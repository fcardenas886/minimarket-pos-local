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
$sizeMb = round(filesize($exe) / 1048576, 1);
$sha256 = hash_file('sha256', $exe);
printf("Listo: %s (%.1f MB)\n", $exe, $sizeMb);

// Cargar changelog para el manifiesto remoto version.json
require_once "$raiz/config/database.php";
$novedades = APP_CHANGELOG["v$version"] ?? APP_CHANGELOG[$version] ?? [
    "Actualización de sistema v$version",
    "Mejoras de rendimiento y estabilidad"
];

$manifest = [
    'version'    => "v$version",
    'fecha'      => date('Y-m-d'),
    'archivo'    => "MinimarketPOS-Setup-$version.exe",
    'tamano_mb'  => $sizeMb,
    'sha256'     => $sha256,
    'url'        => "https://github.com/fcardenas886/minimarket-pos-local/releases/download/v$version/MinimarketPOS-Setup-$version.exe",
    'obligatoria'=> false,
    'novedades'  => $novedades
];

$jsonPath = __DIR__ . '/salida/version.json';
file_put_contents($jsonPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "\n=======================================================\n";
echo "== Manifiesto de actualización generado con éxito:   ==\n";
echo "   $jsonPath\n";
echo "=======================================================\n";
echo "Para enviar la actualización a tus clientes de forma remota:\n";
echo " 1. Sube 'MinimarketPOS-Setup-$version.exe' a tu servidor web, VPS o GitHub Releases.\n";
echo " 2. En 'salida/version.json', edita el campo 'url' con el enlace directo al .exe subido.\n";
echo " 3. Sube 'version.json' a tu servidor (la URL que configuraste en el sistema de tus clientes).\n";
echo "\n ¡Listo! Cuando los clientes abran Minimarket POS, les saldrá el aviso con las novedades\n";
echo " y el botón 'Actualizar Ahora', aplicándose todo de manera 100% automática.\n\n";
