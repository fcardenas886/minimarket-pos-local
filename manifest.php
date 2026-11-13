<?php
// Manifiesto de app web: permite instalar las pantallas de garzón y cocina en celulares y tablets.
//   manifest.php?app=garzon | cocina
require_once __DIR__ . '/config/database.php';

$app = ($_GET['app'] ?? '') === 'cocina' ? 'cocina' : 'garzon';
$negocio = 'Minimarket POS';
try {
    $negocio = (string)getDB()->query("SELECT Valor FROM configuraciones WHERE Clave = 'MINIMARKET_NOMBRE'")->fetchColumn() ?: $negocio;
} catch (Throwable $e) {}

$datos = $app === 'cocina'
    ? ['name' => "Cocina · $negocio", 'short_name' => 'Cocina', 'start_url' => 'cocina.php', 'background_color' => '#14171e', 'theme_color' => '#14171e', 'orientation' => 'any']
    : ['name' => "Garzón · $negocio", 'short_name' => 'Garzón', 'start_url' => 'garzon.php', 'background_color' => '#f3f4f8', 'theme_color' => '#4f46e5', 'orientation' => 'portrait'];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache');
echo json_encode($datos + [
    'id' => $datos['start_url'],
    'scope' => './',
    'display' => 'standalone',
    'lang' => 'es-CL',
    'icons' => [
        ['src' => 'assets/img/app-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'assets/img/app-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
