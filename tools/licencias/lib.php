<?php
/**
 * Funciones del proveedor para emitir licencias. HERRAMIENTA PERSONAL: no se instala en clientes.
 *
 * La clave privada y el registro de licencias emitidas viven fuera del proyecto:
 *   %USERPROFILE%\.minimarket-licencias\privada.pem
 *   %USERPROFILE%\.minimarket-licencias\registro.json
 * (la carpeta se puede cambiar con la variable de entorno MML_DIR).
 */
require_once __DIR__ . '/../../includes/licencia.php'; // planes, rut_valido(), licencia_verificar_texto()

function mml_dir(): string {
    return rtrim(getenv('MML_DIR') ?: ((getenv('USERPROFILE') ?: getenv('HOME')) . '/.minimarket-licencias'), '/\\');
}

function mml_ruta_privada(): string {
    return getenv('MML_CLAVE_PRIVADA') ?: mml_dir() . '/privada.pem';
}

function mml_ruta_registro(): string {
    return mml_dir() . '/registro.json';
}

function mml_rut_normalizar(string $rut): string {
    $r = strtoupper(preg_replace('/[^0-9kK]/', '', $rut));
    return strlen($r) < 2 ? '' : substr($r, 0, -1) . '-' . substr($r, -1);
}

function mml_rut_formatear(string $rut): string {
    $r = mml_rut_normalizar($rut);
    if ($r === '') return '';
    [$cuerpo, $dv] = explode('-', $r);
    return number_format((int)$cuerpo, 0, '', '.') . '-' . $dv;
}

/** Firma una licencia nueva. Lanza Exception si los datos no son válidos. */
function mml_generar(string $cliente, string $rut, string $plan, string $hasta, array $extras = []): array {
    $cliente = trim($cliente);
    if ($cliente === '') throw new Exception('Indica el nombre del cliente.');
    if (!rut_valido($rut)) throw new Exception("El RUT $rut no es válido (revisa el dígito verificador).");
    if (!isset(LICENCIA_PLANES[$plan])) throw new Exception('Plan inválido.');
    $f = DateTime::createFromFormat('Y-m-d', $hasta);
    if (!$f || $f->format('Y-m-d') !== $hasta) throw new Exception('La fecha de actualizaciones debe ser AAAA-MM-DD.');

    $privada = @openssl_pkey_get_private((string)@file_get_contents(mml_ruta_privada()));
    if (!$privada) throw new Exception('No se encontró la clave privada en ' . mml_ruta_privada());

    $extras = array_values(array_unique($extras));
    foreach ($extras as $x) {
        if (!isset(LICENCIA_EXTRAS[$x])) throw new Exception("Módulo extra desconocido: $x");
    }
    $datos = [
        'v' => 1,
        'cliente' => $cliente,
        'rut' => mml_rut_normalizar($rut),
        'plan' => $plan,
        'actualizaciones_hasta' => $hasta,
        'emitida' => date('Y-m-d'),
    ];
    if ($extras) $datos['extras'] = $extras;
    $b64 = fn($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $firmado = 'MML1.' . $b64(json_encode($datos, JSON_UNESCAPED_UNICODE));
    if (!openssl_sign($firmado, $firma, $privada, OPENSSL_ALGO_SHA256)) throw new Exception('No se pudo firmar la licencia.');
    $texto = $firmado . '.' . $b64($firma);

    // Comprobación cruzada: la licencia debe validar con la clave pública que trae el sistema.
    if (!licencia_verificar_texto($texto)['ok']) {
        throw new Exception('La licencia no valida con la clave pública del sistema: la clave privada no corresponde.');
    }
    return $datos + ['extras' => [], 'texto' => $texto];
}

function mml_registro_leer(): array {
    $ruta = mml_ruta_registro();
    if (!is_file($ruta)) return [];
    $d = json_decode((string)file_get_contents($ruta), true);
    return is_array($d) ? $d : [];
}

/** Guarda una licencia emitida en el registro personal. */
function mml_registro_agregar(array $lic, string $nota = ''): void {
    $todo = mml_registro_leer();
    $todo[] = [
        'id' => bin2hex(random_bytes(6)),
        'cliente' => $lic['cliente'],
        'rut' => $lic['rut'],
        'plan' => $lic['plan'],
        'extras' => $lic['extras'] ?? [],
        'emitida' => $lic['emitida'],
        'actualizaciones_hasta' => $lic['actualizaciones_hasta'],
        'nota' => $nota,
        'creada_en' => date('Y-m-d H:i:s'),
        'texto' => $lic['texto'],
    ];
    if (!is_dir(mml_dir())) mkdir(mml_dir(), 0700, true);
    file_put_contents(mml_ruta_registro(), json_encode($todo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/** Última licencia emitida por RUT (la vigente de cada cliente), más el historial. */
function mml_clientes(): array {
    $porRut = [];
    foreach (mml_registro_leer() as $l) {
        $porRut[$l['rut']]['historial'][] = $l;
        $porRut[$l['rut']]['actual'] = $l;
    }
    uasort($porRut, fn($a, $b) => strcmp($b['actual']['creada_en'], $a['actual']['creada_en']));
    return $porRut;
}
