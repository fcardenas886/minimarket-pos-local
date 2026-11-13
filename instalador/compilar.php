<?php
/**
 * Genera el instalador completo en un paso:
 *   php instalador/compilar.php
 *
 * 1. Lee la versión de config/database.php (APP_VERSION): es la única fuente.
 * 2. Arma instalador/paquete/ (preparar.php).
 * 3. Compila con Inno Setup pasando esa versión.
 */
if (PHP_SAPI !== 'cli') exit;

$raiz = dirname(__DIR__);
if (!preg_match("/define\('APP_VERSION',\s*'v?([0-9]+\.[0-9]+\.[0-9]+)'\)/", (string)file_get_contents("$raiz/config/database.php"), $m)) {
    fwrite(STDERR, "No se encontró APP_VERSION en config/database.php\n");
    exit(1);
}
$version = $m[1];

$iscc = getenv('ISCC') ?: 'C:\\Program Files (x86)\\Inno Setup 6\\ISCC.exe';
if (!is_file($iscc)) {
    fwrite(STDERR, "No se encontró Inno Setup en $iscc (instálalo o define la variable ISCC)\n");
    exit(1);
}

echo "== Minimarket POS v$version ==\n";
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/preparar.php'), $c);
if ($c !== 0) exit($c);

echo "Compilando instalador...\n";
passthru('"' . $iscc . '" /Q /DAppVersion=' . $version . ' ' . escapeshellarg(__DIR__ . '/minimarket.iss'), $c);
if ($c !== 0) { fwrite(STDERR, "Inno Setup terminó con error ($c)\n"); exit($c); }

$exe = __DIR__ . "/salida/MinimarketPOS-Setup-$version.exe";
printf("Listo: %s (%.1f MB)\n", $exe, filesize($exe) / 1048576);
