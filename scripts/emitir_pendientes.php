<?php
/**
 * Envía al SII las boletas que quedaron pendientes (sin internet, SII caído, etc.).
 * Pensado para correr cada 2 minutos con el Programador de tareas de Windows:
 *
 *   C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe C:\laragon\www\minimarket\scripts\emitir_pendientes.php
 *
 * Usa un bloqueo de archivo para que nunca corran dos a la vez.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../includes/sii/EBoletaCola.php';

$lock = fopen(sys_get_temp_dir() . '/minimarket_emitir_pendientes.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo date('Y-m-d H:i:s') . " Ya hay otro proceso enviando boletas.\n";
    exit(0);
}

$pdo = getDB();
if (!EBoletaCola::activo($pdo)) {
    echo date('Y-m-d H:i:s') . " Boleta electrónica desactivada.\n";
    exit(0);
}

$r = EBoletaCola::procesarPendientes($pdo, 50);
echo date('Y-m-d H:i:s') . " Emitidas: {$r['emitidas']}, siguen pendientes: {$r['pendientes']}, con error: {$r['errores']}"
    . ($r['detenido_por'] ? " | Motivo: {$r['detenido_por']}" : '') . "\n";
flock($lock, LOCK_UN);
