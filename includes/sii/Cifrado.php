<?php
/**
 * Cifrado simétrico (AES-256-GCM) para guardar secretos como la Clave Tributaria.
 *
 * La llave se genera al azar la primera vez y queda en config/clave_cifrado.key,
 * propia de cada equipo (no viaja en respaldos de la base ni en el instalador).
 * Protege el secreto si alguien obtiene un respaldo .sql o la base de datos, pero
 * no frente a alguien con acceso completo a este computador.
 */
class Cifrado {
    private const ARCHIVO_LLAVE = __DIR__ . '/../../config/clave_cifrado.key';
    private const PREFIJO = 'gcm1:';

    private static function llave(): string {
        if (!is_file(self::ARCHIVO_LLAVE)) {
            $nueva = random_bytes(32);
            if (file_put_contents(self::ARCHIVO_LLAVE, base64_encode($nueva), LOCK_EX) === false) {
                throw new RuntimeException('No se pudo crear la llave de cifrado en config/.');
            }
            return $nueva;
        }
        $llave = base64_decode(trim((string)file_get_contents(self::ARCHIVO_LLAVE)), true);
        if ($llave === false || strlen($llave) !== 32) {
            throw new RuntimeException('La llave de cifrado (config/clave_cifrado.key) está dañada.');
        }
        return $llave;
    }

    public static function cifrar(string $texto): string {
        $iv = random_bytes(12);
        $tag = '';
        $cifrado = openssl_encrypt($texto, 'aes-256-gcm', self::llave(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cifrado === false) throw new RuntimeException('No se pudo cifrar el dato.');
        return self::PREFIJO . base64_encode($iv . $tag . $cifrado);
    }

    public static function descifrar(string $valor): string {
        if (strpos($valor, self::PREFIJO) !== 0) {
            throw new RuntimeException('El dato no está cifrado con el formato esperado.');
        }
        $bin = base64_decode(substr($valor, strlen(self::PREFIJO)), true);
        if ($bin === false || strlen($bin) < 29) throw new RuntimeException('El dato cifrado está dañado.');
        $texto = openssl_decrypt(substr($bin, 28), 'aes-256-gcm', self::llave(), OPENSSL_RAW_DATA, substr($bin, 0, 12), substr($bin, 12, 16));
        if ($texto === false) {
            throw new RuntimeException('No se pudo descifrar: la llave de este equipo no corresponde. Vuelve a ingresar la clave en Configuración → Boleta electrónica.');
        }
        return $texto;
    }
}
