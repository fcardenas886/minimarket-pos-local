<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

// Equipo recién instalado (base creada por el instalador, sin administrador): ir al asistente.
if (is_file(__DIR__ . '/instalar.php') && !is_file(__DIR__ . '/config/instalado.lock')) {
    $hayAdmin = (bool)getDB()->query("
        SELECT 1 FROM usuarios u JOIN roles r ON u.RolID = r.RolID
        WHERE r.Nombre = 'Administrador' AND u.Activo = TRUE LIMIT 1
    ")->fetchColumn();
    if (!$hayAdmin) {
        header('Location: instalar.php');
        exit;
    }
}

$error = '';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $minutosEspera = loginRateLimitCheck($ip);

    if ($minutosEspera > 0) {
        $error = "Demasiados intentos fallidos. Intenta nuevamente en $minutosEspera minuto(s).";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!empty($username) && !empty($password)) {
            try {
                $pdo = getDB();
                $stmt = $pdo->prepare("
                    SELECT u.UsuarioID, u.Nombre, u.NombreUsuario, u.PasswordHash, u.Activo, r.Nombre AS Rol
                    FROM usuarios u
                    JOIN roles r ON u.RolID = r.RolID
                    WHERE u.NombreUsuario = :user
                    LIMIT 1
                ");
                $stmt->execute([':user' => $username]);
                $user = $stmt->fetch();

                if ($user && $user['Activo']) {
                    $valid = verificarPasswordUsuario($pdo, (int)$user['UsuarioID'], (string)$user['PasswordHash'], $password);
                    if ($valid && $user['Rol'] === 'Garzón') {
                        $valid = false;
                        $error = 'Los garzones entran con su PIN en la pantalla de garzón (garzon.php).';
                    }

                    if ($valid) {
                        loginRateLimitClear($ip);
                        $_SESSION['usuario'] = [
                            'id' => $user['UsuarioID'],
                            'nombre' => $user['Nombre'],
                            'username' => $user['NombreUsuario'],
                            'rol' => $user['Rol']
                        ];
                        header('Location: index.php');
                        exit;
                    }
                }
                loginRateLimitRegisterFailure($ip);
                $error = $error ?: 'Usuario o contraseña incorrectos.';
            } catch (Exception $e) {
                $error = 'Error en el sistema: ' . $e->getMessage();
            }
        } else {
            $error = 'Por favor completa todos los campos.';
        }
    }
}

try {
    $pdoLogin = getDB();
    $stmtLoginCfg = $pdoLogin->query("SELECT Clave, Valor FROM configuraciones WHERE Clave IN ('TEMA_MODO', 'TEMA_COLOR_ACENTO', 'MINIMARKET_LOGO_URL', 'MINIMARKET_NOMBRE')");
    $loginCfg = [];
    while ($r = $stmtLoginCfg->fetch(PDO::FETCH_ASSOC)) {
        $loginCfg[$r['Clave']] = $r['Valor'];
    }
    $temaModo = $loginCfg['TEMA_MODO'] ?? 'dark';
    $temaAcento = $loginCfg['TEMA_COLOR_ACENTO'] ?? 'indigo';
    $logoUrl = $loginCfg['MINIMARKET_LOGO_URL'] ?? '';
    $nombreEmpresa = $loginCfg['MINIMARKET_NOMBRE'] ?? 'Minimarket POS';
} catch (Exception $e) {
    $temaModo = 'dark';
    $temaAcento = 'indigo';
    $logoUrl = '';
    $nombreEmpresa = 'Minimarket POS';
}

include __DIR__ . '/views/login.view.php';
