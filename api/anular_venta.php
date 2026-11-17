<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (empty($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}
verifyCsrfApi();

$user = currentUser();
$input = json_decode(file_get_contents('php://input'), true);

$ventaID = (int)($input['venta_id'] ?? 0);
$motivo = trim($input['motivo'] ?? 'Anulación desde sistema web');
$supervisorPass = trim($input['supervisor_pass'] ?? '');

try {
    $pdo = getDB();

    // Si el usuario no es Admin/Supervisor, requerir la clave de un supervisor
    if ($user['rol'] === 'Cajero') {
        if (empty($supervisorPass)) {
            echo json_encode(['success' => false, 'error' => 'Se requiere la clave de un Administrador o Supervisor para anular ventas.']);
            exit;
        }

        $autorizado = verificarClaveSupervisor($pdo, (string)$supervisorPass) !== null;

        if (!$autorizado) {
            echo json_encode(['success' => false, 'error' => 'Clave de supervisor incorrecta.']);
            exit;
        }
    }

    // Iniciar transacción para bloqueo y reversión consistente
    $pdo->beginTransaction();

    // Verificar venta con bloqueo FOR UPDATE
    $stmtV = $pdo->prepare("SELECT * FROM ventas WHERE VentaID = :vid FOR UPDATE");
    $stmtV->execute([':vid' => $ventaID]);
    $venta = $stmtV->fetch();

    if (!$venta) {
        throw new Exception("La venta #$ventaID no existe.");
    }

    if ($venta['Estado'] === 'Anulada') {
        throw new Exception("La venta #$ventaID ya fue anulada previamente.");
    }

    // Si ya se registró una devolución sobre esta venta, anularla completa duplicaría
    // la reposición de stock (la devolución ya repuso lo suyo) y dejaría un vale/egreso
    // de caja huérfano sin la venta que le dio origen.
    $stmtDevPrevia = $pdo->prepare("SELECT COUNT(*) FROM devoluciones WHERE VentaID = :vid");
    $stmtDevPrevia->execute([':vid' => $ventaID]);
    if ((int)$stmtDevPrevia->fetchColumn() > 0) {
        throw new Exception("La venta #$ventaID ya tiene una devolución registrada; no se puede anular completa (repondría stock por duplicado). Revisa el historial de devoluciones de esta venta.");
    }

    // Si la venta fue a crédito (fiado) y ya tiene abonos asociados, no anular directamente
    $stmtAbonosPrevia = $pdo->prepare("SELECT COUNT(*) FROM abonoscredito WHERE VentaID = :vid");
    $stmtAbonosPrevia->execute([':vid' => $ventaID]);
    if ((int)$stmtAbonosPrevia->fetchColumn() > 0) {
        throw new Exception("La venta #$ventaID tiene abonos de pago de crédito registrados; no se puede anular directamente. Revisa la cuenta del cliente.");
    }

    // 1. Cambiar estado de la venta
    $stmtUpdV = $pdo->prepare("UPDATE ventas SET Estado = 'Anulada' WHERE VentaID = :vid");
    $stmtUpdV->execute([':vid' => $ventaID]);

    // 2. Obtener detalles de la venta para devolver el stock
    $stmtD = $pdo->prepare("
        SELECT dv.ProductoID, dv.Cantidad * GREATEST(dv.FactorConversion, 1) AS Cantidad, dv.PrecioUnitario, p.ControlaStock
        FROM detalleventas dv JOIN productos p ON p.ProductoID = dv.ProductoID
        WHERE dv.VentaID = :vid
    ");
    $stmtD->execute([':vid' => $ventaID]);
    $detalles = $stmtD->fetchAll();

    $stmtUpdStock = $pdo->prepare("UPDATE productos SET Stock = Stock + :cant WHERE ProductoID = :pid");
    $stmtKardex = $pdo->prepare("
        INSERT INTO kardex (ProductoID, TipoTransaccion, VentaID, CantidadEntrada, StockSaldo, ValorUnitario)
        SELECT :pid, 'ANULACION_VENTA', :vid, :cant, (Stock + :cant2), :val FROM productos WHERE ProductoID = :pid2
    ");

    foreach ($detalles as $d) {
        if (!$d['ControlaStock']) continue; // platos/servicios: no tienen stock que reponer
        $stmtKardex->execute([
            ':pid' => $d['ProductoID'],
            ':pid2' => $d['ProductoID'],
            ':vid' => $ventaID,
            ':cant' => $d['Cantidad'],
            ':cant2' => $d['Cantidad'],
            ':val' => $d['PrecioUnitario']
        ]);
        $stmtUpdStock->execute([':cant' => $d['Cantidad'], ':pid' => $d['ProductoID']]);
    }

    // 3. Revertir efectos financieros de los medios de pago (crédito fiado, puntos, vales)
    $stmtPagos = $pdo->prepare("SELECT MetodoPago, Monto FROM pagosventa WHERE VentaID = :vid");
    $stmtPagos->execute([':vid' => $ventaID]);
    $pagosRealizados = $stmtPagos->fetchAll();

    foreach ($pagosRealizados as $pago) {
        $metodo = $pago['MetodoPago'];
        $montoPago = (int)$pago['Monto'];

        if (in_array($metodo, ['Credito Interno', 'Credito', 'Fiado'], true) && !empty($venta['ClienteID'])) {
            // Descontar la deuda generada en la cuenta del cliente
            $pdo->prepare("UPDATE clientes SET SaldoDeudor = GREATEST(0, COALESCE(SaldoDeudor, 0) - :monto) WHERE ClienteID = :cid")
                ->execute([':monto' => $montoPago, ':cid' => $venta['ClienteID']]);
        } elseif ($metodo === 'Puntos' && !empty($venta['ClienteID'])) {
            // Devolver los puntos canjeados al cliente
            $pdo->prepare("UPDATE clientes SET PuntosAcumulados = COALESCE(PuntosAcumulados, 0) + :pts WHERE ClienteID = :cid")
                ->execute([':pts' => $montoPago, ':cid' => $venta['ClienteID']]);
        } elseif ($metodo === 'Vale Devolucion') {
            // Restaurar saldo disponible del vale y volver a activarlo
            $stCanjes = $pdo->prepare("SELECT ValeID, Monto FROM valescanjes WHERE VentaID = :vid");
            $stCanjes->execute([':vid' => $ventaID]);
            foreach ($stCanjes->fetchAll() as $canje) {
                $pdo->prepare("UPDATE valesdevolucion SET MontoDisponible = MontoDisponible + :m, Estado = 'Activo' WHERE ValeID = :val")
                    ->execute([':m' => $canje['Monto'], ':val' => $canje['ValeID']]);
            }
            $pdo->prepare("DELETE FROM valescanjes WHERE VentaID = :vid")->execute([':vid' => $ventaID]);
        }
    }

    // Descontar los puntos acumulados ganados en esta compra
    if (!empty($venta['ClienteID']) && (int)($venta['PuntosGanados'] ?? 0) > 0) {
        $pdo->prepare("UPDATE clientes SET PuntosAcumulados = GREATEST(0, COALESCE(PuntosAcumulados, 0) - :pts) WHERE ClienteID = :cid")
            ->execute([':pts' => (int)$venta['PuntosGanados'], ':cid' => $venta['ClienteID']]);
    }

    // 3. Registrar en AuditoriaEventos si existe la tabla
    try {
        $stmtAud = $pdo->prepare("
            INSERT INTO auditoriaeventos (UsuarioID, TipoEvento, Descripcion)
            VALUES (:uid, 'ANULACION_VENTA', :desc)
        ");
        $stmtAud->execute([
            ':uid' => $user['id'],
            ':desc' => "Venta #$ventaID anulada por " . $user['nombre'] . ". Motivo: $motivo"
        ]);
    } catch (Exception $eAud) {
        // Si no existe la tabla de auditoría, continuar
    }

    // 4. Boleta electrónica: si aún no se emitía, se cancela; si ya se emitió, avisar.
    $avisoBoleta = '';
    $stBol = $pdo->prepare("SELECT EstadoSii, Folio FROM dte_emitidos WHERE VentaID = :vid AND TipoDocumento = 'Boleta' LIMIT 1");
    $stBol->execute([':vid' => $ventaID]);
    if ($bol = $stBol->fetch()) {
        if ($bol['EstadoSii'] === 'Emitida' || $bol['EstadoSii'] === 'Aceptado') {
            $avisoBoleta = " Ojo: la boleta electrónica N° {$bol['Folio']} ya fue emitida al SII; si corresponde, anúlala con una nota de crédito en el portal del SII.";
        } else {
            require_once __DIR__ . '/../includes/sii/EBoletaCola.php';
            EBoletaCola::anularSiPendiente($pdo, $ventaID);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'mensaje' => "Venta #$ventaID anulada exitosamente y stock devuelto al inventario." . $avisoBoleta]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
