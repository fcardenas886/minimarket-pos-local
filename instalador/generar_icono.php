<?php
// Genera instalador/icono.ico (carrito blanco sobre cuadrado índigo redondeado) en 16..256 px.
// Uso: php instalador/generar_icono.php   (requiere la extensión gd)
if (!function_exists('imagecreatetruecolor')) { fwrite(STDERR, "Falta la extensión gd\n"); exit(1); }

function dibujar(int $n): string {
    $s = 4; // sobremuestreo para bordes suaves
    $N = $n * $s;
    $im = imagecreatetruecolor($N, $N);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    $fondo = imagecolorallocate($im, 79, 70, 229);
    $blanco = imagecolorallocate($im, 255, 255, 255);

    // Cuadrado con esquinas redondeadas
    $r = (int)($N * 0.22);
    imagefilledrectangle($im, $r, 0, $N - $r, $N, $fondo);
    imagefilledrectangle($im, 0, $r, $N, $N - $r, $fondo);
    foreach ([[$r, $r], [$N - $r, $r], [$r, $N - $r], [$N - $r, $N - $r]] as [$x, $y]) {
        imagefilledellipse($im, $x, $y, $r * 2, $r * 2, $fondo);
    }

    // Carrito: canasto trapezoidal, manija y dos ruedas
    $u = $N / 100;
    imagesetthickness($im, (int)max(1, 7 * $u));
    imageline($im, (int)(14 * $u), (int)(24 * $u), (int)(26 * $u), (int)(24 * $u), $blanco);
    imagefilledpolygon($im, [
        (int)(26 * $u), (int)(32 * $u),
        (int)(84 * $u), (int)(32 * $u),
        (int)(76 * $u), (int)(62 * $u),
        (int)(34 * $u), (int)(62 * $u),
    ], $blanco);
    imageline($im, (int)(24 * $u), (int)(24 * $u), (int)(34 * $u), (int)(62 * $u), $blanco);
    imagefilledellipse($im, (int)(38 * $u), (int)(77 * $u), (int)(13 * $u), (int)(13 * $u), $blanco);
    imagefilledellipse($im, (int)(72 * $u), (int)(77 * $u), (int)(13 * $u), (int)(13 * $u), $blanco);

    $out = imagecreatetruecolor($n, $n);
    imagesavealpha($out, true);
    imagealphablending($out, false);
    imagecopyresampled($out, $im, 0, 0, 0, 0, $n, $n, $N, $N);
    ob_start();
    imagepng($out);
    return ob_get_clean();
}

$tamanos = [16, 24, 32, 48, 64, 128, 256];
$pngs = array_map('dibujar', $tamanos);
$ico = pack('vvv', 0, 1, count($tamanos));
$offset = 6 + 16 * count($tamanos);
foreach ($tamanos as $i => $t) {
    $ico .= pack('CCCCvvVV', $t >= 256 ? 0 : $t, $t >= 256 ? 0 : $t, 0, 0, 1, 32, strlen($pngs[$i]), $offset);
    $offset += strlen($pngs[$i]);
}
file_put_contents(__DIR__ . '/icono.ico', $ico . implode('', $pngs));
file_put_contents(__DIR__ . '/icono_vista_previa.png', $pngs[count($pngs) - 1]);

// Íconos para instalar las pantallas de garzón y cocina como app en celulares y tablets.
$dirApp = __DIR__ . '/../assets/img';
if (!is_dir($dirApp)) mkdir($dirApp, 0777, true);
foreach ([180 => 'app-180.png', 192 => 'app-192.png', 512 => 'app-512.png'] as $t => $nombre) {
    file_put_contents("$dirApp/$nombre", dibujar($t));
}
echo "icono.ico y assets/img/app-*.png generados\n";
