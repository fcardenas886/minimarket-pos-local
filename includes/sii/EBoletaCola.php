<?php
/**
 * Cola de boletas electrónicas (e-Boleta) sobre la tabla dte_emitidos.
 *
 * Estados (EstadoSii):
 *   Pendiente : guardada, falta enviarla (sin internet, SII caído, sin licencia...)
 *   Enviando  : un proceso la está enviando ahora
 *   Emitida   : el SII entregó folio
 *   Revisar   : se envió pero no hubo respuesta clara: PUDO haberse emitido.
 *               No se reintenta sola para no duplicarla; hay que mirar el portal del SII.
 *   Error     : el SII rechazó los datos
 *   Anulada   : la venta se anuló antes de emitir la boleta
 *
 * Proveedores (configuración DTE_PROVEEDOR):
 *   eboleta            : emisión real vía eboleta.sii.cl
 *   eboleta_simulador  : no llama al SII; entrega folios y PDF de prueba
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../licencia.php';
require_once __DIR__ . '/EBoletaCliente.php';

class EBoletaCola {
    const PROVEEDORES = ['eboleta', 'eboleta_simulador'];
    const DIR_PDF = __DIR__ . '/../../storage/boletas';
    const MAX_INTENTOS_CONEXION = 500; // los cortes de internet pueden durar horas

    public static function config(PDO $pdo): array {
        $cfg = [];
        $st = $pdo->query("SELECT Clave, Valor FROM configuraciones WHERE Clave IN
            ('DTE_PROVEEDOR', 'EBOLETA_RUT_USUARIO', 'EBOLETA_CLAVE_CIFRADA', 'EBOLETA_SIMULAR_FALLO', 'MINIMARKET_RUT')");
        foreach ($st as $r) $cfg[$r['Clave']] = $r['Valor'];
        return $cfg;
    }

    public static function activo(PDO $pdo): bool {
        return in_array(self::config($pdo)['DTE_PROVEEDOR'] ?? '', self::PROVEEDORES, true);
    }

    /** Guarda la boleta de una venta como Pendiente. Devuelve el DteID (o el existente). */
    public static function encolarVenta(PDO $pdo, int $ventaID): int {
        $st = $pdo->prepare("SELECT DteID FROM dte_emitidos WHERE VentaID = :v AND TipoDocumento = 'Boleta' LIMIT 1");
        $st->execute([':v' => $ventaID]);
        if ($id = $st->fetchColumn()) return (int)$id;

        $v = $pdo->prepare("SELECT VentaID, MontoTotal, DescuentoGlobal FROM ventas WHERE VentaID = :v");
        $v->execute([':v' => $ventaID]);
        $venta = $v->fetch();
        if (!$venta) throw new RuntimeException("La venta #$ventaID no existe.");

        $d = $pdo->prepare("
            SELECT COALESCE(NULLIF(dv.NombreItem, ''), p.Nombre) AS Nombre, dv.Cantidad, dv.PrecioUnitario,
                   dv.Descuento, dv.Subtotal, dv.EsAfecto
            FROM detalleventas dv JOIN productos p ON p.ProductoID = dv.ProductoID
            WHERE dv.VentaID = :v ORDER BY dv.DetalleVentaID
        ");
        $d->execute([':v' => $ventaID]);
        $detalle = self::armarDetalle($d->fetchAll(), (int)$venta['DescuentoGlobal'], (int)$venta['MontoTotal']);

        $proveedor = self::config($pdo)['DTE_PROVEEDOR'] ?? '';
        $pdo->prepare("
            INSERT INTO dte_emitidos (VentaID, TipoDocumento, Folio, EstadoSii, Ambiente, RutReceptor, MontoTotal, Payload, FechaEmision)
            VALUES (:v, 'Boleta', NULL, 'Pendiente', :amb, '66666666-6', :total, :payload, NOW())
        ")->execute([
            ':v' => $ventaID,
            ':amb' => $proveedor === 'eboleta' ? 'produccion' : 'simulador',
            ':total' => (int)$venta['MontoTotal'],
            ':payload' => json_encode(['tipo_dte' => 39, 'detalle' => $detalle], JSON_UNESCAPED_UNICODE),
        ]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * Convierte el detalle de la venta en el formato del SII. Reparte el descuento
     * global entre los ítems para que la suma de MontoItem sea igual al total cobrado.
     */
    public static function armarDetalle(array $filas, int $descuentoGlobal, int $total): array {
        $items = [];
        foreach ($filas as $f) {
            $items[] = [
                'nombre' => trim(preg_replace('/[\x00-\x1F]/u', ' ', (string)$f['Nombre'])),
                'qty' => (float)$f['Cantidad'],
                'prc' => (float)$f['PrecioUnitario'],
                'desc' => (int)$f['Descuento'],
                'monto' => (int)$f['Subtotal'],
                'exento' => !(int)$f['EsAfecto'],
            ];
        }
        if (!$items) throw new RuntimeException('La venta no tiene productos.');

        // Repartir el descuento global en proporción al monto de cada ítem.
        $suma = array_sum(array_column($items, 'monto'));
        if ($descuentoGlobal > 0 && $suma > 0) {
            $repartido = 0;
            foreach ($items as $i => $it) {
                $parte = (int)floor($descuentoGlobal * $it['monto'] / $suma);
                $items[$i]['monto'] -= $parte;
                $items[$i]['desc'] += $parte;
                $repartido += $parte;
            }
            $mayor = array_keys(array_column($items, 'monto'), max(array_column($items, 'monto')))[0];
            $items[$mayor]['monto'] -= ($descuentoGlobal - $repartido);
            $items[$mayor]['desc'] += ($descuentoGlobal - $repartido);
        }
        // Cualquier diferencia de redondeo con el total cobrado se ajusta en el ítem mayor.
        $dif = $total - array_sum(array_column($items, 'monto'));
        if ($dif !== 0) {
            $mayor = array_keys(array_column($items, 'monto'), max(array_column($items, 'monto')))[0];
            $items[$mayor]['monto'] += $dif;
            $items[$mayor]['desc'] = max(0, $items[$mayor]['desc'] - $dif);
        }

        $salida = [];
        foreach ($items as $it) {
            $qty = $it['qty'] > 0 ? $it['qty'] : 1;
            $prc = $it['prc'];
            // El SII exige MontoItem = Cantidad x Precio - Descuento: si no calza (pesables, packs), se ajusta el precio.
            if (abs($qty * $prc - $it['desc'] - $it['monto']) > 0.5) {
                $prc = round(($it['monto'] + $it['desc']) / $qty, 4);
            }
            $linea = [
                'NmbItem' => mb_substr($it['nombre'] !== '' ? $it['nombre'] : 'Producto', 0, 80),
                'QtyItem' => $qty,
                'PrcItem' => $prc,
            ];
            if ($it['desc'] > 0) $linea['DescuentoMonto'] = $it['desc'];
            if ($it['exento']) $linea['IndExe'] = 1;
            $linea['MontoItem'] = $it['monto'];
            $salida[] = $linea;
        }
        return $salida;
    }

    /**
     * Intenta emitir una boleta de la cola. Devuelve el estado final:
     * ['estado', 'folio', 'pdf_url', 'error', 'tipo_error']
     */
    public static function procesar(PDO $pdo, int $dteID, int $timeoutSegundos = 30): array {
        // Tomar la boleta de forma atómica (evita que la venta y el proceso de fondo la envíen a la vez).
        $tomar = $pdo->prepare("
            UPDATE dte_emitidos SET EstadoSii = 'Enviando', UltimoIntento = NOW(), Intentos = Intentos + 1
            WHERE DteID = :id AND (EstadoSii = 'Pendiente' OR (EstadoSii = 'Enviando' AND UltimoIntento < NOW() - INTERVAL 5 MINUTE))
        ");
        $tomar->execute([':id' => $dteID]);
        if ($tomar->rowCount() === 0) {
            return self::estado($pdo, $dteID);
        }

        $st = $pdo->prepare("SELECT * FROM dte_emitidos WHERE DteID = :id");
        $st->execute([':id' => $dteID]);
        $dte = $st->fetch();
        $cfg = self::config($pdo);
        $proveedor = $cfg['DTE_PROVEEDOR'] ?? '';

        try {
            if (!in_array($proveedor, self::PROVEEDORES, true)) {
                throw new EBoletaError('La boleta electrónica está desactivada en Configuración.', 'conexion');
            }
            if (!licencia_estado()['valida']) {
                throw new EBoletaError('Sin licencia válida: la boleta queda pendiente hasta instalarla.', 'conexion');
            }
            $boleta = json_decode((string)$dte['Payload'], true);
            $boleta['rut_emisor'] = $cfg['MINIMARKET_RUT'] ?? '';

            if ($proveedor === 'eboleta_simulador') {
                $resp = self::simular($pdo, $cfg, $dteID);
            } else {
                if (empty($cfg['EBOLETA_RUT_USUARIO']) || empty($cfg['EBOLETA_CLAVE_CIFRADA'])) {
                    throw new EBoletaError('Falta configurar el RUT y la Clave Tributaria del SII.', 'credenciales');
                }
                $cliente = new EBoletaCliente($cfg['EBOLETA_RUT_USUARIO'], Cifrado::descifrar($cfg['EBOLETA_CLAVE_CIFRADA']), $timeoutSegundos);
                $resp = $cliente->emitirBoleta($boleta);
            }

            $folio = (int)$resp['folio'];
            $pdfUrl = $resp['pdf_public_url'] ?? null;
            if (!empty($resp['b64encoded_pdf'])) {
                if (!is_dir(self::DIR_PDF)) mkdir(self::DIR_PDF, 0775, true);
                file_put_contents(self::DIR_PDF . "/boleta_$dteID.pdf", base64_decode($resp['b64encoded_pdf']));
                $pdfUrl = "ver_boleta.php?dte=$dteID";
            }
            $pdo->prepare("
                UPDATE dte_emitidos SET EstadoSii = 'Emitida', Folio = :f, PdfUrl = :pdf, Mensaje = NULL, FechaEmision = NOW()
                WHERE DteID = :id
            ")->execute([':f' => $folio, ':pdf' => $pdfUrl, ':id' => $dteID]);
            $pdo->prepare("UPDATE ventas SET Folio = :f, TipoDte = 39, DtePdfPath = :pdf WHERE VentaID = :v")
                ->execute([':f' => $folio, ':pdf' => $pdfUrl, ':v' => $dte['VentaID']]);
            return ['estado' => 'Emitida', 'folio' => $folio, 'pdf_url' => $pdfUrl, 'error' => null, 'tipo_error' => null];
        } catch (Throwable $e) {
            $tipo = $e instanceof EBoletaError ? $e->tipo : 'conexion';
            $estado = [
                'conexion' => ((int)$dte['Intentos'] >= self::MAX_INTENTOS_CONEXION) ? 'Error' : 'Pendiente',
                'credenciales' => 'Pendiente',
                'incierto' => 'Revisar',
                'rechazo' => 'Error',
            ][$tipo] ?? 'Pendiente';
            $pdo->prepare("UPDATE dte_emitidos SET EstadoSii = :e, Mensaje = :m WHERE DteID = :id")
                ->execute([':e' => $estado, ':m' => mb_substr($e->getMessage(), 0, 1000), ':id' => $dteID]);
            return ['estado' => $estado, 'folio' => null, 'pdf_url' => null, 'error' => $e->getMessage(), 'tipo_error' => $tipo];
        }
    }

    /** Emite las pendientes en orden de venta. Se detiene ante un problema general (sin internet, clave mala). */
    public static function procesarPendientes(PDO $pdo, int $limite = 50): array {
        $ids = $pdo->query("
            SELECT DteID FROM dte_emitidos
            WHERE TipoDocumento = 'Boleta' AND (EstadoSii = 'Pendiente' OR (EstadoSii = 'Enviando' AND UltimoIntento < NOW() - INTERVAL 5 MINUTE))
            ORDER BY DteID ASC LIMIT " . (int)$limite
        )->fetchAll(PDO::FETCH_COLUMN);
        $res = ['emitidas' => 0, 'pendientes' => 0, 'errores' => 0, 'detenido_por' => null];
        foreach ($ids as $id) {
            $r = self::procesar($pdo, (int)$id);
            if ($r['estado'] === 'Emitida') {
                $res['emitidas']++;
            } elseif ($r['estado'] === 'Pendiente') {
                $res['pendientes']++;
                $res['detenido_por'] = $r['error'];
                break; // problema general: no tiene sentido seguir intentando las demás ahora
            } else {
                $res['errores']++;
            }
        }
        return $res;
    }

    /** Vuelve a poner en cola una boleta en Error o Revisar (acción manual del administrador). */
    public static function reencolar(PDO $pdo, int $dteID): bool {
        $st = $pdo->prepare("UPDATE dte_emitidos SET EstadoSii = 'Pendiente' WHERE DteID = :id AND EstadoSii IN ('Error', 'Revisar')");
        $st->execute([':id' => $dteID]);
        return $st->rowCount() > 0;
    }

    /** Al anular una venta: si su boleta no se había emitido, ya no se emite. */
    public static function anularSiPendiente(PDO $pdo, int $ventaID): void {
        $pdo->prepare("
            UPDATE dte_emitidos SET EstadoSii = 'Anulada', Mensaje = 'Venta anulada antes de emitir la boleta'
            WHERE VentaID = :v AND TipoDocumento = 'Boleta' AND EstadoSii IN ('Pendiente', 'Error', 'Revisar')
        ")->execute([':v' => $ventaID]);
    }

    public static function resumen(PDO $pdo): array {
        $r = ['Pendiente' => 0, 'Revisar' => 0, 'Error' => 0];
        foreach ($pdo->query("SELECT EstadoSii, COUNT(*) c FROM dte_emitidos WHERE TipoDocumento = 'Boleta' AND EstadoSii IN ('Pendiente','Enviando','Revisar','Error') GROUP BY EstadoSii") as $f) {
            $k = $f['EstadoSii'] === 'Enviando' ? 'Pendiente' : $f['EstadoSii'];
            $r[$k] += (int)$f['c'];
        }
        return $r;
    }

    public static function estado(PDO $pdo, int $dteID): array {
        $st = $pdo->prepare("SELECT EstadoSii, Folio, PdfUrl, Mensaje FROM dte_emitidos WHERE DteID = :id");
        $st->execute([':id' => $dteID]);
        $d = $st->fetch() ?: [];
        return ['estado' => $d['EstadoSii'] ?? null, 'folio' => $d['Folio'] ?? null, 'pdf_url' => $d['PdfUrl'] ?? null,
                'error' => $d['Mensaje'] ?? null, 'tipo_error' => null];
    }

    // ------------------------------------------------------------------
    // Simulador: mismo flujo sin llamar al SII
    // ------------------------------------------------------------------

    private static function simular(PDO $pdo, array $cfg, int $dteID): array {
        $fallo = $cfg['EBOLETA_SIMULAR_FALLO'] ?? 'NO';
        if ($fallo === 'conexion') throw new EBoletaError('Simulador: sin conexión con el SII.', 'conexion');
        if ($fallo === 'rechazo') throw new EBoletaError('Simulador: el SII rechazó la boleta.', 'rechazo');
        if ($fallo === 'incierto') throw new EBoletaError('Simulador: el SII no respondió a tiempo.', 'incierto');
        $folio = 1 + (int)$pdo->query("SELECT COALESCE(MAX(Folio), 0) FROM dte_emitidos WHERE Ambiente = 'simulador' AND EstadoSii = 'Emitida'")->fetchColumn();
        return ['folio' => $folio, 'pdf_public_url' => null, 'b64encoded_pdf' => base64_encode(self::pdfSimulado($folio))];
    }

    /** PDF mínimo válido con el texto "BOLETA DE PRUEBA" y el folio. */
    private static function pdfSimulado(int $folio): string {
        $texto = "BT /F1 14 Tf 20 120 Td (BOLETA DE PRUEBA - SIMULADOR) Tj 0 -24 Td (Folio $folio - sin validez tributaria) Tj ET";
        $objs = [
            "<< /Type /Catalog /Pages 2 0 R >>",
            "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 200] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>",
            "<< /Length " . strlen($texto) . " >>\nstream\n$texto\nendstream",
            "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $i => $o) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n$o\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $off) $pdf .= sprintf("%010d 00000 n \n", $off);
        return $pdf . "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    }
}
