<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Copia privada y transitoria del XLSX nominal que YA se prevalidó.
 * No almacena cédulas adicionales ni crea registros de inventario.
 * El directorio está fuera de htdocs; solo se usa una referencia aleatoria.
 */
final class P4NominalStagedFile
{
    public const TTL = 3600;
    private const LIMIT = 15728640;

    private static function directory(): string
    {
        return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
            . 'sindesg_p4_nominal_private';
    }

    /** @return array{token:string,sha256:string,filename:string,extension:string,size:int} */
    public static function stage(string $uploaded, string $filename): array
    {
        $filename = basename($filename);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'csv'], true)
            || !is_uploaded_file($uploaded)
            || !is_file($uploaded)) {
            throw new InvalidArgumentException('Archivo de prevalidación no admitido.');
        }
        $size = filesize($uploaded);
        if ($size === false || $size <= 0 || $size > self::LIMIT) {
            throw new InvalidArgumentException('Archivo fuera de los límites permitidos.');
        }
        $dir = self::directory();
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo preparar el archivo temporal privado.');
        }
        self::cleanup();
        $token = bin2hex(random_bytes(24));
        $target = $dir . DIRECTORY_SEPARATOR . $token . '.' . $extension;
        if (!move_uploaded_file($uploaded, $target)) {
            throw new RuntimeException('No se pudo conservar temporalmente el archivo prevalidado.');
        }
        @chmod($target, 0600);
        $hash = hash_file('sha256', $target);
        if (!is_string($hash)) {
            @unlink($target);
            throw new RuntimeException('No se pudo verificar la copia temporal.');
        }
        return [
            'token' => $token,
            'sha256' => $hash,
            'filename' => $filename,
            'extension' => $extension,
            'size' => (int) $size,
        ];
    }

    /** Validación completa en cada acción posterior. Nunca aceptar rutas del cliente. */
    public static function resolve(array $meta, string $expectedHash): string
    {
        $token = (string) ($meta['token'] ?? '');
        $ext = (string) ($meta['extension'] ?? '');
        if (!preg_match('/^[a-f0-9]{48}$/D', $token)
            || !in_array($ext, ['xlsx', 'csv'], true)
            || !preg_match('/^[a-f0-9]{64}$/D', $expectedHash)
            || !hash_equals($expectedHash, (string) ($meta['sha256'] ?? ''))) {
            throw new InvalidArgumentException('Archivo conciliado inválido. Prevalide nuevamente.');
        }
        $path = self::directory() . DIRECTORY_SEPARATOR . $token . '.' . $ext;
        $size = is_file($path) ? filesize($path) : false;
        $mtime = is_file($path) ? filemtime($path) : false;
        if ($size === false || $size <= 0 || $size > self::LIMIT
            || $mtime === false || $mtime < time() - self::TTL
            || !hash_equals($expectedHash, (string) hash_file('sha256', $path))) {
            throw new InvalidArgumentException(
                'El Excel conciliado venció o cambió. Prevalide nuevamente para continuar.'
            );
        }
        return $path;
    }

    public static function delete(array $meta): void
    {
        $token = (string) ($meta['token'] ?? '');
        $ext = (string) ($meta['extension'] ?? '');
        if (preg_match('/^[a-f0-9]{48}$/D', $token)
            && in_array($ext, ['xlsx', 'csv'], true)) {
            $path = self::directory() . DIRECTORY_SEPARATOR . $token . '.' . $ext;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private static function cleanup(): void
    {
        foreach (glob(self::directory() . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_file($path) && (int) filemtime($path) < time() - self::TTL) {
                @unlink($path);
            }
        }
    }
}
