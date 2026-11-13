<?php
// Vuelve a enviar al SII una boleta en estado Error o Revisar (acción del administrador).
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (empty($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'error' => 'Sesión expirada.']);
    exit;
}
verifyCsrfApi();
if (!in_array(currentUser()['rol'], ['Administrador', 'Supervisor'], true)) {
    echo json_encode(['success' => false, 'error' => 'Solo un administrador puede reenviar boletas.']);
    exit;
}

require_once __DIR__ . '/../includes/sii/EBoletaCola.php';
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$dteID = (int)($in['dte_id'] ?? 0);
$pdo = getDB();

if (!EBoletaCola::reencolar($pdo, $dteID)) {
    echo json_encode(['success' => false, 'error' => 'La boleta no está en estado Error o Revisar.']);
    exit;
}
$r = EBoletaCola::procesar($pdo, $dteID, 20);
echo json_encode([
    'success' => $r['estado'] === 'Emitida',
    'estado' => $r['estado'],
    'folio' => $r['folio'],
    'error' => $r['error'],
], JSON_UNESCAPED_UNICODE);
