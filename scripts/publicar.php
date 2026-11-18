<?php
/**
 * Automatizador de Publicación de Actualizaciones a GitHub
 *
 * Ejecuta en un solo paso:
 *   1. Compilación del instalador Inno Setup (.exe) y version.json
 *   2. Copia de version.json a la raíz
 *   3. Commit y Push a GitHub (rama master)
 *   4. Creación de la Release en GitHub y subida del instalador .exe
 *
 * Uso:
 *   php scripts/publicar.php
 */
if (PHP_SAPI !== 'cli') exit;

$raiz = dirname(__DIR__);
require_once "$raiz/config/database.php";

$version = ltrim(APP_VERSION, 'v');
echo "=======================================================\n";
echo "   PUBLICACIÓN AUTOMÁTICA DE MINIMARKET POS v$version\n";
echo "=======================================================\n\n";

// 1. Compilar paquete e instalador .exe
echo "1. Compilando instalador Inno Setup...\n";
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$raiz/instalador/compilar.php"), $c);
if ($c !== 0) {
    fwrite(STDERR, "Error al compilar el instalador.\n");
    exit($c);
}

// 2. Copiar version.json a la raíz
echo "\n2. Sincronizando version.json en la raíz del proyecto...\n";
copy("$raiz/instalador/salida/version.json", "$raiz/version.json");

// 3. Git Add, Commit y Push
echo "\n3. Enviando código a GitHub (origin/master)...\n";
passthru("git -C \"$raiz\" add -A", $c);
$mensajeCommit = "v$version: lanzamiento oficial de actualización remota";
passthru("git -C \"$raiz\" commit -m " . escapeshellarg($mensajeCommit), $c);
passthru("git -C \"$raiz\" push origin master", $c);
if ($c !== 0) {
    fwrite(STDERR, "Aviso: Error o sin cambios en git push.\n");
}

// 4. Crear Release en GitHub usando GitHub CLI (gh)
echo "\n4. Creando Release oficial en GitHub y subiendo .exe...\n";
$exePath = "$raiz/instalador/salida/MinimarketPOS-Setup-$version.exe";
$tag = "v$version";
$titulo = "Minimarket POS v$version";

$novedades = APP_CHANGELOG["v$version"] ?? APP_CHANGELOG[$version] ?? [];
$notas = "## Novedades de la versión v$version\n\n";
foreach ($novedades as $item) {
    $notas .= "* $item\n";
}

$notasFile = sys_get_temp_dir() . "/release_notes_v$version.txt";
file_put_contents($notasFile, $notas);

$cmdRelease = sprintf(
    'gh release create %s %s --title %s --notes-file %s -R fcardenas886/minimarket-pos-local',
    escapeshellarg($tag),
    escapeshellarg($exePath),
    escapeshellarg($titulo),
    escapeshellarg($notasFile)
);

passthru($cmdRelease, $c);
@unlink($notasFile);

if ($c === 0) {
    echo "\n=======================================================\n";
    echo " ¡PUBLICACIÓN COMPLETADA EXITOSAMENTE EN GITHUB!\n";
    echo " Release: https://github.com/fcardenas886/minimarket-pos-local/releases/tag/v$version\n";
    echo "=======================================================\n";
    echo "A partir de este momento, cualquier cliente que abra el POS\n";
    echo "recibirá la notificación y podrá actualizarse en 1 clic.\n\n";
} else {
    echo "\nNota: Si la release ya existía, puedes subir el nuevo archivo con:\n";
    echo "  gh release upload v$version \"$exePath\" --clobber\n";
}
