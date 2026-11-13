<?php
/**
 * Restablece la contraseña de un usuario (por ejemplo, si el dueño la olvidó).
 * Solo funciona desde la terminal del propio equipo, nunca desde el navegador.
 *
 * Uso (desde la carpeta del sistema):
 *   php scripts/restablecer_clave.php <usuario>
 *
 * Pide la contraseña nueva sin mostrarla, la guarda cifrada y reactiva al usuario.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../config/database.php';

$usuario = $argv[1] ?? '';
if ($usuario === '') {
    fwrite(STDERR, "Uso: php scripts/restablecer_clave.php <usuario>\n");
    exit(1);
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT UsuarioID, Nombre FROM usuarios WHERE NombreUsuario = :u");
$stmt->execute([':u' => $usuario]);
$u = $stmt->fetch();
if (!$u) {
    fwrite(STDERR, "No existe el usuario \"$usuario\". Usuarios: " . implode(', ', $pdo->query("SELECT NombreUsuario FROM usuarios")->fetchAll(PDO::FETCH_COLUMN)) . "\n");
    exit(1);
}

function leerClave(string $prompt): string {
    fwrite(STDOUT, $prompt);
    if (stripos(PHP_OS, 'WIN') === 0) {
        // PowerShell lee la clave sin mostrarla en pantalla.
        $clave = shell_exec('powershell -NoProfile -Command "$p = Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"');
    } else {
        shell_exec('stty -echo');
        $clave = fgets(STDIN);
        shell_exec('stty echo');
        fwrite(STDOUT, "\n");
    }
    return rtrim((string)$clave, "\r\n");
}

$clave = leerClave("Nueva contraseña para {$u['Nombre']} ({$usuario}): ");
if (strlen($clave) < 8) {
    fwrite(STDERR, "La contraseña debe tener al menos 8 caracteres.\n");
    exit(1);
}
if ($clave !== leerClave("Repítela: ")) {
    fwrite(STDERR, "Las contraseñas no coinciden.\n");
    exit(1);
}

$pdo->prepare("UPDATE usuarios SET PasswordHash = :h, Activo = TRUE WHERE UsuarioID = :id")
    ->execute([':h' => password_hash($clave, PASSWORD_DEFAULT), ':id' => $u['UsuarioID']]);
echo "Listo: la contraseña de \"$usuario\" quedó actualizada (cifrada) y el usuario activo.\n";
