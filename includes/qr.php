<?php
/**
 * Generador de códigos QR (ISO/IEC 18004) sin dependencias.
 * Modo byte, corrección de errores nivel M (~15%), versiones 1 a 10 (hasta ~210 bytes).
 * Suficiente para direcciones como http://192.168.1.10:8090/garzon.php
 *
 *   QrCodigo::svg('http://...', 220)  -> <svg> listo para insertar
 *   QrCodigo::matriz('http://...')    -> array de filas con 1 (oscuro) / 0 (claro)
 */
class QrCodigo {
    /** Por versión (nivel M): [codewords EC por bloque, [[cantidad de bloques, codewords de datos], ...]] */
    private const BLOQUES_M = [
        1 => [10, [[1, 16]]], 2 => [16, [[1, 28]]], 3 => [26, [[1, 44]]], 4 => [18, [[2, 32]]],
        5 => [24, [[2, 43]]], 6 => [16, [[4, 27]]], 7 => [18, [[4, 31]]], 8 => [22, [[2, 38], [2, 39]]],
        9 => [22, [[3, 36], [2, 37]]], 10 => [26, [[4, 43], [1, 44]]],
    ];
    private const ALINEACION = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34],
        7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    private int $n;
    private array $m = [];     // módulos: true = oscuro
    private array $fijo = [];  // módulos de patrón de función (no se enmascaran)

    public static function svg(string $texto, int $px = 220, string $oscuro = '#000', string $claro = '#fff'): string {
        $mat = self::matriz($texto);
        $n = count($mat);
        $borde = 4;
        $total = $n + 2 * $borde;
        $d = '';
        foreach ($mat as $y => $fila) {
            foreach ($fila as $x => $v) {
                if ($v) $d .= 'M' . ($x + $borde) . ',' . ($y + $borde) . 'h1v1h-1z';
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $total . ' ' . $total . '" width="' . $px . '" height="' . $px
            . '" shape-rendering="crispEdges" role="img" aria-label="Código QR"><rect width="100%" height="100%" fill="' . $claro . '"/>'
            . '<path fill="' . $oscuro . '" d="' . $d . '"/></svg>';
    }

    public static function matriz(string $texto): array {
        $q = new self();
        return $q->generar($texto);
    }

    private function generar(string $texto): array {
        $datos = array_values(unpack('C*', $texto) ?: []);
        $version = 0;
        foreach (self::BLOQUES_M as $v => [$ec, $grupos]) {
            $capacidad = array_sum(array_map(fn($g) => $g[0] * $g[1], $grupos));
            $bitsCuenta = $v < 10 ? 8 : 16;
            if (4 + $bitsCuenta + 8 * count($datos) <= 8 * $capacidad) { $version = $v; break; }
        }
        if (!$version) throw new InvalidArgumentException('Texto demasiado largo para el código QR.');

        $codewords = $this->codificar($datos, $version);
        $this->n = 17 + 4 * $version;
        $this->m = array_fill(0, $this->n, array_fill(0, $this->n, false));
        $this->fijo = $this->m;
        $this->patronesFuncion($version);
        $this->colocarDatos($codewords);

        // Elegir la máscara con menor penalización.
        $mejor = null;
        $mejorPen = PHP_INT_MAX;
        $base = $this->m;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->m = $base;
            $this->aplicarMascara($mask);
            $this->formato($mask);
            $pen = $this->penalizacion();
            if ($pen < $mejorPen) { $mejorPen = $pen; $mejor = $this->m; }
        }
        return array_map(fn($fila) => array_map('intval', $fila), $mejor);
    }

    // ------------------------------------------------------------------ datos + Reed-Solomon

    private function codificar(array $datos, int $version): array {
        [$ec, $grupos] = self::BLOQUES_M[$version];
        $capacidad = array_sum(array_map(fn($g) => $g[0] * $g[1], $grupos));
        $bits = '0100' . str_pad(decbin(count($datos)), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT);
        foreach ($datos as $b) $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        $bits .= str_repeat('0', min(4, 8 * $capacidad - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $bytes = array_map('bindec', str_split($bits, 8));
        for ($i = 0; count($bytes) < $capacidad; $i++) $bytes[] = $i % 2 ? 0x11 : 0xEC;

        // Dividir en bloques, calcular EC y entrelazar.
        $bloquesDatos = $bloquesEc = [];
        $pos = 0;
        $gen = $this->generadorRs($ec);
        foreach ($grupos as [$cant, $largo]) {
            for ($b = 0; $b < $cant; $b++) {
                $bloque = array_slice($bytes, $pos, $largo);
                $pos += $largo;
                $bloquesDatos[] = $bloque;
                $bloquesEc[] = $this->restoRs($bloque, $gen);
            }
        }
        $salida = [];
        $maxLargo = max(array_map('count', $bloquesDatos));
        for ($i = 0; $i < $maxLargo; $i++) foreach ($bloquesDatos as $bl) if (isset($bl[$i])) $salida[] = $bl[$i];
        for ($i = 0; $i < $ec; $i++) foreach ($bloquesEc as $bl) $salida[] = $bl[$i];
        return $salida;
    }

    private static array $exp = [];
    private static array $log = [];

    private static function gf(): void {
        if (self::$exp) return;
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) $x ^= 0x11D;
        }
        for ($i = 255; $i < 512; $i++) self::$exp[$i] = self::$exp[$i - 255];
    }

    private static function mul(int $a, int $b): int {
        if ($a === 0 || $b === 0) return 0;
        self::gf();
        return self::$exp[self::$log[$a] + self::$log[$b]];
    }

    /** Polinomio generador de grado $grado (coeficientes de mayor a menor, sin el término líder). */
    private function generadorRs(int $grado): array {
        self::gf();
        $g = [1];
        for ($i = 0; $i < $grado; $i++) {
            $nuevo = array_fill(0, count($g) + 1, 0);
            foreach ($g as $j => $c) {
                $nuevo[$j] ^= $c;
                $nuevo[$j + 1] ^= self::mul($c, self::$exp[$i]);
            }
            $g = $nuevo;
        }
        return array_slice($g, 1);
    }

    private function restoRs(array $datos, array $gen): array {
        $resto = array_fill(0, count($gen), 0);
        foreach ($datos as $b) {
            $factor = $b ^ array_shift($resto);
            $resto[] = 0;
            foreach ($gen as $i => $c) $resto[$i] ^= self::mul($c, $factor);
        }
        return $resto;
    }

    // ------------------------------------------------------------------ patrones y ubicación

    private function poner(int $fila, int $col, bool $oscuro, bool $fijo = true): void {
        $this->m[$fila][$col] = $oscuro;
        if ($fijo) $this->fijo[$fila][$col] = true;
    }

    private function patronesFuncion(int $version): void {
        $n = $this->n;
        // Patrones de tiempo
        for ($i = 0; $i < $n; $i++) {
            $this->poner(6, $i, $i % 2 === 0);
            $this->poner($i, 6, $i % 2 === 0);
        }
        // Buscadores con separador
        foreach ([[3, 3], [3, $n - 4], [$n - 4, 3]] as [$cf, $cc]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $f = $cf + $dy; $c = $cc + $dx;
                    if ($f < 0 || $c < 0 || $f >= $n || $c >= $n) continue;
                    $dist = max(abs($dx), abs($dy));
                    $this->poner($f, $c, $dist !== 2 && $dist !== 4);
                }
            }
        }
        // Alineación
        $pos = self::ALINEACION[$version];
        $k = count($pos);
        foreach ($pos as $i => $f) {
            foreach ($pos as $j => $c) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $k - 1) || ($i === $k - 1 && $j === 0)) continue;
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->poner($f + $dy, $c + $dx, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        // Reservar zonas de formato (se escriben después) y módulo oscuro
        $this->formato(0);
        // Información de versión (v >= 7)
        if ($version >= 7) {
            $rem = $version;
            for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            $bits = ($version << 12) | $rem;
            for ($i = 0; $i < 18; $i++) {
                $bit = (($bits >> $i) & 1) === 1;
                $a = $n - 11 + $i % 3;
                $b = intdiv($i, 3);
                $this->poner($b, $a, $bit);
                $this->poner($a, $b, $bit);
            }
        }
    }

    /** Escribe los 15 bits de formato (nivel M) para la máscara dada, en sus dos copias. */
    private function formato(int $mask): void {
        $n = $this->n;
        $datos = (0b00 << 3) | $mask; // nivel M = 00
        $rem = $datos;
        for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        $bits = (($datos << 10) | $rem) ^ 0x5412;
        $bit = fn($i) => (($bits >> $i) & 1) === 1;
        for ($i = 0; $i <= 5; $i++) $this->poner($i, 8, $bit($i));
        $this->poner(7, 8, $bit(6));
        $this->poner(8, 8, $bit(7));
        $this->poner(8, 7, $bit(8));
        for ($i = 9; $i < 15; $i++) $this->poner(8, 14 - $i, $bit($i));
        for ($i = 0; $i < 8; $i++) $this->poner(8, $n - 1 - $i, $bit($i));
        for ($i = 8; $i < 15; $i++) $this->poner($n - 15 + $i, 8, $bit($i));
        $this->poner($n - 8, 8, true); // módulo oscuro
    }

    private function colocarDatos(array $codewords): void {
        $n = $this->n;
        $total = count($codewords) * 8;
        $i = 0;
        for ($der = $n - 1; $der >= 1; $der -= 2) {
            if ($der === 6) $der = 5;
            for ($vert = 0; $vert < $n; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $c = $der - $j;
                    $subiendo = (($der + 1) & 2) === 0;
                    $f = $subiendo ? $n - 1 - $vert : $vert;
                    if (!$this->fijo[$f][$c] && $i < $total) {
                        $this->m[$f][$c] = (($codewords[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function aplicarMascara(int $mask): void {
        for ($f = 0; $f < $this->n; $f++) {
            for ($c = 0; $c < $this->n; $c++) {
                if ($this->fijo[$f][$c]) continue;
                $inv = match ($mask) {
                    0 => ($f + $c) % 2 === 0,
                    1 => $f % 2 === 0,
                    2 => $c % 3 === 0,
                    3 => ($f + $c) % 3 === 0,
                    4 => (intdiv($f, 2) + intdiv($c, 3)) % 2 === 0,
                    5 => ($f * $c) % 2 + ($f * $c) % 3 === 0,
                    6 => (($f * $c) % 2 + ($f * $c) % 3) % 2 === 0,
                    7 => (($f + $c) % 2 + ($f * $c) % 3) % 2 === 0,
                };
                if ($inv) $this->m[$f][$c] = !$this->m[$f][$c];
            }
        }
    }

    /** Penalización según la norma (reglas 1 a 4), para elegir la mejor máscara. */
    private function penalizacion(): int {
        $n = $this->n;
        $m = $this->m;
        $pen = 0;
        $oscuros = 0;
        for ($pase = 0; $pase < 2; $pase++) {
            for ($a = 0; $a < $n; $a++) {
                $linea = [];
                for ($b = 0; $b < $n; $b++) $linea[] = $pase ? $m[$b][$a] : $m[$a][$b];
                // Regla 1: rachas de 5 o más del mismo color
                $racha = 1;
                for ($b = 1; $b <= $n; $b++) {
                    if ($b < $n && $linea[$b] === $linea[$b - 1]) { $racha++; continue; }
                    if ($racha >= 5) $pen += 3 + ($racha - 5);
                    $racha = 1;
                }
                // Regla 3: patrón parecido a un buscador (1:1:3:1:1 con 4 claros a un lado)
                $s = implode('', array_map('intval', $linea));
                $pen += 40 * (substr_count($s, '10111010000') + substr_count($s, '00001011101'));
            }
        }
        for ($f = 0; $f < $n; $f++) {
            for ($c = 0; $c < $n; $c++) {
                if ($m[$f][$c]) $oscuros++;
                // Regla 2: bloques 2x2 del mismo color
                if ($f < $n - 1 && $c < $n - 1 && $m[$f][$c] === $m[$f][$c + 1] && $m[$f][$c] === $m[$f + 1][$c] && $m[$f][$c] === $m[$f + 1][$c + 1]) $pen += 3;
            }
        }
        // Regla 4: proporción de módulos oscuros lejos del 50%
        $pen += 10 * intdiv(abs(intdiv($oscuros * 100, $n * $n) - 50), 5);
        return $pen;
    }
}
