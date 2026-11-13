<?php
// La licencia se instala antes de imprimir el menú, para poder redirigir y que
// el menú y el aviso superior se carguen ya con el plan nuevo.
require_once __DIR__ . '/includes/auth.php';
$errorLicencia = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'instalar_licencia') {
    requireLogin();
    verifyCsrf();
    if ((currentUser()['rol'] ?? '') !== 'Administrador') {
        $errorLicencia = 'Solo un Administrador puede instalar la licencia.';
    } else {
        $errLic = licencia_instalar((string)($_POST['licencia_texto'] ?? ''));
        if ($errLic === null) {
            header('Location: configuracion.php?tab=licencia&licencia_ok=1');
            exit;
        }
        $errorLicencia = 'No se pudo instalar la licencia: ' . $errLic;
    }
}

require_once __DIR__ . '/includes/header.php';
checkRole(['Administrador', 'Supervisor']);

$pdo = getDB();
$message = '';
$error = $errorLicencia;

// Procesar Guardado de Parámetros Generales o Haulmer
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? 'save_config';

    if ($action === 'save_config') {
        try {
            // Manejar subida de archivo de logo si se adjuntó
            if (!empty($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmp = $_FILES['logo_file']['tmp_name'];
                $fileName = $_FILES['logo_file']['name'];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $permitidas = ['png', 'jpg', 'jpeg', 'svg', 'webp', 'gif'];

                if (!in_array($ext, $permitidas, true)) {
                    throw new Exception("Formato de imagen no permitido. Usa PNG, JPG, SVG o WebP.");
                }

                $uploadDir = __DIR__ . '/uploads/logo/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $nuevoNombre = 'logo_' . time() . '.' . $ext;
                $destino = $uploadDir . $nuevoNombre;

                if (move_uploaded_file($fileTmp, $destino)) {
                    $_POST['config']['MINIMARKET_LOGO_URL'] = 'uploads/logo/' . $nuevoNombre;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO configuraciones (Clave, Valor) VALUES (:clave, :valor) ON DUPLICATE KEY UPDATE Valor = VALUES(Valor)");
            
            foreach ($_POST['config'] as $clave => $valor) {
                $stmt->execute([':clave' => $clave, ':valor' => trim($valor)]);
            }
            $message = 'Configuraciones y parámetros guardados correctamente.';
        } catch (Exception $e) {
            $error = 'Error al guardar configuraciones: ' . $e->getMessage();
        }
    } elseif ($action === 'instalar_licencia') {
        // Ya procesado al inicio del archivo.
    } elseif (in_array($action, ['mesas_agregar', 'mesa_toggle', 'garzon_agregar', 'garzon_pin', 'garzon_toggle', 'mesas_config'], true)) {
        try {
            if (!modulo_activo('mesas')) throw new Exception('El módulo Mesas y garzones no está activado en la licencia.');
            $pinEnUso = function (string $pin, int $excepto = 0) use ($pdo): bool {
                $st = $pdo->prepare("SELECT UsuarioID, PinHash FROM usuarios WHERE PinHash IS NOT NULL AND UsuarioID <> :u");
                $st->execute([':u' => $excepto]);
                foreach ($st as $r) if (password_verify($pin, $r['PinHash'])) return true;
                return false;
            };
            if ($action === 'mesas_agregar') {
                $zona = trim(mb_substr((string)($_POST['zona'] ?? ''), 0, 40)) ?: 'Salón';
                $cant = max(1, min(50, (int)($_POST['cantidad'] ?? 1)));
                $st = $pdo->prepare("SELECT COUNT(*) FROM mesas WHERE Zona = :z");
                $st->execute([':z' => $zona]);
                $desde = (int)$st->fetchColumn() + 1;
                $ins = $pdo->prepare("INSERT INTO mesas (Nombre, Zona, Orden) VALUES (:n, :z, :o)");
                for ($i = 0; $i < $cant; $i++) {
                    $ins->execute([':n' => "$zona " . ($desde + $i), ':z' => $zona, ':o' => $desde + $i]);
                }
                $message = "Se crearon $cant mesa(s) en $zona.";
            } elseif ($action === 'mesa_toggle') {
                $id = (int)($_POST['mesa_id'] ?? 0);
                $st = $pdo->prepare("SELECT COUNT(*) FROM mesapedidos WHERE MesaID = :m AND Estado = 'Abierta'");
                $st->execute([':m' => $id]);
                if ((int)$st->fetchColumn()) throw new Exception('La mesa tiene una cuenta abierta: cóbrala o anúlala antes de desactivarla.');
                $pdo->prepare("UPDATE mesas SET Activa = NOT Activa WHERE MesaID = :m")->execute([':m' => $id]);
                $message = 'Mesa actualizada.';
            } elseif ($action === 'garzon_agregar' || $action === 'garzon_pin') {
                $pin = preg_replace('/\D/', '', (string)($_POST['pin'] ?? ''));
                if (strlen($pin) < 4 || strlen($pin) > 8) throw new Exception('El PIN debe tener entre 4 y 8 números.');
                $id = (int)($_POST['usuario_id'] ?? 0);
                if ($pinEnUso($pin, $id)) throw new Exception('Ese PIN ya lo usa otra persona. Elige otro.');
                if ($action === 'garzon_agregar') {
                    $nombre = trim(mb_substr((string)($_POST['nombre'] ?? ''), 0, 100));
                    if ($nombre === '') throw new Exception('Indica el nombre del garzón.');
                    $rol = (int)$pdo->query("SELECT RolID FROM roles WHERE Nombre = 'Garzón'")->fetchColumn();
                    $usuario = 'garzon_' . bin2hex(random_bytes(3));
                    $pdo->prepare("INSERT INTO usuarios (Nombre, RutCuerpo, RutDv, NombreUsuario, PasswordHash, PinHash, RolID, Activo)
                                   VALUES (:n, 11111111, '1', :u, :p, :pin, :r, TRUE)")
                        ->execute([':n' => $nombre, ':u' => $usuario, ':p' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                                   ':pin' => password_hash($pin, PASSWORD_DEFAULT), ':r' => $rol]);
                    $message = "Garzón \"$nombre\" creado. Entra en la tablet con su PIN.";
                } else {
                    $pdo->prepare("UPDATE usuarios SET PinHash = :pin WHERE UsuarioID = :u")
                        ->execute([':pin' => password_hash($pin, PASSWORD_DEFAULT), ':u' => $id]);
                    $message = 'PIN actualizado.';
                }
            } elseif ($action === 'garzon_toggle') {
                $pdo->prepare("UPDATE usuarios u JOIN roles r ON r.RolID = u.RolID SET u.Activo = NOT u.Activo
                               WHERE u.UsuarioID = :u AND r.Nombre = 'Garzón'")->execute([':u' => (int)($_POST['usuario_id'] ?? 0)]);
                $message = 'Garzón actualizado.';
            } else {
                $pdo->prepare("INSERT INTO configuraciones (Clave, Valor) VALUES ('MESAS_IMPRIMIR_COMANDA', :v) ON DUPLICATE KEY UPDATE Valor = VALUES(Valor)")
                    ->execute([':v' => ($_POST['imprimir_comanda'] ?? '') === 'SI' ? 'SI' : 'NO']);
                $message = 'Configuración de mesas guardada.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif (in_array($action, ['guardar_eboleta', 'eboleta_probar', 'eboleta_enviar'], true)) {
        require_once __DIR__ . '/includes/sii/EBoletaCola.php';
        try {
            if ($action === 'guardar_eboleta') {
                $prov = $_POST['dte_proveedor'] ?? 'ninguno';
                if (!in_array($prov, ['ninguno', 'eboleta', 'eboleta_simulador'], true)) throw new Exception('Proveedor inválido.');
                $rutUsuario = trim($_POST['eboleta_rut_usuario'] ?? '');
                if ($rutUsuario !== '' && !rut_valido($rutUsuario)) throw new Exception('El RUT de la persona no es válido.');
                $guardar = $pdo->prepare("INSERT INTO configuraciones (Clave, Valor) VALUES (:k, :v) ON DUPLICATE KEY UPDATE Valor = VALUES(Valor)");
                $guardar->execute([':k' => 'DTE_PROVEEDOR', ':v' => $prov]);
                $guardar->execute([':k' => 'EBOLETA_RUT_USUARIO', ':v' => $rutUsuario !== '' ? licencia_normalizar_rut($rutUsuario) : '']);
                $guardar->execute([':k' => 'EBOLETA_SIMULAR_FALLO', ':v' => in_array($_POST['eboleta_simular_fallo'] ?? 'NO', ['NO', 'conexion', 'rechazo', 'incierto'], true) ? $_POST['eboleta_simular_fallo'] : 'NO']);
                $clave = (string)($_POST['eboleta_clave'] ?? '');
                if ($clave !== '') {
                    $guardar->execute([':k' => 'EBOLETA_CLAVE_CIFRADA', ':v' => Cifrado::cifrar($clave)]);
                    @unlink(EBoletaCliente::ARCHIVO_SESION); // forzar login con la clave nueva
                }
                if ($prov === 'eboleta' && $rutUsuario === '') throw new Exception('Configuración guardada, pero falta el RUT de la persona que entra al SII.');
                $message = 'Configuración de boleta electrónica guardada.';
            } elseif ($action === 'eboleta_probar') {
                $cfgE = EBoletaCola::config($pdo);
                if (empty($cfgE['EBOLETA_RUT_USUARIO']) || empty($cfgE['EBOLETA_CLAVE_CIFRADA'])) throw new Exception('Primero guarda el RUT y la Clave Tributaria.');
                $cliente = new EBoletaCliente($cfgE['EBOLETA_RUT_USUARIO'], Cifrado::descifrar($cfgE['EBOLETA_CLAVE_CIFRADA']), 20);
                $p = $cliente->probarConexion($cfgE['MINIMARKET_RUT'] ?? '');
                $message = 'Conexión correcta con el SII. Usuario: ' . ($p['usuario'] ?? '-') . ($p['razon_social'] ? ' · Emisor: ' . $p['razon_social'] : '') . ' · Sucursales: ' . $p['sucursales'] . '. No se emitió ninguna boleta.';
            } else {
                $r = EBoletaCola::procesarPendientes($pdo, 50);
                $message = "Boletas emitidas: {$r['emitidas']}." . ($r['detenido_por'] ? " Quedan pendientes: {$r['detenido_por']}" : '');
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'add_terminal') {
        $nombreEquipo = trim($_POST['nombre_equipo'] ?? '');
        $cajaID = (int)($_POST['caja_id'] ?? 1);

        if (!empty($nombreEquipo)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO terminalescaja (NombreEquipo, CajaID, Activo) VALUES (:equipo, :caja, TRUE)");
                $stmt->execute([':equipo' => $nombreEquipo, ':caja' => $cajaID]);
                $message = "Terminal '$nombreEquipo' vinculada a la Caja #$cajaID correctamente.";
            } catch (Exception $e) {
                $error = 'Error al vincular terminal: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_terminal') {
        $termID = (int)($_POST['terminal_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM terminalescaja WHERE TerminalID = :tid");
            $stmt->execute([':tid' => $termID]);
            $message = 'Terminal desvinculada correctamente.';
        } catch (Exception $e) {
            $error = 'Error al eliminar terminal: ' . $e->getMessage();
        }
    }
}

// Cargar todas las configuraciones existentes
$rows = $pdo->query("SELECT Clave, Valor FROM configuraciones")->fetchAll();
$config = [];
foreach ($rows as $r) {
    $config[$r['Clave']] = $r['Valor'];
}

// Cargar Terminales y Cajas
$terminales = $pdo->query("
    SELECT t.*, c.Nombre AS CajaName 
    FROM terminalescaja t 
    JOIN cajas c ON t.CajaID = c.CajaID 
    ORDER BY t.TerminalID DESC
")->fetchAll();

$cajas = $pdo->query("SELECT * FROM cajas WHERE Activa = TRUE ORDER BY CajaID ASC")->fetchAll();

if (!empty($_GET['licencia_ok'])) {
    $message = 'Licencia instalada correctamente.';
}
require_once __DIR__ . '/includes/sii/EBoletaCola.php';
$eboletaResumen = EBoletaCola::resumen($pdo);

// Módulo Mesas y garzones
$mesasLista = $garzones = $ipsLocales = [];
if (modulo_activo('mesas')) {
    $mesasLista = $pdo->query("
        SELECT m.*, (SELECT COUNT(*) FROM mesapedidos mp WHERE mp.MesaID = m.MesaID AND mp.Estado = 'Abierta') AS Abierta
        FROM mesas m ORDER BY m.Zona, m.Orden, m.MesaID
    ")->fetchAll();
    $garzones = $pdo->query("
        SELECT u.UsuarioID, u.Nombre, u.Activo FROM usuarios u JOIN roles r ON r.RolID = u.RolID
        WHERE r.Nombre = 'Garzón' ORDER BY u.Activo DESC, u.Nombre
    ")->fetchAll();
    $ipsLocales = ips_del_equipo();
}
$licencia = licencia_estado();
$licUsuarios = licencia_usuarios_disponibles($pdo);
$tabInicial = preg_replace('/[^a-z]/', '', (string)($_GET['tab'] ?? ''));

include __DIR__ . '/views/configuracion.view.php';
require_once __DIR__ . '/includes/footer.php';
