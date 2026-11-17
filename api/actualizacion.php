<?php
/**
 * API de Comprobación y Ejecución de Actualizaciones Remotas.
 * Permite que los clientes reciban nuevas versiones automáticamente
 * sin necesidad de conectarse ni asistir físicamente al local.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? 'check';

if ($action === 'check') {
    // 1. Obtener configuraciones de actualización
    $stmt = $pdo->query("SELECT Clave, Valor FROM configuraciones WHERE Clave IN ('UPDATE_CHECK_URL', 'UPDATE_MODO')");
    $cfg = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cfg[$r['Clave']] = $r['Valor'];
    }
    $defaultCheckUrl = 'https://raw.githubusercontent.com/fcardenas886/minimarket-pos-local/master/version.json';
    $updateUrl = trim($cfg['UPDATE_CHECK_URL'] ?? '');
    if ($updateUrl === '') {
        $updateUrl = $defaultCheckUrl;
    }
    $updateModo = $cfg['UPDATE_MODO'] ?? 'preguntar';

    // 2. Consultar el manifiesto remoto (version.json)
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $updateUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'MinimarketPOS-Updater/' . (defined('APP_VERSION') ? APP_VERSION : '1.0') . ' (Windows)'
    ]);
    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    $esFile = (strpos($updateUrl, 'file://') === 0);
    if ($rawResponse === false || (!$esFile && $httpCode !== 200) || ($esFile && $rawResponse === '')) {
        echo json_encode([
            'ok' => false,
            'configurado' => true,
            'error' => 'No se pudo contactar al servidor de actualizaciones (' . ($curlErr ?: "HTTP $httpCode") . ').'
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $manifest = json_decode($rawResponse, true);
    if (!is_array($manifest) || empty($manifest['version']) || empty($manifest['url'])) {
        echo json_encode([
            'ok' => false,
            'configurado' => true,
            'error' => 'El formato del manifiesto de actualización remoto no es válido.'
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. Comparar versiones
    $curVer = ltrim(defined('APP_VERSION') ? APP_VERSION : 'v0.0.0', 'v');
    $remVer = ltrim((string)$manifest['version'], 'v');
    $hayActualizacion = version_compare($remVer, $curVer, '>');

    // 4. Validar derechos según la licencia del cliente
    $lic = licencia_estado();
    $permiteInstalar = true;
    $mensajeLicencia = null;

    if (!empty($lic['actualizaciones_hasta']) && !empty($manifest['fecha'])) {
        if ($lic['actualizaciones_hasta'] < $manifest['fecha']) {
            $permiteInstalar = false;
            $mensajeLicencia = "Tu licencia cubría actualizaciones hasta el {$lic['actualizaciones_hasta']}. Para actualizar a esta versión debes renovar el soporte.";
        }
    }

    // Registrar última revisión
    $now = date('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO configuraciones (Clave, Valor) VALUES ('UPDATE_ULTIMA_REVISION', :val) ON DUPLICATE KEY UPDATE Valor = VALUES(Valor)")
        ->execute([':val' => $now]);

    echo json_encode([
        'ok' => true,
        'configurado' => true,
        'hay_actualizacion' => $hayActualizacion,
        'version_actual' => defined('APP_VERSION') ? APP_VERSION : 'v0.0.0',
        'nueva_version' => (strpos($manifest['version'], 'v') === 0 ? '' : 'v') . $manifest['version'],
        'fecha' => $manifest['fecha'] ?? '',
        'novedades' => (array)($manifest['novedades'] ?? []),
        'url' => $manifest['url'],
        'sha256' => $manifest['sha256'] ?? '',
        'tamano_mb' => $manifest['tamano_mb'] ?? null,
        'obligatoria' => !empty($manifest['obligatoria']),
        'permite_instalar' => $permiteInstalar,
        'mensaje_licencia' => $mensajeLicencia,
        'modo' => $updateModo,
        'ultima_revision' => $now
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'download_and_install') {
    // Solo administradores y supervisores pueden autorizar la instalación
    $user = currentUser();
    if (!in_array($user['rol'] ?? '', ['Administrador', 'Supervisor'], true)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Se requieren permisos de Administrador para aplicar actualizaciones.']);
        exit;
    }

    verifyCsrfApi();

    // Obtener URL del archivo a descargar
    $downloadUrl = trim((string)($_POST['url'] ?? ''));
    $expectedSha256 = trim((string)($_POST['sha256'] ?? ''));

    // Si no se pasó en POST, obtenerla del version.json configurado
    if ($downloadUrl === '') {
        $stmt = $pdo->query("SELECT Valor FROM configuraciones WHERE Clave = 'UPDATE_CHECK_URL'");
        $checkUrl = trim((string)$stmt->fetchColumn()) ?: 'https://raw.githubusercontent.com/fcardenas886/minimarket-pos-local/master/version.json';
        if ($checkUrl !== '') {
            $raw = @file_get_contents($checkUrl);
            $parsed = json_decode((string)$raw, true);
            if (!empty($parsed['url'])) {
                $downloadUrl = $parsed['url'];
                $expectedSha256 = $parsed['sha256'] ?? '';
            }
        }
    }

    if ($downloadUrl === '' || !preg_match('#^https?://#i', $downloadUrl)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'URL de descarga de actualización no válida o no disponible.']);
        exit;
    }

    // Carpeta temporal de trabajo
    $destDir = is_dir('C:/MinimarketPOS/tmp') ? 'C:/MinimarketPOS/tmp' : (sys_get_temp_dir() . '/minimarket_updates');
    @mkdir($destDir, 0777, true);
    $destExe = $destDir . '/MinimarketPOS-Update.exe';

    // Descarga directa por streaming
    $fp = fopen($destExe, 'w+');
    if (!$fp) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo abrir el archivo temporal para escribir la actualización.']);
        exit;
    }

    $ch = curl_init($downloadUrl);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'MinimarketPOS-Updater (Windows)'
    ]);
    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    fclose($fp);

    if (!$success || $httpCode !== 200 || !is_file($destExe) || filesize($destExe) < 2000000) {
        @unlink($destExe);
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => 'Error al descargar el paquete de actualización (' . ($curlErr ?: "HTTP $httpCode o archivo incompleto") . ').'
        ]);
        exit;
    }

    // Validar hash SHA256 si se proporcionó
    if ($expectedSha256 !== '') {
        $actualSha256 = hash_file('sha256', $destExe);
        if (strcasecmp($actualSha256, $expectedSha256) !== 0) {
            @unlink($destExe);
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'error' => 'El archivo descargado no coincide con el hash de seguridad (descarga corrupta).'
            ]);
            exit;
        }
    }

    // Crear script .bat desacoplado que ejecuta el instalador silencioso
    // Inno Setup argumentos: /VERYSILENT /SUPPRESSMSGBOXES /NORESTART
    $batPath = $destDir . '/lanzar_actualizacion.bat';
    $winExe = str_replace('/', '\\', $destExe);
    $batContent = "@echo off\r\n"
                . "chcp 65001 > nul\r\n"
                . "timeout /t 3 /nobreak > nul\r\n"
                . "start \"\" /wait \"" . $winExe . "\" /VERYSILENT /SUPPRESSMSGBOXES /NORESTART\r\n"
                . "del \"" . $winExe . "\" > nul 2>&1\r\n"
                . "del \"%~f0\" > nul 2>&1\r\n";
    file_put_contents($batPath, $batContent);

    // Lanzar proceso desacoplado de PHP (para que PHP pueda responder antes de que se detenga Apache)
    $winBat = str_replace('/', '\\', $batPath);
    pclose(popen("start \"\" /B cmd.exe /c \"" . $winBat . "\"", "r"));

    echo json_encode([
        'ok' => true,
        'mensaje' => 'Actualización descargada correctamente. Iniciando instalador en segundo plano...'
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Acción no reconocida.']);
