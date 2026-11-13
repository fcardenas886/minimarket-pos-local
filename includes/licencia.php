<?php
/**
 * Licencias de por vida por plan (Básico / Comercio).
 *
 * La licencia es un texto "MML1.<datos>.<firma>" donde <datos> es un JSON en
 * base64url y <firma> es una firma ECDSA P-256 (SHA-256) de esos datos hecha con
 * la clave privada del proveedor. Aquí solo vive la clave pública, así que el
 * cliente puede leer la licencia pero no fabricar ni modificar una.
 *
 * Datos de la licencia:
 *   { "v":1, "cliente":"...", "rut":"76453920-K", "plan":"comercio",
 *     "actualizaciones_hasta":"2027-11-30", "emitida":"2026-11-10" }
 *
 * - No vence: el uso es de por vida.
 * - Queda ligada al RUT del negocio (MINIMARKET_RUT en Configuración).
 * - "actualizaciones_hasta" decide qué paquetes de actualización se pueden instalar.
 *
 * Sin licencia válida el sistema funciona como plan Básico y muestra un aviso.
 * La emisión de boletas (fase 2) exigirá licencia válida.
 *
 * Se guarda en un archivo (config/licencia.lic) y no en la base de datos.
 */

const LICENCIA_ARCHIVO = __DIR__ . '/../config/licencia.lic';

const LICENCIA_CLAVE_PUBLICA = <<<PEM
-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEmGKc0qXKeJSkbeQEWWXMzA9xxMvJ
QGfSYyrz67Lr9rC1RYeGzrMLca0MN/oi+2q/p11HX8ebYNQG+tvxNny4UA==
-----END PUBLIC KEY-----
PEM;

/** Módulos que se pueden activar por plan. Lo que no está aquí es base (todos los planes). */
const LICENCIA_PLANES = [
    'basico' => [
        'nombre'       => 'Básico',
        'max_usuarios' => 2,
        'modulos'      => [],
    ],
    'comercio' => [
        'nombre'       => 'Comercio',
        'max_usuarios' => 3,
        'modulos'      => ['clientes', 'devoluciones', 'compras', 'kardex', 'inventario', 'promociones', 'precios_rapidos'],
    ],
];

/**
 * Módulos extra: se venden aparte y se suman a cualquier plan.
 * La licencia los lista en "extras": ["mesas", ...].
 */
const LICENCIA_EXTRAS = [
    'mesas' => 'Mesas y garzones (pedidos desde tablet o celular, cocina y comandas)',
];

/** Módulos que existen solo en el POS online: ningún plan local los activa. */
const LICENCIA_SOLO_ONLINE = ['cotizaciones', 'notaspedido', 'supervisor', 'utilidades', 'precios_masivos', 'multicaja', 'reportes_cajeros', 'factura'];

const LICENCIA_NOMBRES_MODULO = [
    'clientes'         => 'Clientes y fiados',
    'devoluciones'     => 'Devoluciones y vales',
    'compras'          => 'Compras y proveedores',
    'kardex'           => 'Kardex y ajustes de stock',
    'inventario'       => 'Toma de inventario',
    'promociones'      => 'Promociones',
    'cotizaciones'     => 'Cotizaciones',
    'notaspedido'      => 'Notas de pedido',
    'supervisor'       => 'Clave de supervisor',
    'utilidades'       => 'Reporte de utilidades',
    'precios_rapidos'  => 'Actualizador rápido de precios',
    'precios_masivos'  => 'Cambio masivo de precios (por % y categoría) y etiquetas',
    'multicaja'        => 'Varias cajas',
    'reportes_cajeros' => 'Rendimiento de cajeros',
    'factura'          => 'Factura electrónica',
];

/**
 * Qué módulo protege cada script (ruta relativa a la raíz del sistema).
 * Se aplica automáticamente desde includes/auth.php, que cargan todas las
 * pantallas y APIs, así que un script nuevo de un módulo solo debe agregarse aquí.
 */
const LICENCIA_SCRIPTS = [
    'clientes.php'                     => 'clientes',
    'api/detalle_cuenta_cliente.php'   => 'clientes',
    'devoluciones.php'                 => 'devoluciones',
    'api/registrar_devolucion.php'     => 'devoluciones',
    'api/verificar_vale.php'           => 'devoluciones',
    'api/reembolsar_vale.php'          => 'devoluciones',
    'compras.php'                      => 'compras',
    'proveedores.php'                  => 'compras',
    'api/registrar_compra.php'         => 'compras',
    'api/actualizar_precios_compra.php'=> 'compras',
    'kardex.php'                       => 'kardex',
    'ajustes.php'                      => 'kardex',
    'api/registrar_ajuste.php'         => 'kardex',
    'inventario.php'                   => 'inventario',
    'api/inventario.php'               => 'inventario',
    'promociones.php'                  => 'promociones',
    'cotizaciones.php'                 => 'cotizaciones',
    // api/cotizaciones.php queda en base: el POS lo usa para "Pausar venta".
    'notaspedido.php'                  => 'notaspedido',
    'api/registrar_nota_pedido.php'    => 'notaspedido',
    'api/ver_nota_pedido.php'          => 'notaspedido',
    'api/autorizar_supervisor.php'     => 'supervisor',
    'reporte_utilidades.php'           => 'utilidades',
    // Actualizador de precios: versión reducida en Comercio; carga por categoría,
    // aumentos por % y etiquetas de góndola quedan para el online (precios_masivos).
    'actualizar_precios.php'           => 'precios_rapidos',
    'api/guardar_cambio_precios.php'   => 'precios_rapidos',
    'cajas.php'                        => 'multicaja',
    'garzon.php'                       => 'mesas',
    'cocina.php'                       => 'mesas',
    'api/mesas.php'                    => 'mesas',
];

/**
 * Lo único que se puede abrir desde OTROS equipos de la red del local
 * (tablets y celulares de garzones, pantalla de cocina). Todo lo demás
 * (caja, configuración, reportes...) solo responde en el propio PC.
 */
const ACCESO_RED_PERMITIDO = ['garzon.php', 'cocina.php', 'api/mesas.php'];

function licencia_b64url_decode(string $s) {
    return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
}

/** "76.453.920-k" -> "76453920-K" */
function licencia_normalizar_rut(?string $rut): string {
    $rut = strtoupper(preg_replace('/[^0-9kK]/', '', (string)$rut));
    if (strlen($rut) < 2) return '';
    return substr($rut, 0, -1) . '-' . substr($rut, -1);
}

/** Valida formato y dígito verificador de un RUT chileno. */
function rut_valido(string $rut): bool {
    $rut = strtoupper(preg_replace('/[^0-9kK]/', '', $rut));
    if (!preg_match('/^(\d{7,8})([0-9K])$/', $rut, $m)) return false;
    $suma = 0; $mult = 2;
    for ($i = strlen($m[1]) - 1; $i >= 0; $i--) {
        $suma += (int)$m[1][$i] * $mult;
        $mult = $mult === 7 ? 2 : $mult + 1;
    }
    $dv = 11 - ($suma % 11);
    $dv = $dv === 11 ? '0' : ($dv === 10 ? 'K' : (string)$dv);
    return $dv === $m[2];
}

/**
 * Verifica un texto de licencia. No mira el RUT del negocio.
 * @return array ['ok'=>bool, 'datos'=>array|null, 'error'=>string|null]
 */
function licencia_verificar_texto(string $texto): array {
    $texto = trim($texto);
    $partes = explode('.', $texto);
    if (count($partes) !== 3 || $partes[0] !== 'MML1') {
        return ['ok' => false, 'datos' => null, 'error' => 'El texto no tiene formato de licencia.'];
    }
    $json = licencia_b64url_decode($partes[1]);
    $firma = licencia_b64url_decode($partes[2]);
    if ($json === false || $firma === false) {
        return ['ok' => false, 'datos' => null, 'error' => 'La licencia está dañada.'];
    }
    $clave = openssl_pkey_get_public(LICENCIA_CLAVE_PUBLICA);
    if (!$clave || openssl_verify($partes[0] . '.' . $partes[1], $firma, $clave, OPENSSL_ALGO_SHA256) !== 1) {
        return ['ok' => false, 'datos' => null, 'error' => 'La firma de la licencia no es válida.'];
    }
    $datos = json_decode($json, true);
    if (!is_array($datos) || empty($datos['rut']) || !isset(LICENCIA_PLANES[$datos['plan'] ?? ''])) {
        return ['ok' => false, 'datos' => null, 'error' => 'La licencia no tiene un plan o RUT válido.'];
    }
    $datos['extras'] = array_values(array_intersect((array)($datos['extras'] ?? []), array_keys(LICENCIA_EXTRAS)));
    return ['ok' => true, 'datos' => $datos, 'error' => null];
}

/** RUT del negocio configurado en el sistema. */
function licencia_rut_negocio(): string {
    try {
        $stmt = getDB()->prepare("SELECT Valor FROM configuraciones WHERE Clave = 'MINIMARKET_RUT'");
        $stmt->execute();
        return licencia_normalizar_rut((string)$stmt->fetchColumn());
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * Estado de la licencia instalada (se calcula una vez por petición).
 * @return array ['valida','plan','plan_nombre','max_usuarios','cliente','rut','actualizaciones_hasta','error']
 */
function licencia_estado(bool $recargar = false): array {
    static $cache = null;
    if ($cache !== null && !$recargar) return $cache;

    $estado = [
        'valida' => false,
        'plan' => 'basico',
        'cliente' => null,
        'rut' => null,
        'actualizaciones_hasta' => null,
        'extras' => [],
        'error' => 'No hay licencia instalada.',
    ];

    if (is_file(LICENCIA_ARCHIVO)) {
        $r = licencia_verificar_texto((string)file_get_contents(LICENCIA_ARCHIVO));
        if (!$r['ok']) {
            $estado['error'] = $r['error'];
        } else {
            $d = $r['datos'];
            $rutLic = licencia_normalizar_rut($d['rut']);
            $estado['cliente'] = $d['cliente'] ?? null;
            $estado['rut'] = $rutLic;
            $estado['actualizaciones_hasta'] = $d['actualizaciones_hasta'] ?? null;
            $rutNegocio = licencia_rut_negocio();
            if ($rutNegocio !== $rutLic) {
                $estado['error'] = "La licencia es para el RUT $rutLic, pero el negocio está configurado con " . ($rutNegocio ?: 'un RUT vacío') . '.';
            } else {
                $estado['valida'] = true;
                $estado['plan'] = $d['plan'];
                $estado['extras'] = $d['extras'];
                $estado['error'] = null;
            }
        }
    }

    $plan = LICENCIA_PLANES[$estado['plan']];
    $estado['plan_nombre'] = $plan['nombre'];
    $estado['max_usuarios'] = $plan['max_usuarios'];
    return $cache = $estado;
}

/** ¿Está activo este módulo en el plan instalado? Los módulos base siempre lo están. */
function modulo_activo(string $modulo): bool {
    if (in_array($modulo, LICENCIA_SOLO_ONLINE, true)) return false;
    if (isset(LICENCIA_EXTRAS[$modulo])) return in_array($modulo, licencia_estado()['extras'], true);
    $planificables = array_merge(...array_column(LICENCIA_PLANES, 'modulos'));
    if (!in_array($modulo, $planificables, true)) return true; // módulo base
    return in_array($modulo, LICENCIA_PLANES[licencia_estado()['plan']]['modulos'], true);
}

/** Módulo que protege el script actual, o null si es base. */
function licencia_modulo_del_script(): ?string {
    $raiz = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $script = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '');
    if ($raiz === '' || strpos($script, $raiz . '/') !== 0) return null;
    return LICENCIA_SCRIPTS[substr($script, strlen($raiz) + 1)] ?? null;
}

/** Corta la petición si el script actual pertenece a un módulo que el plan no incluye. */
function licencia_proteger_script_actual(): void {
    if (PHP_SAPI === 'cli') return;
    $modulo = licencia_modulo_del_script();
    if ($modulo === null || modulo_activo($modulo)) return;

    $nombre = LICENCIA_NOMBRES_MODULO[$modulo] ?? (LICENCIA_EXTRAS[$modulo] ?? $modulo);
    $soloOnline = in_array($modulo, LICENCIA_SOLO_ONLINE, true);
    if ($soloOnline) {
        $msg = "\"$nombre\" está disponible en la versión online del sistema.";
    } elseif (isset(LICENCIA_EXTRAS[$modulo])) {
        $msg = "\"$nombre\" es un módulo adicional que no está activado en tu licencia. Consulta a tu proveedor.";
    } else {
        $msg = "\"$nombre\" está incluido en el plan Comercio. Tu plan actual es " . licencia_estado()['plan_nombre'] . '.';
    }

    http_response_code(403);
    $esApi = strpos(str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? ''), '/api/') !== false;
    if ($esApi) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $msg, 'modulo_no_incluido' => $modulo], JSON_UNESCAPED_UNICODE);
    } else {
        $m = htmlspecialchars($msg);
        echo "<div style='font-family:sans-serif; padding:40px; text-align:center;'><h2>No incluido en tu plan</h2><p>$m</p>"
           . "<p><a href='configuracion.php?tab=licencia'>Ver mi licencia</a> · <a href='index.php'>Volver al inicio</a></p></div>";
    }
    exit;
}

/** Guarda una licencia nueva si es válida para este negocio. Devuelve null o el mensaje de error. */
function licencia_instalar(string $texto): ?string {
    $r = licencia_verificar_texto($texto);
    if (!$r['ok']) return $r['error'];
    $rutLic = licencia_normalizar_rut($r['datos']['rut']);
    $rutNegocio = licencia_rut_negocio();
    if ($rutLic !== $rutNegocio) {
        return "Esta licencia es para el RUT $rutLic y el negocio está configurado con " . ($rutNegocio ?: 'un RUT vacío') . '. Revisa el RUT en Ajustes Generales.';
    }
    if (file_put_contents(LICENCIA_ARCHIVO, trim($texto), LOCK_EX) === false) {
        return 'No se pudo guardar el archivo de licencia (revisa permisos de la carpeta config).';
    }
    licencia_estado(true);
    return null;
}

/** ¿La petición viene de este mismo equipo (no de una tablet o celular de la red)? */
function acceso_es_local(): bool {
    return in_array($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', ['127.0.0.1', '::1'], true);
}

/**
 * IP de este equipo en la red del local, para que las tablets sepan a dónde conectarse.
 * 'principal' es la de la tarjeta que sale al router (se averigua con un "connect" UDP, que
 * no envía ningún paquete: funciona aunque no haya internet). Se descartan las direcciones
 * locales (127.*) y las automáticas sin red (169.254.*), como las de adaptadores virtuales.
 */
function ips_del_equipo(): array {
    $principal = null;
    $s = @stream_socket_client('udp://8.8.8.8:53', $errno, $errstr, 1);
    if ($s) {
        $principal = explode(':', (string)stream_socket_get_name($s, false))[0] ?: null;
        fclose($s);
    }
    $todas = array_filter((array)@gethostbynamel(gethostname()), fn($ip) => !preg_match('/^(127\.|169\.254\.|0\.)/', $ip));
    if ($principal && preg_match('/^(127\.|169\.254\.|0\.)/', $principal)) $principal = null;
    if (!$principal) {
        // Sin router: preferir una dirección privada típica de red de local.
        foreach ($todas as $ip) if (preg_match('/^(192\.168\.|10\.|172\.(1[6-9]|2\d|3[01])\.)/', $ip)) { $principal = $ip; break; }
    }
    return ['principal' => $principal, 'otras' => array_values(array_diff($todas, [$principal]))];
}

/** Desde otros equipos de la red solo se permiten las pantallas de garzón y cocina. */
function proteger_acceso_red(): void {
    if (PHP_SAPI === 'cli' || acceso_es_local()) return;
    $raiz = str_replace('\\', '/', (string)realpath(__DIR__ . '/..'));
    $script = str_replace('\\', '/', (string)realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $rel = ($raiz !== '' && strpos($script, $raiz . '/') === 0) ? substr($script, strlen($raiz) + 1) : '';
    if (in_array($rel, ACCESO_RED_PERMITIDO, true)) return;
    http_response_code(403);
    exit("<div style='font-family:sans-serif; padding:40px; text-align:center;'><h2>Solo disponible en el computador de caja</h2>"
       . "<p>Desde otros equipos se puede usar la pantalla de <a href='garzon.php'>garzón</a> o de <a href='cocina.php'>cocina</a>.</p></div>");
}

/** Cantidad de usuarios activos y si se alcanzó el máximo del plan. Los garzones (PIN) no cuentan. */
function licencia_usuarios_disponibles(PDO $pdo): array {
    $activos = (int)$pdo->query("
        SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.RolID = u.RolID
        WHERE u.Activo = TRUE AND r.Nombre <> 'Garzón'
    ")->fetchColumn();
    $max = licencia_estado()['max_usuarios'];
    return ['activos' => $activos, 'max' => $max, 'puede_agregar' => $activos < $max];
}
