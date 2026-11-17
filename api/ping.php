<?php
/**
 * Endpoint de verificación de estado y versión del sistema.
 * Utilizado por el actualizador automático para detectar cuándo los servicios
 * se reiniciaron tras aplicar una actualización.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../config/database.php';

echo json_encode([
    'ok' => true,
    'version' => defined('APP_VERSION') ? APP_VERSION : 'unknown',
    'timestamp' => time()
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
