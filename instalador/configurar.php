<?php
/**
 * Configuración posterior a la instalación de Minimarket POS (la ejecuta el instalador
 * con el PHP incluido, como administrador).
 *
 *   php configurar.php --instalar        configura, crea servicios, base de datos, .env y tareas
 *   php configurar.php --desinstalar     detiene y quita servicios y tareas (no borra datos)
 *   php configurar.php --probar          como --instalar pero sin servicios ni tareas:
 *                                         levanta MySQL y Apache como procesos (sin administrador)
 *   php configurar.php --detener-prueba  detiene los procesos del modo --probar
 *
 * Opciones: --puerto-web=8090 --puerto-db=3307
 * Es idempotente: se puede volver a ejecutar (por ejemplo al actualizar) sin perder datos.
 */
if (PHP_SAPI !== 'cli') exit;

$opt = getopt('', ['instalar', 'desinstalar', 'probar', 'detener-prueba', 'puerto-web::', 'puerto-db::', 'red-local', 'solo-local']);
$BASE = str_replace('\\', '/', __DIR__);
$WIN = str_replace('/', '\\', $BASE);
$PUERTO_WEB = (int)($opt['puerto-web'] ?? 8090);
$PUERTO_DB = (int)($opt['puerto-db'] ?? 3307);

// Acceso desde la red del local (tablets de garzones, pantalla de cocina). Se recuerda en
// datos/red_local.flag para que las actualizaciones lo mantengan.
@mkdir("$BASE/datos", 0777, true);
if (isset($opt['red-local'])) @file_put_contents("$BASE/datos/red_local.flag", date('c'));
if (isset($opt['solo-local'])) @unlink("$BASE/datos/red_local.flag");
$RED_LOCAL = is_file("$BASE/datos/red_local.flag");
const REGLA_FIREWALL = 'Minimarket POS (tablets del local)';
const SERV_WEB = 'MinimarketPOS-Web';
const SERV_DB = 'MinimarketPOS-DB';
const DB_NOMBRE = 'minimarket_local';
const DB_USUARIO = 'minimarket';
$CRED = "$BASE/datos/instalacion.json";

@mkdir("$BASE/logs", 0777, true);
function logx(string $m): void {
    global $BASE;
    $linea = '[' . date('Y-m-d H:i:s') . "] $m";
    echo $linea, "\n";
    file_put_contents("$BASE/logs/instalacion.log", $linea . "\n", FILE_APPEND);
}
function fallar(string $m): never {
    logx("ERROR: $m");
    exit(1);
}
/** Ejecuta un comando y devuelve [código, salida]. */
function ejecutar(string $cmd, bool $mostrar = true): array {
    if ($mostrar) logx("> $cmd");
    exec($cmd . ' 2>&1', $salida, $codigo);
    $txt = trim(implode("\n", $salida));
    if ($mostrar && $txt !== '') logx('  ' . str_replace("\n", "\n  ", $txt));
    return [$codigo, $txt];
}
function q(string $s): string { return '"' . str_replace('/', '\\', $s) . '"'; }
function puertoAbierto(int $puerto): bool {
    $c = @fsockopen('127.0.0.1', $puerto, $errno, $errstr, 1);
    if ($c) { fclose($c); return true; }
    return false;
}
function esperarPuerto(int $puerto, int $segundos, string $nombre): void {
    for ($i = 0; $i < $segundos; $i++) {
        if (puertoAbierto($puerto)) return;
        sleep(1);
    }
    fallar("$nombre no respondió en el puerto $puerto. Revisa logs/.");
}
function servicioExiste(string $n): bool { return ejecutar("sc query $n", false)[0] === 0; }

// ---------------------------------------------------------------------------
// Configuraciones
// ---------------------------------------------------------------------------

function escribirConfiguraciones(): void {
    global $BASE, $PUERTO_WEB, $PUERTO_DB, $RED_LOCAL;
    $escucha = $RED_LOCAL ? "0.0.0.0:$PUERTO_WEB" : "127.0.0.1:$PUERTO_WEB";
    foreach (['datos/sesiones', 'datos/mysql', 'logs', 'respaldos', 'tmp'] as $d) @mkdir("$BASE/$d", 0777, true);

    file_put_contents("$BASE/php/php.ini", <<<INI
; Generado por configurar.php
[PHP]
engine = On
short_open_tag = Off
memory_limit = 256M
max_execution_time = 120
post_max_size = 20M
upload_max_filesize = 16M
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = "$BASE/logs/php_error.log"
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
expose_php = Off
extension_dir = "$BASE/php/ext"
extension=curl
extension=fileinfo
extension=mbstring
extension=openssl
extension=pdo_mysql
sys_temp_dir = "$BASE/tmp"
upload_tmp_dir = "$BASE/tmp"

[Date]
date.timezone = America/Santiago

[Session]
session.save_path = "$BASE/datos/sesiones"
session.use_strict_mode = 1
session.cookie_httponly = 1
session.gc_maxlifetime = 43200

[curl]
curl.cainfo = "$BASE/php/extras/ssl/cacert.pem"

[openssl]
openssl.cafile = "$BASE/php/extras/ssl/cacert.pem"

INI);

    @mkdir("$BASE/apache/conf", 0777, true);
    file_put_contents("$BASE/apache/conf/httpd.conf", <<<CONF
# Generado por configurar.php. Con acceso de red activado escucha en la red del local;
# aun así, desde otros equipos el sistema solo permite las pantallas de garzón y cocina.
Define BASE "$BASE"
ServerRoot "\${BASE}/apache"
Listen $escucha
ServerName localhost:$PUERTO_WEB

LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule log_config_module modules/mod_log_config.so
LoadModule setenvif_module modules/mod_setenvif.so
LoadModule headers_module modules/mod_headers.so
LoadModule rewrite_module modules/mod_rewrite.so

# PHP como módulo de Apache. Las DLL se cargan desde la carpeta de PHP.
LoadFile "\${BASE}/php/php8ts.dll"
LoadFile "\${BASE}/php/libcrypto-1_1-x64.dll"
LoadFile "\${BASE}/php/libssl-1_1-x64.dll"
LoadFile "\${BASE}/php/libssh2.dll"
LoadFile "\${BASE}/php/nghttp2.dll"
LoadModule php_module "\${BASE}/php/php8apache2_4.dll"
PHPIniDir "\${BASE}/php"
<FilesMatch "\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>

TypesConfig conf/mime.types
DirectoryIndex index.php
DocumentRoot "\${BASE}/app"
ServerTokens Prod
ServerSignature Off
PidFile "\${BASE}/logs/httpd.pid"
ThreadsPerChild 64
EnableSendfile Off

<Directory />
    AllowOverride None
    Require all denied
</Directory>
<Directory "\${BASE}/app">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

ErrorLog "\${BASE}/logs/apache_error.log"
LogLevel warn
CONF);

    file_put_contents("$BASE/mysql/my.ini", <<<INI
# Generado por configurar.php — solo escucha en este equipo (127.0.0.1).
[mysqld]
basedir = "$BASE/mysql"
datadir = "$BASE/datos/mysql"
port = $PUERTO_DB
bind-address = 127.0.0.1
mysqlx = OFF
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
default_authentication_plugin = mysql_native_password
explicit_defaults_for_timestamp = 1
innodb_buffer_pool_size = 128M
max_connections = 50
max_allowed_packet = 64M
log-error = "$BASE/logs/mysql_error.log"
secure-file-priv = NULL

[client]
port = $PUERTO_DB
host = 127.0.0.1

[mysqldump]
quick
max_allowed_packet = 64M
INI);
    logx('Configuraciones de PHP, Apache y MySQL escritas.');
}

function inicializarMysql(): void {
    global $BASE;
    if (is_dir("$BASE/datos/mysql/mysql")) {
        logx('La base de datos ya existe: se conserva.');
        return;
    }
    logx('Inicializando MySQL (puede tardar un minuto)...');
    [$c] = ejecutar(q("$BASE/mysql/bin/mysqld.exe") . ' --defaults-file=' . q("$BASE/mysql/my.ini") . ' --initialize-insecure --console');
    if ($c !== 0 || !is_dir("$BASE/datos/mysql/mysql")) fallar('No se pudo inicializar MySQL.');
}

// ---------------------------------------------------------------------------
// Servicios / procesos
// ---------------------------------------------------------------------------

function verificarPuertosLibres(): void {
    global $PUERTO_WEB, $PUERTO_DB;
    foreach ([$PUERTO_WEB => SERV_WEB, $PUERTO_DB => SERV_DB] as $p => $serv) {
        if (puertoAbierto($p) && !servicioExiste($serv)) {
            fallar("El puerto $p ya está en uso por otro programa. Ciérralo o instala con otro puerto.");
        }
    }
}

function instalarServicios(): void {
    global $BASE, $PUERTO_WEB, $PUERTO_DB;
    if (!servicioExiste(SERV_DB)) {
        [$c] = ejecutar(q("$BASE/mysql/bin/mysqld.exe") . ' --install ' . SERV_DB . ' --defaults-file=' . q("$BASE/mysql/my.ini"));
        if ($c !== 0) fallar('No se pudo registrar el servicio de base de datos (¿se ejecutó como administrador?).');
    }
    ejecutar('sc config ' . SERV_DB . ' start= auto');
    ejecutar('net start ' . SERV_DB);
    esperarPuerto($PUERTO_DB, 60, 'MySQL');

    if (!servicioExiste(SERV_WEB)) {
        [$c] = ejecutar(q("$BASE/apache/bin/httpd.exe") . ' -k install -n ' . SERV_WEB . ' -f ' . q("$BASE/apache/conf/httpd.conf"));
        if ($c !== 0) fallar('No se pudo registrar el servicio web.');
    }
    ejecutar('sc config ' . SERV_WEB . ' start= auto');
    // Reiniciar para que tome la configuración nueva (en actualizaciones).
    ejecutar('net stop ' . SERV_WEB, false);
    ejecutar('net start ' . SERV_WEB);
    esperarPuerto($PUERTO_WEB, 30, 'Apache');
}

/** Lanza un programa totalmente separado de este proceso (no queda esperando a que termine). */
function lanzarSeparado(string $exe, string $args): void {
    $ps = 'Start-Process -WindowStyle Hidden -FilePath ' . "'" . str_replace('/', '\\', $exe) . "'"
        . ' -ArgumentList ' . "'" . str_replace("'", "''", $args) . "'";
    ejecutar('powershell -NoProfile -Command "' . str_replace('"', '\"', $ps) . '"', false);
}

function iniciarProcesosPrueba(): void {
    global $BASE, $PUERTO_WEB, $PUERTO_DB;
    if (!puertoAbierto($PUERTO_DB)) {
        lanzarSeparado("$BASE/mysql/bin/mysqld.exe", '--defaults-file=' . q("$BASE/mysql/my.ini"));
    }
    esperarPuerto($PUERTO_DB, 60, 'MySQL');
    if (!puertoAbierto($PUERTO_WEB)) {
        lanzarSeparado("$BASE/apache/bin/httpd.exe", '-f ' . q("$BASE/apache/conf/httpd.conf"));
    }
    esperarPuerto($PUERTO_WEB, 30, 'Apache');
    logx('Procesos de prueba en marcha (MySQL y Apache).');
}

function detenerProcesosPrueba(): void {
    global $BASE;
    $cred = leerCredenciales();
    if ($cred) {
        // Apagado ordenado de MySQL.
        $env = 'set "MYSQL_PWD=' . $cred['root'] . '" && ';
        ejecutar($env . q("$BASE/mysql/bin/mysqladmin.exe") . ' --defaults-file=' . q("$BASE/mysql/my.ini") . ' -uroot shutdown', false);
    }
    // Apache: solo los procesos de esta carpeta.
    ejecutar('powershell -NoProfile -Command "Get-Process httpd -ErrorAction SilentlyContinue | Where-Object { $_.Path -like \'' . str_replace('/', '\\', $BASE) . '\\*\' } | Stop-Process -Force"', false);
    logx('Procesos de prueba detenidos.');
}

function desinstalarServicios(): void {
    global $BASE;
    foreach ([SERV_WEB, SERV_DB] as $s) {
        if (servicioExiste($s)) {
            ejecutar("net stop $s");
            ejecutar("sc delete $s");
        }
    }
    foreach (['Emitir boletas pendientes', 'Respaldo diario'] as $t) {
        ejecutar('schtasks /Delete /F /TN "MinimarketPOS\\' . $t . '"', false);
    }
    ejecutar('netsh advfirewall firewall delete rule name="' . REGLA_FIREWALL . '"', false);
    logx('Servicios y tareas quitados. Los datos quedan en ' . $BASE . '/datos y ' . $BASE . '/respaldos.');
}

// ---------------------------------------------------------------------------
// Base de datos y .env
// ---------------------------------------------------------------------------

function leerCredenciales(): ?array {
    global $CRED;
    if (!is_file($CRED)) return null;
    $d = json_decode((string)file_get_contents($CRED), true);
    return is_array($d) ? $d : null;
}

function configurarBase(): void {
    global $BASE, $PUERTO_DB, $CRED;
    $cred = leerCredenciales();
    $dsn = "mysql:host=127.0.0.1;port=$PUERTO_DB;charset=utf8mb4";
    $opc = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

    if ($cred) {
        $pdo = new PDO($dsn, 'root', $cred['root'], $opc);
    } else {
        // Instalación nueva: root sin clave tras --initialize-insecure; se le pone una aleatoria.
        $pdo = new PDO($dsn, 'root', '', $opc);
        $cred = ['root' => bin2hex(random_bytes(16)), 'app' => bin2hex(random_bytes(16)), 'creado' => date('c')];
        $pdo->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY " . $pdo->quote($cred['root']));
        file_put_contents($CRED, json_encode($cred, JSON_PRETTY_PRINT));
        logx('Contraseñas de MySQL generadas (guardadas en datos/instalacion.json).');
    }

    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NOMBRE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    foreach (['localhost', '127.0.0.1'] as $h) {
        $u = "'" . DB_USUARIO . "'@'$h'";
        $pdo->exec("CREATE USER IF NOT EXISTS $u IDENTIFIED BY " . $pdo->quote($cred['app']));
        $pdo->exec("ALTER USER $u IDENTIFIED BY " . $pdo->quote($cred['app']));
        $pdo->exec('GRANT ALL PRIVILEGES ON `' . DB_NOMBRE . "`.* TO $u");
    }
    $pdo->exec('FLUSH PRIVILEGES');

    $db = new PDO("$dsn;dbname=" . DB_NOMBRE, DB_USUARIO, $cred['app'], $opc);
    $tablas = (int)$db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
    $nueva = $tablas === 0;
    if ($nueva) {
        foreach (['01_esquema.sql', '02_datos_base.sql'] as $f) {
            $db->exec((string)file_get_contents("$BASE/app/db/instalacion/$f"));
        }
        logx('Base de datos creada con el esquema y los datos base.');
    } else {
        logx("La base ya tiene $tablas tablas: se conserva.");
    }
    $GLOBALS['BASE_NUEVA'] = $nueva;
    $GLOBALS['CRED_APP'] = $cred['app'];

    file_put_contents("$BASE/app/.env", implode("\n", [
        '# Generado por configurar.php el ' . date('Y-m-d H:i'),
        'DB_HOST=127.0.0.1',
        "DB_PORT=$PUERTO_DB",
        'DB_NAME=' . DB_NOMBRE,
        'DB_USER=' . DB_USUARIO,
        'DB_PASS=' . $cred['app'],
        "MYSQLDUMP_PATH=$BASE/mysql/bin/mysqldump.exe",
        "RESPALDO_DIR=$BASE/respaldos",
        '',
    ]));
    logx('Archivo .env escrito.');
}

/**
 * Aplica las migraciones de app/db/migrations que falten, en orden, registrándolas en la tabla
 * `migraciones`. Base nueva: todas quedan marcadas (el esquema ya las trae). Base sin la tabla
 * (instalada antes de existir este control): se asume hasta la 0011, con la que nació el instalador.
 */
function aplicarMigraciones(): void {
    global $BASE, $PUERTO_DB;
    $db = new PDO("mysql:host=127.0.0.1;port=$PUERTO_DB;dbname=" . DB_NOMBRE . ';charset=utf8mb4', DB_USUARIO, $GLOBALS['CRED_APP'],
                  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $archivos = glob("$BASE/app/db/migrations/*.sql");
    sort($archivos, SORT_STRING);
    $nombres = array_map('basename', $archivos);

    $existe = (bool)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'migraciones'")->fetchColumn();
    if (!$existe) {
        $db->exec("CREATE TABLE migraciones (Archivo VARCHAR(150) NOT NULL PRIMARY KEY, AplicadaEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)
                   ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $base = $GLOBALS['BASE_NUEVA'] ? $nombres : array_filter($nombres, fn($n) => strcmp(substr($n, 0, 4), '0011') <= 0);
        $ins = $db->prepare('INSERT IGNORE INTO migraciones (Archivo) VALUES (:a)');
        foreach ($base as $n) $ins->execute([':a' => $n]);
        logx('Control de migraciones creado (' . count($base) . ' ya incluidas en la base).');
    }

    $aplicadas = $db->query('SELECT Archivo FROM migraciones')->fetchAll(PDO::FETCH_COLUMN);
    $pendientes = array_values(array_diff($nombres, $aplicadas));
    if (!$pendientes) { logx('La base de datos está al día.'); return; }

    logx('Migraciones por aplicar: ' . implode(', ', $pendientes) . '. Respaldando antes...');
    [$c] = ejecutar(q("$BASE/php/php.exe") . ' -c ' . q("$BASE/php/php.ini") . ' ' . q("$BASE/app/scripts/respaldo.php"));
    if ($c !== 0) fallar('No se pudo respaldar la base antes de actualizarla; no se aplicó ningún cambio.');

    foreach ($pendientes as $n) {
        try {
            $db->exec((string)file_get_contents("$BASE/app/db/migrations/$n"));
            $db->prepare('INSERT INTO migraciones (Archivo) VALUES (:a)')->execute([':a' => $n]);
            logx("  aplicada $n");
        } catch (Throwable $e) {
            fallar("La migración $n falló: " . $e->getMessage() . '. La base quedó respaldada en ' . $BASE . '/respaldos.');
        }
    }
}

/** Regla del Firewall de Windows para que las tablets lleguen al puerto web (solo redes privadas). */
function configurarFirewall(): void {
    global $RED_LOCAL, $PUERTO_WEB;
    ejecutar('netsh advfirewall firewall delete rule name="' . REGLA_FIREWALL . '"', false);
    if ($RED_LOCAL) {
        ejecutar('netsh advfirewall firewall add rule name="' . REGLA_FIREWALL . '" dir=in action=allow protocol=TCP localport=' . $PUERTO_WEB . ' profile=private,domain');
        logx('Acceso desde la red del local ACTIVADO (tablets y celulares de garzones / cocina).');
    } else {
        logx('El sistema solo es accesible desde este equipo.');
    }
}

function crearTareas(): void {
    global $WIN;
    $php = "$WIN\\php\\php.exe";
    $tareas = [
        ['Emitir boletas pendientes', '/SC MINUTE /MO 2', "$WIN\\app\\scripts\\emitir_pendientes.php"],
        ['Respaldo diario', '/SC DAILY /ST 23:30', "$WIN\\app\\scripts\\respaldo.php"],
    ];
    foreach ($tareas as [$nombre, $cuando, $script]) {
        $tr = '\"' . $php . '\" \"' . $script . '\"';
        ejecutar('schtasks /Create /F /RU SYSTEM ' . $cuando . ' /TN "MinimarketPOS\\' . $nombre . '" /TR "' . $tr . '"');
    }
}

function comprobarWeb(): void {
    global $PUERTO_WEB;
    $ctx = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true, 'follow_location' => 0]]);
    $r = @file_get_contents("http://127.0.0.1:$PUERTO_WEB/instalar.php", false, $ctx);
    $estado = isset($http_response_header[0]) ? $http_response_header[0] : 'sin respuesta';
    logx("Comprobación web: $estado");
    if ($r === false || !preg_match('#HTTP/\S+ (200|302)#', $estado)) {
        fallar('El sistema no respondió correctamente. Revisa logs/apache_error.log y logs/php_error.log.');
    }
}

// ---------------------------------------------------------------------------

try {
    if (isset($opt['desinstalar'])) {
        desinstalarServicios();
    } elseif (isset($opt['detener-prueba'])) {
        detenerProcesosPrueba();
    } elseif (isset($opt['instalar']) || isset($opt['probar'])) {
        $prueba = isset($opt['probar']);
        // En una instalación nueva este PHP arrancó sin php.ini (lo escribe este mismo script),
        // así que no tiene pdo_mysql/curl. Se escribe la configuración y se vuelve a ejecutar con ella.
        if (!extension_loaded('pdo_mysql') && !getenv('MML_REEJECUTADO')) {
            escribirConfiguraciones();
            logx('Reiniciando la configuración con el php.ini recién creado...');
            putenv('MML_REEJECUTADO=1');
            $args = implode(' ', array_map('escapeshellarg', array_slice($argv, 1)));
            passthru(q(PHP_BINARY) . ' -c ' . q("$BASE/php/php.ini") . ' ' . q(__FILE__) . ' ' . $args, $codigo);
            exit($codigo);
        }
        if (!extension_loaded('pdo_mysql')) {
            fallar('PHP no cargó la extensión pdo_mysql. Revisa ' . $BASE . '/php/php.ini y la carpeta php/ext.');
        }
        logx('=== Configurando Minimarket POS en ' . $BASE . ($prueba ? ' (modo prueba)' : '') . ' ===');
        escribirConfiguraciones();
        if (!$prueba) verificarPuertosLibres();
        inicializarMysql();
        $prueba ? iniciarProcesosPrueba() : instalarServicios();
        configurarBase();
        aplicarMigraciones();
        if (!$prueba) { crearTareas(); configurarFirewall(); }
        comprobarWeb();
        logx("Listo. Abre http://127.0.0.1:$PUERTO_WEB/");
    } else {
        echo "Uso: php configurar.php --instalar | --desinstalar | --probar | --detener-prueba\n";
        exit(1);
    }
} catch (Throwable $e) {
    fallar($e->getMessage());
}
