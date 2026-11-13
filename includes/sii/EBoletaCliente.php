<?php
/**
 * Cliente de e-Boleta (eboleta.sii.cl) en PHP puro.
 *
 * Port del gateway en Python (sii-eboleta-gateway). Integración NO oficial: usa el
 * mismo backend que la app web del SII, descubierto por ingeniería inversa. El SII
 * puede cambiar endpoints, autenticación o formato sin aviso; si eso pasa, este es
 * el archivo a actualizar.
 *
 * Flujo:
 *   1. POST /sign-in con RUT de la persona + Clave Tributaria -> token Cognito + IdentityId
 *   2. Cognito GetCredentialsForIdentity -> credenciales AWS temporales
 *   3. GET info-emisor-usuario (firmado SigV4) -> datos del emisor y sucursal
 *   4. POST /api/dte/documentos/generar (firmado SigV4) -> folio + PDF
 *
 * La sesión (token y credenciales temporales) se guarda cifrada en
 * config/eboleta_sesion.json para no hacer login en cada venta.
 */
require_once __DIR__ . '/Cifrado.php';

class EBoletaError extends RuntimeException {
    /** conexion: no llegó al SII, se puede reintentar sin riesgo.
     *  incierto: la boleta pudo haberse emitido (timeout o error del servidor tras enviar); revisar antes de reintentar.
     *  rechazo: el SII rechazó los datos; reintentar igual no sirve.
     *  credenciales: RUT o Clave Tributaria incorrectos. */
    public string $tipo;
    public function __construct(string $mensaje, string $tipo) {
        parent::__construct($mensaje);
        $this->tipo = $tipo;
    }
}

class EBoletaCliente {
    const REGION = 'us-east-1';
    const AUTH_API = 'https://x78kr8nqx5.execute-api.us-east-1.amazonaws.com/prod';
    const DTE_API = 'https://cn68i6qm0g.execute-api.us-east-1.amazonaws.com/prod';
    const COGNITO = 'https://cognito-identity.us-east-1.amazonaws.com/';
    const ARCHIVO_SESION = __DIR__ . '/../../config/eboleta_sesion.json';
    const MARGEN_SEGUNDOS = 120;

    private string $rutCuerpo;
    private string $rutDv;
    private string $clave;
    private int $timeout;
    private array $sesion = [];

    /** @param string $rutUsuario RUT de la PERSONA que entra al SII (no el de la empresa), ej "12345678-9". */
    public function __construct(string $rutUsuario, string $clave, int $timeoutSegundos = 30) {
        $rut = strtoupper(preg_replace('/[^0-9kK]/', '', $rutUsuario));
        if (strlen($rut) < 2 || $clave === '') {
            throw new EBoletaError('Falta el RUT o la Clave Tributaria para e-Boleta.', 'credenciales');
        }
        $this->rutCuerpo = substr($rut, 0, -1);
        $this->rutDv = substr($rut, -1);
        $this->clave = $clave;
        $this->timeout = $timeoutSegundos;
        $this->cargarSesion();
    }

    // ------------------------------------------------------------------
    // API pública
    // ------------------------------------------------------------------

    /** Hace login y obtiene los datos del emisor: sirve para probar la conexión. */
    public function probarConexion(string $rutEmisor): array {
        $this->sesion = []; // forzar login nuevo
        $info = $this->infoEmisor($rutEmisor);
        return [
            'usuario' => $this->sesion['username'] ?? null,
            'razon_social' => $info['razon_social'] ?? $info['razonSocial'] ?? null,
            'sucursales' => count($info['sucursales'] ?? []),
        ];
    }

    /**
     * Emite una boleta. $boleta = ['rut_emisor', 'detalle' => [[NmbItem, QtyItem, PrcItem, MontoItem, DescuentoMonto?], ...], 'tipo_dte' => 39]
     * Devuelve la respuesta del SII: folio, pdf_public_url, b64encoded_pdf, dte.
     */
    public function emitirBoleta(array $boleta): array {
        $rutEmisor = $boleta['rut_emisor'];
        $info = $this->infoEmisor($rutEmisor);
        $sucursal = $info['sucursales'][0]['codigo'] ?? null;

        $emisor = ['RUTEmisor' => self::rutConGuion($rutEmisor)];
        if ($sucursal !== null) $emisor['CdgSIISucur'] = $sucursal;

        $payload = [
            'vendedor' => $this->sesion['username'],
            'Encabezado' => [
                'IdDoc' => ['TipoDTE' => (int)($boleta['tipo_dte'] ?? 39), 'Folio' => 1],
                'Emisor' => $emisor,
                'Receptor' => ['RUTRecep' => '66666666-6', 'RznSocRecep' => 'SII Boleta'],
            ],
            'Detalle' => array_values($boleta['detalle']),
            'Meta' => ['info_emisor' => $info, 'plataforma' => 'eboleta_web'],
        ];

        $creds = $this->credencialesAws();
        $r = $this->peticionFirmada('POST', self::DTE_API . '/api/dte/documentos/generar', $creds, $payload, true);
        if ($r['status'] === 200 || $r['status'] === 201) {
            $json = json_decode($r['body'], true);
            if (!is_array($json) || empty($json['folio'])) {
                throw new EBoletaError('El SII respondió sin folio: ' . mb_substr($r['body'], 0, 300), 'incierto');
            }
            return $json;
        }
        if ($r['status'] === 401 || $r['status'] === 403) {
            $this->sesion = [];
            $this->guardarSesion();
        }
        // La empresa no está habilitada en e-Boleta: es un problema general, no de esta boleta.
        // Se trata como 'credenciales' para que la cola se detenga y las boletas esperen en Pendiente.
        if ($r['status'] < 500 && preg_match('/no se encuentra autorizado|no autorizad/i', $r['body'])) {
            throw new EBoletaError('El SII indica que la empresa no está autorizada para emitir en e-Boleta (sistema gratuito). '
                . 'Revisa en eboleta.sii.cl si puedes emitir a mano; si allí tampoco, consulta a la Mesa de Ayuda del SII. '
                . 'Las boletas quedan pendientes y se emitirán cuando se resuelva. Respuesta del SII: ' . mb_substr($r['body'], 0, 200), 'credenciales');
        }
        // 5xx tras enviar: no sabemos si se emitió. 4xx: el SII rechazó los datos.
        $tipo = $r['status'] >= 500 ? 'incierto' : 'rechazo';
        throw new EBoletaError("El SII respondió HTTP {$r['status']}: " . mb_substr($r['body'], 0, 400), $tipo);
    }

    // ------------------------------------------------------------------
    // Sesión: login, Cognito y datos del emisor
    // ------------------------------------------------------------------

    private function iniciarSesion(): void {
        $r = $this->http('POST', self::AUTH_API . '/sign-in', ['Content-Type: application/json'],
            json_encode(['rut' => $this->rutCuerpo, 'dv' => $this->rutDv, 'clave' => $this->clave, 'opts' => null]));
        if ($r['status'] === 401 || $r['status'] === 403 || ($r['status'] >= 400 && $r['status'] < 500)) {
            throw new EBoletaError('El SII rechazó el RUT o la Clave Tributaria (HTTP ' . $r['status'] . ').', 'credenciales');
        }
        if ($r['status'] !== 200) {
            throw new EBoletaError('El login del SII falló (HTTP ' . $r['status'] . ').', 'conexion');
        }
        $d = json_decode($r['body'], true);
        if (empty($d['openId']['Token']) || empty($d['openId']['IdentityId'])) {
            throw new EBoletaError('Respuesta de login del SII con forma inesperada (¿cambió e-Boleta?).', 'conexion');
        }
        $expiraMs = $d['user']['expires_in'] ?? null;
        $this->sesion = [
            'rut' => $this->rutCuerpo . '-' . $this->rutDv,
            'username' => $d['user']['username'] ?? ($this->rutCuerpo . '-' . $this->rutDv),
            'identity_id' => $d['openId']['IdentityId'],
            'token' => $d['openId']['Token'],
            'token_expira' => time() + ($expiraMs ? (int)($expiraMs / 1000) : 3300),
            'aws' => null,
            'info_emisor' => [],
        ];
        $this->guardarSesion();
    }

    private function credencialesAws(): array {
        $ahora = time();
        if (empty($this->sesion['token']) || $this->sesion['token_expira'] - $ahora < self::MARGEN_SEGUNDOS) {
            $this->iniciarSesion();
        }
        $aws = $this->sesion['aws'] ?? null;
        if (!$aws || $aws['expira'] - $ahora < self::MARGEN_SEGUNDOS) {
            $r = $this->http('POST', self::COGNITO, [
                'Content-Type: application/x-amz-json-1.1',
                'X-Amz-Target: AWSCognitoIdentityService.GetCredentialsForIdentity',
            ], json_encode([
                'IdentityId' => $this->sesion['identity_id'],
                'Logins' => ['cognito-identity.amazonaws.com' => $this->sesion['token']],
            ]));
            $d = json_decode($r['body'], true);
            if ($r['status'] !== 200 || empty($d['Credentials']['AccessKeyId'])) {
                // Token vencido o revocado: la próxima vez se hace login de nuevo.
                $this->sesion = [];
                $this->guardarSesion();
                throw new EBoletaError('No se obtuvieron credenciales temporales del SII (HTTP ' . $r['status'] . ').', 'conexion');
            }
            $c = $d['Credentials'];
            $aws = [
                'ak' => $c['AccessKeyId'],
                'sk' => $c['SecretKey'],
                'st' => $c['SessionToken'],
                'expira' => (int)$c['Expiration'],
            ];
            $this->sesion['aws'] = $aws;
            $this->guardarSesion();
        }
        return $aws;
    }

    private function infoEmisor(string $rutEmisor): array {
        $cuerpo = substr(strtoupper(preg_replace('/[^0-9kK]/', '', $rutEmisor)), 0, -1);
        $creds = $this->credencialesAws();
        if (!empty($this->sesion['info_emisor'][$cuerpo])) {
            return $this->sesion['info_emisor'][$cuerpo];
        }
        $url = self::DTE_API . "/api/info-contribuyente/info-emisor-usuario/$cuerpo/" . rawurlencode($this->sesion['username']);
        $r = $this->peticionFirmada('GET', $url, $creds);
        if ($r['status'] !== 200) {
            $tipo = ($r['status'] === 403 || $r['status'] === 404) ? 'credenciales' : 'conexion';
            throw new EBoletaError("No se pudieron obtener los datos del emisor $rutEmisor (HTTP {$r['status']}). "
                . 'Revisa que la persona tenga permiso para emitir boletas de esa empresa en el SII.', $tipo);
        }
        $info = json_decode($r['body'], true);
        if (!is_array($info)) throw new EBoletaError('Datos del emisor con forma inesperada.', 'conexion');
        $this->sesion['info_emisor'][$cuerpo] = $info;
        $this->guardarSesion();
        return $info;
    }

    private function cargarSesion(): void {
        if (!is_file(self::ARCHIVO_SESION)) return;
        try {
            $s = json_decode(Cifrado::descifrar((string)file_get_contents(self::ARCHIVO_SESION)), true);
            if (is_array($s) && ($s['rut'] ?? '') === $this->rutCuerpo . '-' . $this->rutDv) {
                $this->sesion = $s;
            }
        } catch (Throwable $e) {
            $this->sesion = [];
        }
    }

    private function guardarSesion(): void {
        @file_put_contents(self::ARCHIVO_SESION, Cifrado::cifrar(json_encode($this->sesion)), LOCK_EX);
    }

    // ------------------------------------------------------------------
    // HTTP y firma AWS SigV4
    // ------------------------------------------------------------------

    private function peticionFirmada(string $metodo, string $url, array $creds, ?array $json = null, bool $esEmision = false): array {
        $cuerpo = $json === null ? '' : json_encode($json, JSON_UNESCAPED_UNICODE);
        $firmados = self::firmarSigV4($metodo, $url, $cuerpo, $creds['ak'], $creds['sk'], $creds['st'], self::REGION, 'execute-api', gmdate('Ymd\THis\Z'));
        $headers = [];
        foreach ($firmados as $k => $v) $headers[] = "$k: $v";
        if ($json !== null) $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        return $this->http($metodo, $url, $headers, $json === null ? null : $cuerpo, $esEmision);
    }

    /**
     * Firma AWS Signature Version 4. Devuelve los headers a agregar (Host, X-Amz-Date,
     * X-Amz-Security-Token si corresponde y Authorization). Pública y estática para
     * poder verificarla contra los vectores de prueba oficiales de AWS.
     */
    public static function firmarSigV4(string $metodo, string $url, string $cuerpo, string $ak, string $sk, ?string $token, string $region, string $servicio, string $amzDate): array {
        $p = parse_url($url);
        $host = $p['host'];
        $ruta = $p['path'] ?? '/';
        $rutaCanonica = implode('/', array_map(fn($s) => rawurlencode(rawurldecode($s)), explode('/', $ruta)));
        $query = '';
        if (!empty($p['query'])) {
            parse_str($p['query'], $qs);
            ksort($qs);
            $partes = [];
            foreach ($qs as $k => $v) $partes[] = rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
            $query = implode('&', $partes);
        }
        $fecha = substr($amzDate, 0, 8);

        $headers = ['host' => $host, 'x-amz-date' => $amzDate];
        if ($token) $headers['x-amz-security-token'] = $token;
        ksort($headers);
        $canonHeaders = '';
        foreach ($headers as $k => $v) $canonHeaders .= $k . ':' . trim($v) . "\n";
        $firmadosLista = implode(';', array_keys($headers));

        $canonica = implode("\n", [strtoupper($metodo), $rutaCanonica, $query, $canonHeaders, $firmadosLista, hash('sha256', $cuerpo)]);
        $alcance = "$fecha/$region/$servicio/aws4_request";
        $aFirmar = "AWS4-HMAC-SHA256\n$amzDate\n$alcance\n" . hash('sha256', $canonica);

        $kFecha = hash_hmac('sha256', $fecha, 'AWS4' . $sk, true);
        $kRegion = hash_hmac('sha256', $region, $kFecha, true);
        $kServicio = hash_hmac('sha256', $servicio, $kRegion, true);
        $kFirma = hash_hmac('sha256', 'aws4_request', $kServicio, true);
        $firma = hash_hmac('sha256', $aFirmar, $kFirma);

        $salida = ['Host' => $host, 'X-Amz-Date' => $amzDate];
        if ($token) $salida['X-Amz-Security-Token'] = $token;
        $salida['Authorization'] = "AWS4-HMAC-SHA256 Credential=$ak/$alcance, SignedHeaders=$firmadosLista, Signature=$firma";
        return $salida;
    }

    private function http(string $metodo, string $url, array $headers, ?string $cuerpo = null, bool $esEmision = false): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => min(8, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => 'MinimarketPOS-Local',
        ]);
        if ($cuerpo !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $cuerpo);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $conecto = curl_getinfo($ch, CURLINFO_CONNECT_TIME) > 0;
        curl_close($ch);

        if ($errno !== 0) {
            // Si ni siquiera se conectó, la boleta no llegó al SII: reintentar es seguro.
            $tipo = ($esEmision && $conecto) ? 'incierto' : 'conexion';
            throw new EBoletaError('Sin conexión con el SII: ' . $error, $tipo);
        }
        return ['status' => $status, 'body' => (string)$body];
    }

    private static function rutConGuion(string $rut): string {
        $r = strtoupper(preg_replace('/[^0-9kK]/', '', $rut));
        return substr($r, 0, -1) . '-' . substr($r, -1);
    }
}
