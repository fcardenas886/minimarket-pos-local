<?php
/**
 * Asistente de instalación para un equipo nuevo.
 *
 * Paso 1: conexión a MySQL -> crea la base, carga db/instalacion/*.sql y escribe .env
 * Paso 2: datos del negocio y usuario administrador
 * Paso 3: licencia (opcional, se puede instalar después en Configuración)
 *
 * Solo responde desde el mismo equipo (localhost) y se bloquea cuando el sistema
 * ya está instalado (config/instalado.lock, o .env con un administrador creado).
 */
declare(strict_types=1);
session_start();

const RAIZ = __DIR__;
const ENV_PATH = RAIZ . '/.env';
const LOCK_PATH = RAIZ . '/config/instalado.lock';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function leerEnv(): array {
    $env = [];
    if (!is_file(ENV_PATH)) return $env;
    foreach (file(ENV_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        $l = trim($l);
        if ($l === '' || $l[0] === '#' || !str_contains($l, '=')) continue;
        [$k, $v] = explode('=', $l, 2);
        $env[trim($k)] = trim(trim($v), "\"'");
    }
    return $env;
}

function conectar(array $c, bool $conBase = true): PDO {
    $dsn = "mysql:host={$c['DB_HOST']};port={$c['DB_PORT']};charset=utf8mb4" . ($conBase ? ";dbname={$c['DB_NAME']}" : '');
    return new PDO($dsn, $c['DB_USER'], $c['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function hayAdministrador(array $env): bool {
    try {
        return (bool)conectar($env)->query("
            SELECT 1 FROM usuarios u JOIN roles r ON u.RolID = r.RolID
            WHERE r.Nombre = 'Administrador' AND u.Activo = TRUE LIMIT 1
        ")->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function rutValido(string $rut): bool {
    $rut = strtoupper(preg_replace('/[^0-9kK]/', '', $rut));
    if (!preg_match('/^(\d{7,8})([0-9K])$/', $rut, $m)) return false;
    $suma = 0; $mult = 2;
    for ($i = strlen($m[1]) - 1; $i >= 0; $i--) {
        $suma += (int)$m[1][$i] * $mult;
        $mult = $mult === 7 ? 2 : $mult + 1;
    }
    $dv = 11 - ($suma % 11);
    $dv = $dv === 11 ? '0' : ($dv === 10 ? 'K' : (string)$dv);
    return $dv === $m[2];
}

function formatearRut(string $rut): string {
    $rut = strtoupper(preg_replace('/[^0-9kK]/', '', $rut));
    $cuerpo = substr($rut, 0, -1);
    return number_format((int)$cuerpo, 0, '', '.') . '-' . substr($rut, -1);
}

// --- Seguridad: solo desde este equipo y solo si no está instalado ---
$remoto = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remoto, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('La instalación solo se puede hacer desde el mismo equipo (http://localhost).');
}

$env = leerEnv();
$instalado = is_file(LOCK_PATH) || (isset($env['DB_NAME']) && hayAdministrador($env));
if (empty($_SESSION['instalacion_csrf'])) {
    $_SESSION['instalacion_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['instalacion_csrf'];

// Paso actual: 1 sin .env, 2 con .env sin administrador, 3 licencia, 4 listo.
$paso = isset($env['DB_NAME']) ? 2 : 1;
if (!empty($_SESSION['instalacion_paso'])) $paso = (int)$_SESSION['instalacion_paso'];
if ($instalado && $paso < 3) $paso = 0;

$error = '';
$form = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $paso > 0) {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'El formulario expiró. Recarga la página.';
    } elseif ($paso === 1) {
        $c = [
            'DB_HOST' => trim($_POST['db_host'] ?? '127.0.0.1') ?: '127.0.0.1',
            'DB_PORT' => trim($_POST['db_port'] ?? '3306') ?: '3306',
            'DB_NAME' => trim($_POST['db_name'] ?? ''),
            'DB_USER' => trim($_POST['db_user'] ?? ''),
            'DB_PASS' => (string)($_POST['db_pass'] ?? ''),
        ];
        try {
            if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $c['DB_NAME'])) {
                throw new Exception('El nombre de la base solo puede tener letras, números y guion bajo.');
            }
            if ($c['DB_USER'] === '') throw new Exception('Indica el usuario de MySQL.');
            if (preg_match('/[\r\n]/', implode('', $c))) throw new Exception('Los datos no pueden tener saltos de línea.');

            $srv = conectar($c, false);
            $srv->exec("CREATE DATABASE IF NOT EXISTS `{$c['DB_NAME']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $db = conectar($c);
            $tablas = (int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
            if ($tablas > 0) {
                throw new Exception("La base \"{$c['DB_NAME']}\" ya tiene tablas. Usa otro nombre para no borrar datos existentes.");
            }
            foreach (['01_esquema.sql', '02_datos_base.sql'] as $archivo) {
                $sql = file_get_contents(RAIZ . '/db/instalacion/' . $archivo);
                if ($sql === false) throw new Exception("Falta el archivo db/instalacion/$archivo.");
                $db->exec($sql);
            }

            $contenido = "# Generado por instalar.php el " . date('Y-m-d H:i') . "\n";
            foreach ($c as $k => $v) $contenido .= "$k=$v\n";
            if (file_put_contents(ENV_PATH, $contenido, LOCK_EX) === false) {
                throw new Exception('No se pudo escribir el archivo .env (revisa permisos de la carpeta).');
            }
            $_SESSION['instalacion_paso'] = 2;
            header('Location: instalar.php');
            exit;
        } catch (PDOException $e) {
            $error = 'No se pudo conectar o crear la base de datos: ' . $e->getMessage();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif ($paso === 2) {
        try {
            $nombre = trim($_POST['negocio_nombre'] ?? '');
            $rut = trim($_POST['negocio_rut'] ?? '');
            $adminNombre = trim($_POST['admin_nombre'] ?? '');
            $adminUser = trim($_POST['admin_usuario'] ?? '');
            $pass = (string)($_POST['admin_pass'] ?? '');
            if ($nombre === '') throw new Exception('Indica el nombre del negocio.');
            if (!rutValido($rut)) throw new Exception('El RUT del negocio no es válido (revisa el dígito verificador).');
            if ($adminNombre === '' || !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $adminUser)) {
                throw new Exception('Indica el nombre del administrador y un usuario de 3 a 50 caracteres (letras, números, punto o guion).');
            }
            if (strlen($pass) < 8) throw new Exception('La contraseña debe tener al menos 8 caracteres.');
            if ($pass !== (string)($_POST['admin_pass2'] ?? '')) throw new Exception('Las contraseñas no coinciden.');

            $db = conectar($env);
            if (hayAdministrador($env)) throw new Exception('Ya existe un administrador: el sistema ya está instalado.');
            $db->beginTransaction();
            $cfg = $db->prepare("UPDATE configuraciones SET Valor = :v WHERE Clave = :k");
            $datos = [
                'MINIMARKET_NOMBRE' => $nombre,
                'MINIMARKET_RUT' => formatearRut($rut),
                'MINIMARKET_DIRECCION' => trim($_POST['negocio_direccion'] ?? ''),
                'MINIMARKET_COMUNA' => trim($_POST['negocio_comuna'] ?? ''),
                'MINIMARKET_GIRO' => trim($_POST['negocio_giro'] ?? ''),
                'MINIMARKET_TELEFONO' => trim($_POST['negocio_telefono'] ?? ''),
            ];
            foreach ($datos as $k => $v) $cfg->execute([':k' => $k, ':v' => mb_substr($v, 0, 500)]);

            $rolAdmin = (int)$db->query("SELECT RolID FROM roles WHERE Nombre = 'Administrador'")->fetchColumn();
            $db->prepare("
                INSERT INTO usuarios (Nombre, RutCuerpo, RutDv, NombreUsuario, PasswordHash, RolID, Activo)
                VALUES (:n, 11111111, '1', :u, :p, :r, TRUE)
            ")->execute([':n' => $adminNombre, ':u' => $adminUser, ':p' => password_hash($pass, PASSWORD_DEFAULT), ':r' => $rolAdmin]);
            $db->commit();

            file_put_contents(LOCK_PATH, 'Instalado el ' . date('Y-m-d H:i:s') . "\n", LOCK_EX);
            $_SESSION['instalacion_paso'] = 3;
            header('Location: instalar.php');
            exit;
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            $error = $e->getMessage();
        }
    } elseif ($paso === 3) {
        if (($_POST['accion'] ?? '') === 'omitir') {
            $_SESSION['instalacion_paso'] = 4;
            header('Location: instalar.php');
            exit;
        }
        require_once RAIZ . '/config/database.php';
        require_once RAIZ . '/includes/licencia.php';
        $errLic = licencia_instalar((string)($_POST['licencia'] ?? ''));
        if ($errLic === null) {
            $_SESSION['instalacion_paso'] = 4;
            header('Location: instalar.php');
            exit;
        }
        $error = $errLic;
    }
}

if ($paso === 4) {
    unset($_SESSION['instalacion_paso']);
}
$pasos = [1 => 'Base de datos', 2 => 'Negocio y administrador', 3 => 'Licencia'];
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Instalación - Minimarket POS</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
  <style>
    .inst-wrap { max-width: 640px; margin: 2rem auto; padding: 0 1rem; }
    .inst-card { background: var(--card-bg); border: 1px solid var(--border-dark); border-radius: 14px; padding: 1.75rem; }
    .inst-pasos { display: flex; gap: 0.5rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
    .inst-paso { flex: 1; min-width: 140px; font-size: 0.8rem; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-dark); color: var(--text-muted); }
    .inst-paso.activo { border-color: var(--primary); color: var(--text-main); font-weight: 700; }
    .inst-paso.hecho { color: var(--success); }
    .inst-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .inst-grid .full { grid-column: 1 / -1; }
    .inst-card label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem; }
    .inst-error { background: rgba(239, 68, 68, 0.12); border: 1px solid var(--danger); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.9rem; }
    .inst-ayuda { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem; }
    @media (max-width: 560px) { .inst-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
<div class="inst-wrap">
  <h1 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.25rem;"><i class="fa-solid fa-store"></i> Instalación de Minimarket POS</h1>
  <p class="inst-ayuda">Configura este equipo en tres pasos.</p>

  <?php if ($paso > 0): ?>
  <div class="inst-pasos">
    <?php foreach ($pasos as $n => $titulo): ?>
      <div class="inst-paso <?= $n === $paso ? 'activo' : ($n < $paso ? 'hecho' : '') ?>">
        <?= $n < $paso ? '<i class="fa-solid fa-check"></i>' : $n . '.' ?> <?= h($titulo) ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="inst-card">
    <?php if ($error): ?><div class="inst-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= h($error) ?></div><?php endif; ?>

    <?php if ($paso === 0): ?>
      <h2 style="font-size: 1.15rem; margin-bottom: 0.5rem;">El sistema ya está instalado</h2>
      <p class="inst-ayuda">Por seguridad, el asistente no se puede volver a usar en este equipo.</p>
      <a href="login.php" class="btn btn-primary"><i class="fa-solid fa-right-to-bracket"></i> Ir al inicio de sesión</a>

    <?php elseif ($paso === 1): ?>
      <h2 style="font-size: 1.15rem; margin-bottom: 0.5rem;">1. Base de datos</h2>
      <p class="inst-ayuda">Datos de MySQL de este equipo. Se creará una base nueva y vacía; no se toca ninguna base existente.</p>
      <form method="POST" class="inst-grid">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <div><label>Servidor</label><input class="form-control" name="db_host" value="<?= h($form['db_host'] ?? '127.0.0.1') ?>"></div>
        <div><label>Puerto</label><input class="form-control" name="db_port" value="<?= h($form['db_port'] ?? '3306') ?>"></div>
        <div class="full"><label>Nombre de la base nueva</label><input class="form-control" name="db_name" value="<?= h($form['db_name'] ?? 'minimarket_local') ?>" required></div>
        <div><label>Usuario MySQL</label><input class="form-control" name="db_user" value="<?= h($form['db_user'] ?? 'root') ?>" required autocomplete="off"></div>
        <div><label>Contraseña MySQL</label><input class="form-control" type="password" name="db_pass" autocomplete="new-password"></div>
        <div class="full"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-database"></i> Crear base de datos</button></div>
      </form>

    <?php elseif ($paso === 2): ?>
      <h2 style="font-size: 1.15rem; margin-bottom: 0.5rem;">2. Negocio y administrador</h2>
      <p class="inst-ayuda">El RUT debe ser el mismo de la licencia y del SII. Podrás cambiar estos datos después en Ajustes Generales.</p>
      <form method="POST" class="inst-grid">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <div class="full"><label>Nombre del negocio</label><input class="form-control" name="negocio_nombre" value="<?= h($form['negocio_nombre'] ?? '') ?>" required></div>
        <div><label>RUT del negocio</label><input class="form-control" name="negocio_rut" value="<?= h($form['negocio_rut'] ?? '') ?>" placeholder="76.123.456-7" required></div>
        <div><label>Teléfono</label><input class="form-control" name="negocio_telefono" value="<?= h($form['negocio_telefono'] ?? '') ?>"></div>
        <div class="full"><label>Dirección</label><input class="form-control" name="negocio_direccion" value="<?= h($form['negocio_direccion'] ?? '') ?>"></div>
        <div><label>Comuna</label><input class="form-control" name="negocio_comuna" value="<?= h($form['negocio_comuna'] ?? '') ?>"></div>
        <div><label>Giro</label><input class="form-control" name="negocio_giro" value="<?= h($form['negocio_giro'] ?? 'Minimarket y venta de abarrotes') ?>"></div>
        <div class="full" style="border-top: 1px solid var(--border-dark); margin-top: 0.5rem; padding-top: 1rem; font-weight: 700;">Usuario administrador</div>
        <div><label>Nombre</label><input class="form-control" name="admin_nombre" value="<?= h($form['admin_nombre'] ?? '') ?>" required></div>
        <div><label>Usuario para entrar</label><input class="form-control" name="admin_usuario" value="<?= h($form['admin_usuario'] ?? 'admin') ?>" required autocomplete="off"></div>
        <div><label>Contraseña (mín. 8)</label><input class="form-control" type="password" name="admin_pass" minlength="8" required autocomplete="new-password"></div>
        <div><label>Repetir contraseña</label><input class="form-control" type="password" name="admin_pass2" minlength="8" required autocomplete="new-password"></div>
        <div class="full"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-user-shield"></i> Guardar y continuar</button></div>
      </form>

    <?php elseif ($paso === 3): ?>
      <h2 style="font-size: 1.15rem; margin-bottom: 0.5rem;">3. Licencia</h2>
      <p class="inst-ayuda">Pega la licencia que te entregó tu proveedor (empieza con <code>MML1.</code>). Si aún no la tienes, puedes omitir este paso: el sistema funcionará como plan Básico y no emitirá boletas hasta instalarla en Configuración → Licencia.</p>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <textarea class="form-control" name="licencia" rows="4" style="width: 100%; font-family: monospace; font-size: 0.8rem;"><?= h($form['licencia'] ?? '') ?></textarea>
        <div style="display: flex; gap: 0.5rem; margin-top: 0.75rem;">
          <button class="btn btn-primary" type="submit" name="accion" value="instalar"><i class="fa-solid fa-key"></i> Instalar licencia</button>
          <button class="btn btn-secondary" type="submit" name="accion" value="omitir" formnovalidate>Omitir por ahora</button>
        </div>
      </form>

    <?php else: ?>
      <h2 style="font-size: 1.15rem; margin-bottom: 0.5rem;"><i class="fa-solid fa-circle-check" style="color: var(--success);"></i> Instalación terminada</h2>
      <p class="inst-ayuda">Entra con el usuario administrador que creaste. Te recomendamos completar el logo y el pie del ticket en Ajustes Generales, y crear el usuario del cajero.</p>
      <a href="login.php" class="btn btn-primary"><i class="fa-solid fa-right-to-bracket"></i> Ir al inicio de sesión</a>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
