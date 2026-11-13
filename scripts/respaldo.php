<?php
/**
 * Respaldo de la base de datos (comprimido .sql.gz). Guarda los últimos 30 días.
 *
 *   php scripts/respaldo.php
 *
 * Usa los datos de .env. Variables opcionales en .env:
 *   MYSQLDUMP_PATH  ruta a mysqldump.exe (si no está en el PATH)
 *   RESPALDO_DIR    carpeta destino (por defecto ../respaldos junto al sistema)
 * El instalador lo programa todos los días a las 23:30.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';

$dias = 30;
$dump = getenv('MYSQLDUMP_PATH') ?: 'mysqldump';
$dir = getenv('RESPALDO_DIR') ?: dirname(__DIR__, 2) . '/respaldos';
if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
    fwrite(STDERR, "No se pudo crear la carpeta de respaldos: $dir\n");
    exit(1);
}

$archivo = $dir . '/' . DB_NAME . '_' . date('Y-m-d_His') . '.sql.gz';
$cmd = sprintf('"%s" --host=%s --port=%s --user=%s --single-transaction --routines --triggers --no-tablespaces --default-character-set=utf8mb4 %s',
    $dump, DB_HOST, DB_PORT, DB_USER, DB_NAME);

// La contraseña va por variable de entorno, no en la línea de comandos.
$env = array_merge(getenv(), ['MYSQL_PWD' => DB_PASS]);
$proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
if (!is_resource($proc)) {
    fwrite(STDERR, "No se pudo ejecutar mysqldump ($dump)\n");
    exit(1);
}
$gz = gzopen($archivo, 'wb6');
while (!feof($pipes[1])) {
    $bloque = fread($pipes[1], 1 << 16);
    if ($bloque !== false && $bloque !== '') gzwrite($gz, $bloque);
}
gzclose($gz);
$errores = stream_get_contents($pipes[2]);
$codigo = proc_close($proc);

if ($codigo !== 0 || filesize($archivo) < 200) {
    @unlink($archivo);
    fwrite(STDERR, date('Y-m-d H:i:s') . " Respaldo FALLÓ: " . trim($errores) . "\n");
    exit(1);
}

// Borrar respaldos antiguos.
$borrados = 0;
foreach (glob($dir . '/' . DB_NAME . '_*.sql.gz') as $f) {
    if (filemtime($f) < time() - $dias * 86400) { unlink($f); $borrados++; }
}
echo date('Y-m-d H:i:s') . ' Respaldo OK: ' . basename($archivo) . ' (' . round(filesize($archivo) / 1024) . " KB)"
    . ($borrados ? ", $borrados antiguos borrados" : '') . "\n";
