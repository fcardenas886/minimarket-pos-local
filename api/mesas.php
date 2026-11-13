<?php
/**
 * API del módulo "Mesas y garzones".
 *
 * Sesiones:
 *  - Garzón/Cocina: $_SESSION['garzon'] (entra con PIN desde garzon.php o cocina.php, desde cualquier equipo del local)
 *  - Caja: $_SESSION['usuario'] (login normal, solo desde el PC de caja)
 *
 * GET  ?action=mesas | pedido&mesa_id= | productos | comandas | por_imprimir | para_cobrar&pedido_id=
 * POST ?action=login_pin | logout | enviar | comanda_lista | tomar_para_imprimir | anular_cuenta
 * GET  ?action=reimprimir&pedido_id=  (caja)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

function responder(array $d, int $status = 200): never {
    http_response_code($status);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}
function garzonActual(): ?array { return $_SESSION['garzon'] ?? null; }
function cajaActual(): ?array { return (acceso_es_local() && !empty($_SESSION['usuario'])) ? $_SESSION['usuario'] : null; }
function exigirGarzonOCaja(): array {
    $g = garzonActual() ?? cajaActual();
    if (!$g) responder(['success' => false, 'error' => 'Sesión expirada. Ingresa tu PIN de nuevo.', 'sin_sesion' => true], 401);
    return $g;
}
function exigirCaja(): array {
    $u = cajaActual();
    if (!$u) responder(['success' => false, 'error' => 'Solo disponible en el computador de caja.'], 403);
    return $u;
}
function leerJson(): array { return json_decode(file_get_contents('php://input'), true) ?: []; }

/** Líneas y total de la cuenta abierta de un pedido. */
function detallePedido(PDO $pdo, int $pedidoID): array {
    $st = $pdo->prepare("
        SELECT d.DetalleID, d.ProductoID, p.Nombre, d.Cantidad, d.PrecioUnitario, d.Nota, d.ComandaID,
               c.Fecha AS FechaComanda, c.EstadoCocina, COALESCE(u.Nombre, '') AS Garzon
        FROM mesapedidosdetalle d
        JOIN productos p ON p.ProductoID = d.ProductoID
        JOIN comandas c ON c.ComandaID = d.ComandaID
        LEFT JOIN usuarios u ON u.UsuarioID = c.UsuarioID
        WHERE d.PedidoID = :p AND d.Anulado = 0
        ORDER BY d.DetalleID
    ");
    $st->execute([':p' => $pedidoID]);
    $lineas = $st->fetchAll();
    $total = 0;
    foreach ($lineas as &$l) {
        $l['Subtotal'] = (int)round((float)$l['Cantidad'] * (int)$l['PrecioUnitario']);
        $total += $l['Subtotal'];
    }
    return ['lineas' => $lineas, 'total' => $total];
}

/** Comandas con sus líneas y la mesa (para cocina e impresión). */
function cargarComandas(PDO $pdo, string $where, array $params = []): array {
    $st = $pdo->prepare("
        SELECT c.ComandaID, c.Fecha, c.EstadoCocina, c.FechaLista, c.Impresa, m.Nombre AS Mesa, m.Zona,
               COALESCE(u.Nombre, '') AS Garzon, c.PedidoID
        FROM comandas c
        JOIN mesapedidos mp ON mp.PedidoID = c.PedidoID
        JOIN mesas m ON m.MesaID = mp.MesaID
        LEFT JOIN usuarios u ON u.UsuarioID = c.UsuarioID
        WHERE $where
        ORDER BY c.ComandaID ASC
        LIMIT 60
    ");
    $st->execute($params);
    $comandas = $st->fetchAll();
    if (!$comandas) return [];
    $ids = implode(',', array_map('intval', array_column($comandas, 'ComandaID')));
    $items = [];
    foreach ($pdo->query("
        SELECT d.ComandaID, p.Nombre, d.Cantidad, d.Nota
        FROM mesapedidosdetalle d JOIN productos p ON p.ProductoID = d.ProductoID
        WHERE d.ComandaID IN ($ids) AND d.Anulado = 0 ORDER BY d.DetalleID
    ") as $r) {
        $items[$r['ComandaID']][] = ['nombre' => $r['Nombre'], 'cantidad' => (float)$r['Cantidad'], 'nota' => $r['Nota']];
    }
    foreach ($comandas as &$c) $c['items'] = $items[$c['ComandaID']] ?? [];
    return $comandas;
}

$action = $_GET['action'] ?? '';
$pdo = getDB();

try {
    // ------------------------------------------------------------------ sesión PIN
    if ($action === 'login_pin') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
        if ($min = loginRateLimitCheck($ip)) {
            responder(['success' => false, 'error' => "Demasiados intentos. Espera $min minuto(s)."], 429);
        }
        $pin = preg_replace('/\D/', '', (string)(leerJson()['pin'] ?? ''));
        if (strlen($pin) < 4) responder(['success' => false, 'error' => 'El PIN tiene al menos 4 dígitos.'], 400);
        foreach ($pdo->query("SELECT UsuarioID, Nombre, PinHash FROM usuarios WHERE Activo = TRUE AND PinHash IS NOT NULL") as $u) {
            if (password_verify($pin, $u['PinHash'])) {
                loginRateLimitClear($ip);
                session_regenerate_id(true);
                $_SESSION['garzon'] = ['id' => (int)$u['UsuarioID'], 'nombre' => $u['Nombre']];
                responder(['success' => true, 'nombre' => $u['Nombre'], 'csrf' => csrfToken()]);
            }
        }
        loginRateLimitRegisterFailure($ip);
        responder(['success' => false, 'error' => 'PIN incorrecto.'], 401);
    }
    if ($action === 'logout') {
        unset($_SESSION['garzon']);
        responder(['success' => true]);
    }

    // ------------------------------------------------------------------ garzón / caja
    if ($action === 'mesas') {
        exigirGarzonOCaja();
        $mesas = $pdo->query("
            SELECT m.MesaID, m.Nombre, m.Zona, mp.PedidoID, mp.FechaApertura,
                   (SELECT COALESCE(SUM(ROUND(d.Cantidad * d.PrecioUnitario)), 0) FROM mesapedidosdetalle d
                     WHERE d.PedidoID = mp.PedidoID AND d.Anulado = 0) AS Total,
                   (SELECT COUNT(*) FROM comandas c WHERE c.PedidoID = mp.PedidoID AND c.EstadoCocina = 'Pendiente') AS EnCocina
            FROM mesas m
            LEFT JOIN mesapedidos mp ON mp.MesaID = m.MesaID AND mp.Estado = 'Abierta'
            WHERE m.Activa = TRUE
            ORDER BY m.Zona, m.Orden, m.MesaID
        ")->fetchAll();
        responder(['success' => true, 'mesas' => $mesas]);
    }

    if ($action === 'pedido') {
        exigirGarzonOCaja();
        $st = $pdo->prepare("SELECT m.MesaID, m.Nombre, m.Zona, mp.PedidoID FROM mesas m
            LEFT JOIN mesapedidos mp ON mp.MesaID = m.MesaID AND mp.Estado = 'Abierta' WHERE m.MesaID = :m");
        $st->execute([':m' => (int)($_GET['mesa_id'] ?? 0)]);
        $mesa = $st->fetch();
        if (!$mesa) responder(['success' => false, 'error' => 'Mesa no encontrada.'], 404);
        $det = $mesa['PedidoID'] ? detallePedido($pdo, (int)$mesa['PedidoID']) : ['lineas' => [], 'total' => 0];
        responder(['success' => true, 'mesa' => $mesa] + $det);
    }

    if ($action === 'productos') {
        exigirGarzonOCaja();
        $productos = $pdo->query("
            SELECT p.ProductoID, p.Nombre, p.PrecioVenta, COALESCE(c.Nombre, 'Otros') AS Categoria
            FROM productos p LEFT JOIN categorias c ON c.CategoriaID = p.CategoriaID
            WHERE p.Activo = TRUE AND p.PrecioVenta > 0
            ORDER BY Categoria, p.Nombre
        ")->fetchAll();
        responder(['success' => true, 'productos' => $productos]);
    }

    if ($action === 'enviar') {
        $quien = exigirGarzonOCaja();
        verifyCsrfApi();
        $in = leerJson();
        $mesaID = (int)($in['mesa_id'] ?? 0);
        $items = array_values(array_filter((array)($in['items'] ?? []), fn($i) => (float)($i['cantidad'] ?? 0) > 0));
        if (!$items) responder(['success' => false, 'error' => 'Agrega al menos un producto.'], 400);
        if (count($items) > 60) responder(['success' => false, 'error' => 'Demasiados productos en un envío.'], 400);

        $pdo->beginTransaction();
        // Bloquear la mesa: dos garzones enviando a la vez quedan en la misma cuenta, sin duplicarla.
        $st = $pdo->prepare("SELECT MesaID, Nombre FROM mesas WHERE MesaID = :m AND Activa = TRUE FOR UPDATE");
        $st->execute([':m' => $mesaID]);
        $mesa = $st->fetch();
        if (!$mesa) throw new Exception('Mesa no encontrada.');

        $st = $pdo->prepare("SELECT PedidoID FROM mesapedidos WHERE MesaID = :m AND Estado = 'Abierta' LIMIT 1");
        $st->execute([':m' => $mesaID]);
        $pedidoID = (int)$st->fetchColumn();
        if (!$pedidoID) {
            $pdo->prepare("INSERT INTO mesapedidos (MesaID, UsuarioApertura) VALUES (:m, :u)")
                ->execute([':m' => $mesaID, ':u' => $quien['id']]);
            $pedidoID = (int)$pdo->lastInsertId();
        }
        $pdo->prepare("INSERT INTO comandas (PedidoID, UsuarioID) VALUES (:p, :u)")
            ->execute([':p' => $pedidoID, ':u' => $quien['id']]);
        $comandaID = (int)$pdo->lastInsertId();

        $prod = $pdo->prepare("SELECT PrecioVenta FROM productos WHERE ProductoID = :id AND Activo = TRUE");
        $ins = $pdo->prepare("INSERT INTO mesapedidosdetalle (PedidoID, ComandaID, ProductoID, Cantidad, PrecioUnitario, Nota)
                              VALUES (:p, :c, :pr, :q, :precio, :nota)");
        foreach ($items as $it) {
            $prod->execute([':id' => (int)$it['producto_id']]);
            $precio = $prod->fetchColumn();
            if ($precio === false) throw new Exception('Un producto del pedido ya no está disponible.');
            $cant = round((float)$it['cantidad'], 3);
            if ($cant <= 0 || $cant > 999) throw new Exception('Cantidad inválida.');
            $nota = trim(mb_substr((string)($it['nota'] ?? ''), 0, 150)) ?: null;
            $ins->execute([':p' => $pedidoID, ':c' => $comandaID, ':pr' => (int)$it['producto_id'], ':q' => $cant, ':precio' => (int)$precio, ':nota' => $nota]);
        }
        $pdo->commit();
        responder(['success' => true, 'pedido_id' => $pedidoID, 'comanda_id' => $comandaID, 'mesa' => $mesa['Nombre']] + detallePedido($pdo, $pedidoID));
    }

    // ------------------------------------------------------------------ cocina
    if ($action === 'comandas') {
        exigirGarzonOCaja();
        $pendientes = cargarComandas($pdo, "c.EstadoCocina = 'Pendiente'");
        $listas = cargarComandas($pdo, "c.EstadoCocina = 'Lista' AND c.FechaLista >= NOW() - INTERVAL 15 MINUTE");
        responder(['success' => true, 'pendientes' => $pendientes, 'listas' => array_slice(array_reverse($listas), 0, 8), 'ahora' => date('Y-m-d H:i:s')]);
    }

    if ($action === 'comanda_lista') {
        exigirGarzonOCaja();
        verifyCsrfApi();
        $in = leerJson();
        $estado = !empty($in['deshacer']) ? 'Pendiente' : 'Lista';
        $pdo->prepare("UPDATE comandas SET EstadoCocina = :e, FechaLista = " . ($estado === 'Lista' ? 'NOW()' : 'NULL') . " WHERE ComandaID = :c")
            ->execute([':e' => $estado, ':c' => (int)($in['comanda_id'] ?? 0)]);
        responder(['success' => true]);
    }

    // ------------------------------------------------------------------ caja
    // Toma las comandas nuevas y las marca como impresas en un solo paso (si hay dos
    // pestañas de caja abiertas, cada comanda la imprime solo una).
    if ($action === 'tomar_para_imprimir') {
        exigirCaja();
        verifyCsrfApi();
        $pdo->beginTransaction();
        $ids = $pdo->query("SELECT ComandaID FROM comandas WHERE Impresa = 0 AND Fecha >= NOW() - INTERVAL 12 HOUR FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
        if ($ids) $pdo->exec('UPDATE comandas SET Impresa = 1 WHERE ComandaID IN (' . implode(',', array_map('intval', $ids)) . ')');
        $pdo->commit();
        responder(['success' => true, 'comandas' => $ids ? cargarComandas($pdo, 'c.ComandaID IN (' . implode(',', array_map('intval', $ids)) . ')') : []]);
    }

    if ($action === 'reimprimir') {
        exigirCaja();
        responder(['success' => true, 'comandas' => cargarComandas($pdo, 'c.PedidoID = :p', [':p' => (int)($_GET['pedido_id'] ?? 0)])]);
    }

    if ($action === 'para_cobrar') {
        exigirCaja();
        $pedidoID = (int)($_GET['pedido_id'] ?? 0);
        $st = $pdo->prepare("SELECT mp.PedidoID, m.Nombre AS Mesa FROM mesapedidos mp JOIN mesas m ON m.MesaID = mp.MesaID
                             WHERE mp.PedidoID = :p AND mp.Estado = 'Abierta'");
        $st->execute([':p' => $pedidoID]);
        $ped = $st->fetch();
        if (!$ped) responder(['success' => false, 'error' => 'La cuenta ya fue cobrada o no existe.'], 404);
        // Agrupar por producto para el carrito de la caja.
        $st = $pdo->prepare("
            SELECT p.ProductoID, p.Nombre, p.CodigoBarras, p.PrecioVenta, p.Stock, p.EsPesable, p.CategoriaID,
                   SUM(d.Cantidad) AS Cantidad
            FROM mesapedidosdetalle d JOIN productos p ON p.ProductoID = d.ProductoID
            WHERE d.PedidoID = :p AND d.Anulado = 0
            GROUP BY p.ProductoID, p.Nombre, p.CodigoBarras, p.PrecioVenta, p.Stock, p.EsPesable, p.CategoriaID
            ORDER BY MIN(d.DetalleID)
        ");
        $st->execute([':p' => $pedidoID]);
        responder(['success' => true, 'pedido' => $ped, 'items' => $st->fetchAll()]);
    }

    if ($action === 'anular_cuenta') {
        $u = exigirCaja();
        verifyCsrfApi();
        if (!in_array($u['rol'], ['Administrador', 'Supervisor'], true)) {
            responder(['success' => false, 'error' => 'Solo un administrador puede anular la cuenta de una mesa.'], 403);
        }
        $st = $pdo->prepare("UPDATE mesapedidos SET Estado = 'Anulada', FechaCierre = NOW() WHERE PedidoID = :p AND Estado = 'Abierta'");
        $st->execute([':p' => (int)(leerJson()['pedido_id'] ?? 0)]);
        responder(['success' => $st->rowCount() > 0, 'error' => $st->rowCount() ? null : 'La cuenta ya no está abierta.']);
    }

    responder(['success' => false, 'error' => 'Acción no válida.'], 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    responder(['success' => false, 'error' => $e->getMessage()], 400);
}
