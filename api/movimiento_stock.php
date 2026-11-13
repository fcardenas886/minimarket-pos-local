<?php
/**
 * Stock simple (todos los planes): ingreso de mercadería, merma/salida y ajuste a conteo
 * de un producto, más su historial de movimientos.
 *
 * Usa las mismas tablas que los Ajustes de Stock (ajustesstock, detalleajustesstock, kardex),
 * así que en el plan Comercio estos movimientos aparecen también en el Kardex.
 *
 * GET  ?producto_id=N            -> últimos 30 movimientos del producto
 * POST {producto_id, tipo: ingreso|salida|conteo, cantidad, costo?, motivo?, observacion?}
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$responder = function (array $data, int $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
};

if (empty($_SESSION['usuario'])) {
    $responder(['success' => false, 'error' => 'Sesión expirada. Inicia sesión nuevamente.'], 401);
}
$user = currentUser();
if (!in_array($user['rol'], ['Administrador', 'Supervisor'], true)) {
    $responder(['success' => false, 'error' => 'Solo un administrador puede modificar el stock.'], 403);
}

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pid = (int)($_GET['producto_id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT k.FechaMovimiento, k.TipoTransaccion, k.CantidadEntrada, k.CantidadSalida, k.StockSaldo,
               a.Motivo, a.Observaciones, u.Nombre AS Usuario
        FROM kardex k
        LEFT JOIN ajustesstock a ON k.AjusteStockID = a.AjusteStockID
        LEFT JOIN usuarios u ON a.UsuarioID = u.UsuarioID
        WHERE k.ProductoID = :pid
        ORDER BY k.KardexID DESC
        LIMIT 30
    ");
    $stmt->execute([':pid' => $pid]);
    $responder(['success' => true, 'movimientos' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $responder(['success' => false, 'error' => 'Método no permitido.'], 405);
}
verifyCsrfApi();

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$productoID = (int)($in['producto_id'] ?? 0);
$tipo = $in['tipo'] ?? '';
$cantidad = round((float)($in['cantidad'] ?? 0), 3);
$costo = isset($in['costo']) && $in['costo'] !== '' ? (int)$in['costo'] : null;
$motivo = trim((string)($in['motivo'] ?? ''));
$observacion = trim((string)($in['observacion'] ?? '')) ?: null;

if (!in_array($tipo, ['ingreso', 'salida', 'conteo'], true)) {
    $responder(['success' => false, 'error' => 'Tipo de movimiento inválido.'], 400);
}
if ($tipo === 'conteo' ? $cantidad < 0 : $cantidad <= 0) {
    $responder(['success' => false, 'error' => $tipo === 'conteo' ? 'El stock contado no puede ser negativo.' : 'La cantidad debe ser mayor a cero.'], 400);
}
if ($costo !== null && $costo < 0) {
    $responder(['success' => false, 'error' => 'El costo no puede ser negativo.'], 400);
}

try {
    $pdo->beginTransaction();

    $stmtP = $pdo->prepare("SELECT ProductoID, Nombre, Stock, CostoCompra, EsPesable FROM productos WHERE ProductoID = :pid FOR UPDATE");
    $stmtP->execute([':pid' => $productoID]);
    $prod = $stmtP->fetch();
    if (!$prod) {
        throw new Exception('El producto no existe.');
    }
    if (!$prod['EsPesable'] && floor($cantidad) != $cantidad) {
        throw new Exception('Este producto se vende por unidad: la cantidad debe ser un número entero.');
    }

    $stockActual = (float)$prod['Stock'];
    if ($tipo === 'ingreso') {
        $delta = $cantidad;
        $motivoTxt = 'Ingreso de mercadería';
    } elseif ($tipo === 'salida') {
        if ($cantidad > $stockActual) {
            throw new Exception("No puedes sacar $cantidad: el stock actual es $stockActual.");
        }
        $delta = -$cantidad;
        $motivoTxt = $motivo !== '' ? mb_substr($motivo, 0, 100) : 'Merma';
    } else {
        $delta = round($cantidad - $stockActual, 3);
        if ($delta == 0) {
            throw new Exception('El stock contado es igual al registrado: no hay diferencia que ajustar.');
        }
        $motivoTxt = 'Ajuste a conteo físico';
    }

    $stmtA = $pdo->prepare("INSERT INTO ajustesstock (UsuarioID, Motivo, Observaciones) VALUES (:uid, :motivo, :obs)");
    $stmtA->execute([':uid' => $user['id'], ':motivo' => $motivoTxt, ':obs' => $observacion]);
    $ajusteID = (int)$pdo->lastInsertId();

    $entrada = $delta > 0;
    $pdo->prepare("INSERT INTO detalleajustesstock (AjusteStockID, ProductoID, Cantidad, TipoMovimiento) VALUES (:aid, :pid, :cant, :tipo)")
        ->execute([':aid' => $ajusteID, ':pid' => $productoID, ':cant' => abs($delta), ':tipo' => $entrada ? 'ENTRADA' : 'SALIDA']);

    $nuevoCosto = ($tipo === 'ingreso' && $costo !== null) ? $costo : (int)$prod['CostoCompra'];
    $pdo->prepare("UPDATE productos SET Stock = Stock + :delta, CostoCompra = :costo WHERE ProductoID = :pid")
        ->execute([':delta' => $delta, ':costo' => $nuevoCosto, ':pid' => $productoID]);

    $col = $entrada ? 'CantidadEntrada' : 'CantidadSalida';
    $pdo->prepare("
        INSERT INTO kardex (ProductoID, TipoTransaccion, AjusteStockID, $col, StockSaldo, ValorUnitario)
        VALUES (:pid, :tipo, :aid, :cant, :saldo, :valor)
    ")->execute([
        ':pid' => $productoID,
        ':tipo' => $entrada ? 'AJUSTE_ENTRADA' : 'AJUSTE_SALIDA',
        ':aid' => $ajusteID,
        ':cant' => abs($delta),
        ':saldo' => round($stockActual + $delta, 3),
        ':valor' => $nuevoCosto,
    ]);

    $pdo->commit();
    $responder(['success' => true, 'stock' => round($stockActual + $delta, 3), 'diferencia' => $delta]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $responder(['success' => false, 'error' => $e->getMessage()], 400);
}
