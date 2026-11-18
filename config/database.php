<?php
// Version de la app: unica fuente de verdad, para que login y footer nunca queden desincronizados.
// El instalador (instalador/compilar.php) toma la versión de aquí: no se escribe en otro lado.
define('APP_VERSION', 'v4.3.0');

// Novedades reales por versión, para la pantalla de bienvenida y el módulo "Acerca de".
// Registra la cronología completa de la evolución del sistema desde su inicio.
define('APP_CHANGELOG', [
    'v4.3.0' => [
        'Centro de Control y Visor de Mesas en Caja POS (F8): consulta rápida de pedidos de mesas, productos en cocina, garzón y tiempos sin vaciar el carrito de caja.',
        'Emisión e Impresión de Pre-Cuenta Térmica (Ley 20.918): ticket no fiscal en 80mm/58mm con consumo, subtotal y 10% sugerido de propina voluntaria.',
        'Calculadora de División de Cuentas: desglose equitativo entre 2 y 20 personas con propina proporcional e impresión de comprobante informativo.',
        'Traslado y Cambio de Mesa: reasignación ágil y segura de pedidos activos a mesas desocupadas desde caja o desde el celular del garzón.',
        'Semáforo Visual de Mesas y Tiempos: tarjetas enriquecidas con código de colores (Libre, Ocupada, En Cocina y Pre-cuenta) con reloj de minutos transcurridos.',
        'Agregar Productos a Mesas desde Caja: permite a los cajeros sumar productos y notas a una comanda abierta sin perder los ítems de su venta actual.',
    ],
    'v4.2.0' => [
        'Ley de Redondeo en Efectivo (Ley N° 20.956): cálculo y desglose automático de diferencias (-$5 a +$4 CLP) en pagos en efectivo en caja POS y comprobantes impresos.',
        'Sistema de Actualizaciones Remotas Automáticas: detección y aplicación silenciosa de nuevas versiones al abrir el programa, sin visitas presenciales.',
        'Soporte completo para productos sin control de stock (servicios, recargas y preparación) con venta ilimitada en terminales POS.',
        'Corrección en auditoría de fiados y cartera de clientes en el centro de reportes.',
        'Anulación segura de ventas con reversión atómica de saldos deudores, puntos acumulados y vales de cambio.',
    ],
    'v4.1.0' => [
        'Propina voluntaria al cobrar: se sugiere un porcentaje (10% por defecto) y el cliente puede dar menos o nada. No va en la boleta ni lleva IVA, y aparece aparte en el comprobante y en el cierre de caja.',
        'Al cobrar una mesa se propone la propina sugerida; en venta directa parte sin propina.',
        'Comprobante de venta: botón "Nueva venta", cierre con la tecla Esc o la X, y los botones ya no quedan fuera de la pantalla en tickets largos.',
        'Mesas y garzones: dirección de conexión correcta en equipos con varias tarjetas de red, códigos QR y pantallas instalables como app en celulares y tablets.',
    ],
    'v4.0.0' => [
        'Edición local con planes Básico y Comercio: licencia de por vida ligada al RUT del negocio, con actualizaciones incluidas por un período.',
        'Boleta electrónica integrada con e-Boleta del SII: se emite al cobrar y, si no hay internet, queda en cola y se envía sola al volver la conexión.',
        'Instalador para Windows: se instala como un programa, con respaldo diario automático y actualizaciones que conservan los datos.',
        'Stock simple en Productos: ingreso de mercadería, mermas y ajuste a conteo, con historial por producto.',
        'Módulo adicional Mesas y garzones: pedidos desde celular o tablet, pantalla de cocina, comandas impresas en caja y códigos QR para conectar los equipos.',
        'Productos sin control de stock para platos preparados, servicios y recargas.',
        'Más seguridad: contraseñas cifradas, sin claves de acceso universales y archivos de configuración protegidos.',
        'Hora de Chile en tickets y boletas, y menús más fáciles de abrir, también en pantallas táctiles.',
    ],
    'v3.3.0' => [
        'Actualizador Rápido de Precios: Nueva pantalla especializada para cambiar precios de venta y costos mediante escaneo continuo de códigos de barra o búsqueda rápida.',
        'Cálculo de Margen Comercial en Tiempo Real: Visualización instantánea del margen de ganancia (%) con alertas de rentabilidad al modificar precios.',
        'Gestión de Packs y Códigos Secundarios: Ajuste directo de precios para Six Packs, Cajas y presentaciones alternativas desde la misma pantalla.',
        'Herramientas Masivas de Ajuste: Aplicación de porcentajes globales (+5%, +10%, etc.) y redondeo comercial chileno a decenas/centenas.',
        'Impresión de Flejes y Etiquetas de Góndola: Generación e impresión directa de etiquetas de estantería para los productos actualizados.',
        'Jerarquía Inteligente de Precios en POS: Armonización automática entre promociones Multibuy (ej. 3x$5.000) y códigos de pack sin precio fijo.',
    ],
    'v3.2.0' => [
        'Consulta de Ventas con Filtros Avanzados: Búsqueda flexible por rango de fechas (con accesos directos: Hoy, Ayer, Últimos 7 Días, Este Mes), N° de venta, Folio DTE, Nombre de Cliente o RUT.',
        'Detalle Completo de Operación: Modal interactivo con inspección profunda de cajero, turno, cliente, desglose de ítems, descuentos, impuestos (Neto / IVA 19%) y medios de pago múltiples/mixtos.',
        'Reimpresión de Ticket Térmico (80mm/58mm): Generación y reimpresión instantánea del comprobante físico con membrete del local, detalle de artículos y pagaré firmado para ventas a crédito (Fiado).',
        'Integración y Descarga de Boleta/Factura Electrónica (DTE): Botón de acceso directo e impresión limpia del PDF oficial tributario emitido ante el SII.',
        'KPIs Ejecutivos en Tiempo Real: Tarjetas con total recaudado en el período, cantidad de operaciones, ticket promedio y monto anulado.',
    ],
    'v3.1.0' => [
        'Centro Integral de Reportes y BI: Módulo analítico unificado para auditar ventas, cartera, compras, rotación de inventario y personal.',
        'Reporte de Ventas y Medios de Pago: Métricas ejecutivas (Bruto, Neto, IVA 19%, Descuentos, Ticket Promedio), medios de pago y mapa de horas peak.',
        'Cartera de Clientes y Fiados: Auditoría de cuentas por cobrar, ranking de clientes con deuda activa, porcentaje de cupo utilizado y flujo de abonos.',
        'Compras y Gastos por Proveedor: Egresos en mercadería, ranking de distribuidores por volumen facturado y detalle de recepciones.',
        'Ranking y Detector de Stock Estancado ("Huesos"): Identificación de artículos líderes y productos sin movimiento con capital inmovilizado.',
        'Valorización de Inventario: Capital total en bodega a costo de adquisición vs. retorno proyectado a precio de venta y margen potencial.',
        'Rendimiento de Cajeros y Cuadraturas: Monitoreo de recaudación por cajero y balance histórico de sobrantes/faltantes en arqueos de turno.',
        'Exportación a Excel (CSV) universal con codificación UTF-8 e impresión limpia sin cabeceras innecesarias.',
    ],
    'v3.0.0' => [
        'Múltiples Códigos de Barra por Producto: Asocia códigos alternativos (packs, latas, cambio de presentación o nuevo EAN) sin duplicar stock.',
        'Búsqueda unificada en POS y Compras: Al escanear cualquiera de los códigos alternativos o el principal se localiza de inmediato el producto unificado.',
        'Bloqueo Rápido de Pantalla de Caja (Lock Screen): Protege la terminal con un clic (🔒) o atajo rápido (Alt + L / F9) con reloj digital en tiempo real.',
        'Desbloqueo seguro por Cajero o Supervisor: Validación por contraseña del cajero titular o clave de supervisor con registro en auditoría de seguridad.',
        'Protección total de ventas en curso: La venta actual, descuentos y clientes seleccionados permanecen intactos durante el bloqueo y recargas.',
    ],
    'v2.9.4' => [
        'Rediseño operativo del POS en 3 modos integrales: Supermercado (Caja Rápida), Táctil y Clásico.',
        'Modo Supermercado: oculta tarjetas de catálogo y despliega en el centro una tabla amplia de venta con escaneo continuo.',
        'Modo Táctil: barra deslizable superior de categorías y tarjetas táctiles optimizadas para touchscreen.',
        'Modo Clásico personalizable: selector en vivo de catálogo para elegir ⭐ Más Vendidos, 🏷️ En Oferta, 📦 Todos (A-Z) o por categoría.',
        'Persistencia de modo favorito y sincronización bidireccional entre la tabla y el carrito en tiempo real.',
    ],
    'v2.9.3' => [
        'Personalización visual de la marca: selector de Modo Claro (Light) y Modo Oscuro (Dark).',
        'Paleta de 5 colores de acento corporativo: Índigo, Verde Minimarket, Naranja, Cyan y Rojo.',
        'Soporte para subir o definir el Logo de la Empresa visible en Navbar, Bienvenida y Login.',
        'Diseño dinámico de grilla POS: selector en vivo entre Modo Táctil, Modo Lista Compacta y Estándar.',
        'Nueva pantalla "Acerca de..." con la línea de tiempo interactiva de toda la historia del sistema.',
    ],
    'v2.9.2' => [
        'Autorización directa de clave de supervisor en el modal de cobro para descuentos especiales.',
        'Flujo ágil con validación inmediata y atajo directo en el teclado.',
    ],
    'v2.9.1' => [
        'Supervisión configurable en caja: autorización para anular venta en proceso y eliminar productos.',
        'Descuento máximo en caja por porcentaje (%) configurable desde Ajustes Generales.',
    ],
    'v2.9.0' => [
        'Nueva pantalla de Cotizaciones: crear presupuestos y cargarlos en la caja para cobrar.',
        'Toma de Inventario físico: contar por categoría, ver diferencias y ajustar el stock de una vez.',
        'Al pausar una venta en el POS queda como cotización, visible desde Cotizaciones.',
    ],
    'v2.8.0' => [
        'Comprobante de venta con formato para impresora térmica de 80 mm.',
        'Al vender con crédito interno se imprime un comprobante de fiado con firma del cliente.',
        'El comprobante muestra los datos del local, el desglose de pagos y el vuelto.',
    ],
    'v2.7.0' => [
        'Los avisos del POS ahora son notificaciones (toasts) que no frenan la caja.',
        'Vaciar carrito y pausar venta usan ventanas del sistema, no las del navegador.',
    ],
    'v2.6.0' => [
        'Carrito del POS rediseñado: líneas más claras y botones de cantidad más grandes.',
        'La pantalla de actualización ahora muestra las novedades reales de cada versión.',
    ],
    'v2.5.0' => [
        'Productos pesables: venta por peso leyendo el código de la balanza (PLU).',
        'Ajustes de stock con varios productos y proveedor en un mismo movimiento.',
        'Facturación electrónica (DTE) con selección de proveedor en Configuración.',
    ],
    'v2.4.0' => [
        'Nueva pestaña de Configuración para la balanza de pesaje.',
        'Impresión directa del PDF del DTE al cobrar.',
    ],
    'v2.3.0' => [
        'Canje de vales usando el número de boleta, no solo el código.',
        'Editar productos sin afectar el stock; activar/desactivar productos.',
        'Menú de navegación reorganizado.',
    ],
    'v2.2.0' => [
        'Ticket de Cambio de Mercadería (TC-) con validación de saldo para nuevas compras.',
        'Módulo de Notas de Pedido: acordar cantidad y costo con el proveedor antes de recibir.',
    ],
    'v2.1.0' => [
        'Devoluciones multi-producto en una sola transacción.',
        'Emisión y canje de Vales de Devolución como saldo a favor en tienda.',
    ],
    'v2.0.0' => [
        'Reimpresión de tickets históricos y comprobantes de ventas anteriores.',
        'Abonos de crédito con imputación automática inteligente FIFO venta por venta.',
    ],
    'v1.5.0' => [
        'Centro de Ayuda y Manual de Usuario integrado dentro del sistema.',
        'Guías operativas para aperturas de turno, arqueos y administración.',
    ],
    'v1.4.0' => [
        'Motor de Promociones: descuentos por unidad y ofertas multibuy por volumen (packs).',
        'Validación automática de promociones en tiempo real en la caja registradora.',
    ],
    'v1.3.0' => [
        'Recepción de Compras como asistente en dos fases: multi-ítem y ajuste de precios/costos.',
        'Protección CSRF en los endpoints de inventario y compras.',
    ],
    'v1.2.0' => [
        'Generación de Cierre Z fiscal al cerrar turno con clave de supervisor.',
        'Registro de movimientos de caja (retiro e ingreso de efectivo) desde el POS.',
        'Redirección automática a la caja al iniciar turno.',
    ],
    'v1.1.0' => [
        'Auditoría y fortalecimiento de seguridad: sanitización y escape contra XSS en todas las vistas.',
        'Iconografía local con Font Awesome sin dependencias de internet.',
        'Control estricto de roles (Cajeros con accesos restringidos a módulos administrativos).',
    ],
    'v1.0.0' => [
        'Lanzamiento inicial: base del Punto de Venta (POS) con carrito y catálogo de productos.',
        'Gestión de turnos de caja registradora, apertura, control de efectivo y arqueo.',
        'Control de stock, catálogo de productos y módulo de clientes para ventas a crédito.',
    ],
]);

// Hora de Chile para todo el sistema (tickets, boletas, reportes). Sin esto PHP usa UTC
// y las horas impresas quedan adelantadas 3-4 horas respecto de la base de datos.
date_default_timezone_set('America/Santiago');

// URL de un CSS/JS con versión según su fecha de modificación: cada cambio del
// archivo obliga al navegador a descargarlo de nuevo en vez de usar la copia en caché.
function asset_url(string $ruta): string {
    $mtime = @filemtime(__DIR__ . '/../' . $ruta);
    return $ruta . '?v=' . APP_VERSION . ($mtime ? '-' . $mtime : '');
}

// Cargar variables desde .env (no versionado) si existe
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim(trim($value), "\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}

// Configuración de Conexión a MySQL
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'minimarket_local');

$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
if ($dbUser === false || $dbPass === false) {
    // Equipo sin instalar: llevar al asistente (instalar.php) desde cualquier pantalla o API.
    if (PHP_SAPI !== 'cli' && is_file(__DIR__ . '/../instalar.php')) {
        $raiz = str_replace('\\', '/', (string)realpath(__DIR__ . '/..'));
        $script = str_replace('\\', '/', (string)realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $relativo = ($raiz !== '' && strpos($script, $raiz . '/') === 0) ? substr($script, strlen($raiz) + 1) : '';
        if (strpos($relativo, 'api/') === 0) {
            http_response_code(503);
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode(['success' => false, 'error' => 'El sistema no está instalado.']));
        }
        header('Location: ' . str_repeat('../', substr_count($relativo, '/')) . 'instalar.php');
        exit;
    }
    die("<div style='font-family:sans-serif; padding:20px; color:#c0392b; background:#fadbd8; border-radius:8px; margin:20px;'>
        <h2>⚠️ Faltan credenciales de base de datos</h2>
        <p>Copia <code>.env.example</code> a <code>.env</code> y completa <code>DB_USER</code> y <code>DB_PASS</code> con las credenciales reales.</p>
    </div>");
}
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            $esApi = (PHP_SAPI !== 'cli') && (
                str_contains($_SERVER['SCRIPT_FILENAME'] ?? '', 'api/') ||
                str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/') ||
                str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            );
            if ($esApi) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => 'Error de conexión a la base de datos: ' . $e->getMessage()
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, "Error de conexión a MySQL (" . DB_NAME . "): " . $e->getMessage() . "\n");
                exit(1);
            }
            die("<div style='font-family:sans-serif; padding:20px; color:#c0392b; background:#fadbd8; border-radius:8px; margin:20px;'>
                <h2>⚠️ Error de conexión a la Base de Datos</h2>
                <p><strong>Detalle:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                <p>Asegúrate de que el servicio MySQL en Laragon esté encendido y que la base de datos <code>" . DB_NAME . "</code> exista.</p>
            </div>");
        }
    }
    return $pdo;
}
