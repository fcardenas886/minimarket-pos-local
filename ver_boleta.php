<?php
// Entrega el PDF de una boleta electrónica guardada (storage/boletas), solo a usuarios con sesión.
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$id = (int)($_GET['dte'] ?? 0);
$archivo = __DIR__ . "/storage/boletas/boleta_$id.pdf";
if ($id <= 0 || !is_file($archivo)) {
    http_response_code(404);
    exit('Boleta no encontrada.');
}
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="boleta_' . $id . '.pdf"');
header('Content-Length: ' . filesize($archivo));
readfile($archivo);
