<?php
/**
 * Arma la carpeta instalador/paquete/ con todo lo que lleva el instalador:
 *   app/      el sistema (sin herramientas del proveedor, git, datos ni licencias)
 *   php/      PHP 8.1 solo con lo necesario
 *   apache/   Apache 2.4 sin manuales ni ejemplos
 *   mysql/    servidor MySQL 8 y utilidades mínimas
 *   configurar.php, iniciar.bat ...
 *
 * Uso (desde la raíz del proyecto):  php instalador/preparar.php
 * Después se compila con Inno Setup: instalador/minimarket.iss
 */
if (PHP_SAPI !== 'cli') exit;

$raiz = realpath(__DIR__ . '/..');
$destino = __DIR__ . '/paquete';
$laragon = getenv('LARAGON_BIN') ?: 'C:/laragon/bin';
$fuentes = [
    'php' => "$laragon/php/php-8.1.10-Win32-vs16-x64",
    'apache' => "$laragon/apache/httpd-2.4.54-win64-VS16",
    'mysql' => "$laragon/mysql/mysql-8.0.30-winx64",
    'cacert' => dirname($laragon) . '/etc/ssl/cacert.pem',
];
foreach ($fuentes as $k => $ruta) {
    if (!file_exists($ruta)) { fwrite(STDERR, "No se encontró $k en $ruta\n"); exit(1); }
}

function borrar(string $ruta): void {
    if (!file_exists($ruta)) return;
    if (is_file($ruta) || is_link($ruta)) { unlink($ruta); return; }
    foreach (scandir($ruta) as $f) if ($f !== '.' && $f !== '..') borrar("$ruta/$f");
    rmdir($ruta);
}

/** Copia recursiva. $excluir recibe la ruta relativa (con /) y devuelve true para omitir. */
function copiar(string $origen, string $dest, ?callable $excluir = null, string $rel = ''): int {
    $bytes = 0;
    if (!is_dir($dest)) mkdir($dest, 0777, true);
    foreach (scandir($origen) as $f) {
        if ($f === '.' || $f === '..') continue;
        $r = ltrim("$rel/$f", '/');
        if ($excluir && $excluir($r, is_dir("$origen/$f"))) continue;
        if (is_dir("$origen/$f")) {
            $bytes += copiar("$origen/$f", "$dest/$f", $excluir, $r);
        } else {
            copy("$origen/$f", "$dest/$f");
            $bytes += filesize("$origen/$f");
        }
    }
    return $bytes;
}

$mb = fn($b) => number_format($b / 1048576, 1) . ' MB';
echo "Limpiando $destino ...\n";
borrar($destino);
mkdir($destino, 0777, true);

// 1. Sistema
$excluirApp = function (string $r, bool $esDir): bool {
    $fuera = ['.git', '.claude', '.vscode', '.idea', 'tools', 'instalador', 'node_modules',
              '.env', 'DOCUMENTACION_TECNICA.md', 'config/licencia.lic', 'config/instalado.lock',
              'config/clave_cifrado.key', 'config/eboleta_sesion.json'];
    if (in_array($r, $fuera, true)) return true;
    if (preg_match('/\.(stackdump|bak|log)$/i', $r)) return true;
    if (preg_match('#^config/licencia\.lic#', $r)) return true;
    // Datos del equipo de desarrollo: se crean las carpetas vacías más abajo.
    if (preg_match('#^(uploads|storage/boletas)/.+#', $r)) return true;
    return false;
};
$t = copiar($raiz, "$destino/app", $excluirApp);
foreach (['uploads/logo', 'storage/boletas'] as $d) @mkdir("$destino/app/$d", 0777, true);
echo "  app     ", $mb($t), "\n";

// 2. PHP: sin herramientas de desarrollo, sin ICU (intl no se usa), solo extensiones necesarias.
$extensiones = ['php_curl.dll', 'php_fileinfo.dll', 'php_mbstring.dll', 'php_openssl.dll', 'php_pdo_mysql.dll'];
$t = copiar($fuentes['php'], "$destino/php", function (string $r, bool $esDir) use ($extensiones) {
    if (in_array($r, ['dev', 'extras', 'lib', 'php.ini', 'php.ini-development', 'php.ini-production', 'pear'], true)) return true;
    if (preg_match('/^(icu|php8phpdbg|phpdbg|php8embed|php-cgi|php-win|deplister)/i', $r)) return true;
    if (preg_match('/\.(pdb|lib)$/i', $r)) return true;
    if (strpos($r, 'ext/') === 0 && !in_array(basename($r), $extensiones, true)) return true;
    return false;
});
@mkdir("$destino/php/extras/ssl", 0777, true);
copy($fuentes['cacert'], "$destino/php/extras/ssl/cacert.pem");
echo "  php     ", $mb($t), "\n";

// 3. Apache: sin manuales/ejemplos ni las copias de PHP/ICU que agrega Laragon.
$t = copiar($fuentes['apache'], "$destino/apache", function (string $r, bool $esDir) {
    if (in_array($r, ['manual', 'htdocs', 'cgi-bin', 'include', 'lib', 'logs', 'icons', 'error', 'conf/extra', 'conf/original', 'conf/ssl'], true)) return true;
    if (preg_match('#^bin/(icu|php8|php_|libsqlite|libpq|libsasl|iconv|glib|gmodule|libenchant)#i', $r)) return true;
    if (preg_match('/\.(pdb|lib|exp)$/i', $r)) return true;
    if (strpos($r, 'conf/') === 0 && !in_array($r, ['conf/mime.types'], true)) return true;
    return false;
});
echo "  apache  ", $mb($t), "\n";

// 4. MySQL: servidor + cliente + mysqldump + sus DLL y el directorio share (mensajes y juegos de caracteres).
$binarios = ['mysqld.exe', 'mysql.exe', 'mysqldump.exe', 'mysqladmin.exe'];
$t = copiar($fuentes['mysql'], "$destino/mysql", function (string $r, bool $esDir) use ($binarios) {
    if (in_array($r, ['docs', 'include', 'my.ini', 'README', 'data', 'lib'], true)) return true;
    if (strpos($r, 'bin/') === 0) {
        $f = basename($r);
        if (preg_match('/\.exe$/i', $f)) return !in_array($f, $binarios, true);
        if (preg_match('/debug|\.(pdb|lib|pl)$/i', $f)) return true;
    }
    return false;
});
echo "  mysql   ", $mb($t), "\n";

// 5. Runtime de Visual C++ junto a cada programa (por si el equipo no lo tiene instalado).
foreach (['vcruntime140.dll', 'vcruntime140_1.dll', 'msvcp140.dll'] as $dll) {
    $src = 'C:/Windows/System32/' . $dll;
    if (!is_file($src)) { echo "  (aviso) no se encontró $dll\n"; continue; }
    foreach (['php', 'apache/bin', 'mysql/bin'] as $d) copy($src, "$destino/$d/$dll");
}

// 6. Scripts de instalación
foreach (['configurar.php', 'abrir.vbs', 'icono.ico'] as $f) copy(__DIR__ . "/$f", "$destino/$f");
foreach (['logs', 'respaldos', 'datos'] as $d) @mkdir("$destino/$d", 0777, true);

$total = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($destino, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) $total += $f->getSize();
echo "Paquete listo en $destino (" . $mb($total) . " sin comprimir)\n";
