<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/licencia.php';

// Desde tablets/celulares de la red solo se permiten las pantallas de garzón y cocina.
proteger_acceso_red();

// Corta pantallas y APIs de módulos que el plan instalado no incluye.
licencia_proteger_script_actual();

function requireLogin() {
    if (empty($_SESSION['usuario'])) {
        header('Location: login.php');
        exit;
    }
}

function currentUser() {
    return $_SESSION['usuario'] ?? null;
}

function checkRole($allowedRoles = []) {
    requireLogin();
    $user = currentUser();
    if (!empty($allowedRoles) && !in_array($user['rol'], $allowedRoles)) {
        http_response_code(403);
        die("<div style='font-family:sans-serif; padding:40px; text-align:center;'><h2>403 - Acceso Denegado</h2><p>No tienes permisos suficientes para esta sección.</p><a href='index.php'>Volver al inicio</a></div>");
    }
}

function formatCLP($monto) {
    return '$' . number_format((float)$monto, 0, ',', '.');
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function csrfMatches($token) {
    return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

// Para formularios HTML tradicionales (POST con redirect/render de vista)
function verifyCsrf() {
    if (!csrfMatches($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die("<div style='font-family:sans-serif; padding:40px; text-align:center;'><h2>403 - Sesión expirada</h2><p>Tu sesión o el formulario expiraron. Refresca la página e inténtalo de nuevo.</p><a href='javascript:history.back()'>Volver</a></div>");
    }
}

// Para endpoints JSON llamados por fetch(); el token llega en el header X-CSRF-Token
function verifyCsrfApi() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!csrfMatches($token)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido. Refresca la página e inténtalo de nuevo.']);
        exit;
    }
}

// Bloqueo de intentos fallidos de login por IP. Usa un archivo fuera del
// docroot (temp del sistema) en vez de una tabla, para no requerir migraciones.
define('LOGIN_ATTEMPTS_MAX', 5);
define('LOGIN_ATTEMPTS_WINDOW', 900); // 15 minutos

function loginAttemptsPath() {
    return sys_get_temp_dir() . '/minimarket_login_attempts.json';
}

function loginAttemptsLoad() {
    $path = loginAttemptsPath();
    if (!file_exists($path)) return [];
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function loginAttemptsSave($data) {
    file_put_contents(loginAttemptsPath(), json_encode($data), LOCK_EX);
}

// Devuelve 0 si puede intentar login, o los minutos que faltan para desbloquear.
function loginRateLimitCheck($ip) {
    $data = loginAttemptsLoad();
    $now = time();
    $intentos = array_values(array_filter($data[$ip] ?? [], fn($t) => $t > $now - LOGIN_ATTEMPTS_WINDOW));
    $data[$ip] = $intentos;
    loginAttemptsSave($data);

    if (count($intentos) >= LOGIN_ATTEMPTS_MAX) {
        return max(1, (int)ceil((min($intentos) + LOGIN_ATTEMPTS_WINDOW - $now) / 60));
    }
    return 0;
}

function loginRateLimitRegisterFailure($ip) {
    $data = loginAttemptsLoad();
    $data[$ip][] = time();
    loginAttemptsSave($data);
}

function loginRateLimitClear($ip) {
    $data = loginAttemptsLoad();
    unset($data[$ip]);
    loginAttemptsSave($data);
}

// Verifica la contraseña de un usuario. Acepta el hash (password_hash) y, solo para
// usuarios antiguos guardados en texto plano, la clave tal cual; en ese caso la
// convierte a hash en el momento, así que la clave en texto plano desaparece al primer uso.
function verificarPasswordUsuario(PDO $pdo, int $usuarioID, string $hashGuardado, string $plainPass): bool {
    if ($plainPass === '' || $hashGuardado === '') return false;
    $esHash = password_get_info($hashGuardado)['algo'] !== null;
    $valida = $esHash ? password_verify($plainPass, $hashGuardado) : hash_equals($hashGuardado, $plainPass);
    if ($valida && (!$esHash || password_needs_rehash($hashGuardado, PASSWORD_DEFAULT))) {
        $pdo->prepare("UPDATE usuarios SET PasswordHash = :h WHERE UsuarioID = :id")
            ->execute([':h' => password_hash($plainPass, PASSWORD_DEFAULT), ':id' => $usuarioID]);
    }
    return $valida;
}

// Autoriza con la clave de un Supervisor o Administrador activo.
// Devuelve el UsuarioID cuya clave coincidió (para dejar registrado quién autorizó),
// o null si la clave no es válida.
function verificarClaveSupervisor(PDO $pdo, string $plainPass): ?int {
    if (empty($plainPass)) return null;
    $stmt = $pdo->prepare("
        SELECT u.UsuarioID, u.PasswordHash FROM usuarios u
        JOIN roles r ON u.RolID = r.RolID
        WHERE r.Nombre IN ('Administrador', 'Supervisor') AND u.Activo = TRUE
    ");
    $stmt->execute();
    foreach ($stmt->fetchAll() as $sup) {
        if (verificarPasswordUsuario($pdo, (int)$sup['UsuarioID'], (string)$sup['PasswordHash'], $plainPass)) {
            return (int)$sup['UsuarioID'];
        }
    }
    return null;
}
