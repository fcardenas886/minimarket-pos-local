<?php
/**
 * Generador de licencias por terminal — HERRAMIENTA DEL PROVEEDOR. No se instala en clientes.
 * (Para uso diario es más cómodo el panel: tools/licencias/abrir_panel.bat)
 *
 * Uso (desde la raíz del proyecto):
 *   php tools/licencias/generar_licencia.php --cliente="Almacén Don Pedro" --rut=76123456-7 --plan=comercio [--meses=12] [--hasta=2027-11-30] [--extras=mesas] [--nota="..."]
 *
 * Imprime el texto de licencia y lo guarda en el registro personal (registro.json).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/lib.php';

$opt = getopt('', ['cliente:', 'rut:', 'plan:', 'meses::', 'hasta::', 'nota::', 'extras::']);
$extras = array_filter(array_map('trim', explode(',', (string)($opt['extras'] ?? ''))));
if (empty($opt['cliente']) || empty($opt['rut']) || empty($opt['plan'])) {
    fwrite(STDERR, "Faltan datos. Ejemplo:\n  php tools/licencias/generar_licencia.php --cliente=\"Almacén Don Pedro\" --rut=76123456-7 --plan=comercio\n");
    fwrite(STDERR, "Planes: " . implode(', ', array_keys(LICENCIA_PLANES)) . "\n");
    exit(1);
}
$hasta = $opt['hasta'] ?? (new DateTime())->modify('+' . (int)($opt['meses'] ?? 12) . ' months')->format('Y-m-d');

try {
    $lic = mml_generar($opt['cliente'], $opt['rut'], $opt['plan'], $hasta, $extras);
    mml_registro_agregar($lic, $opt['nota'] ?? 'Generada por terminal');
} catch (Exception $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
echo $lic['texto'] . "\n";
fwrite(STDERR, "Licencia {$lic['plan']} para {$lic['cliente']} ({$lic['rut']}), actualizaciones hasta {$lic['actualizaciones_hasta']}. Guardada en el registro.\n");
