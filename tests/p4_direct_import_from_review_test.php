<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\P4NominalStagedFile;

$dirMethod = new ReflectionMethod(P4NominalStagedFile::class, 'directory');
$dir = $dirMethod->invoke(null);
if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
    throw new RuntimeException('No se pudo crear el directorio temporal de prueba.');
}
$token = bin2hex(random_bytes(24));
$path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $token . '.csv';
$bytes = "POSICION,NOMBRE,TALLA\n17381,CARLOS LARA,9\n";
if (file_put_contents($path, $bytes) === false) {
    throw new RuntimeException('No se pudo preparar archivo de prueba.');
}
$metadata = [
    'token' => $token,
    'extension' => 'csv',
    'filename' => 'dotacion.csv',
    'sha256' => hash('sha256', $bytes),
];
try {
    if (P4NominalStagedFile::resolve($metadata, $metadata['sha256']) !== $path) {
        throw new RuntimeException('El archivo prevalidado no pudo reabrirse.');
    }
    $bad = array_replace($metadata, ['token' => '../' . $token]);
    $rejected = false;
    try {
        P4NominalStagedFile::resolve($bad, $metadata['sha256']);
    } catch (InvalidArgumentException $e) {
        $rejected = true;
    }
    if (!$rejected) {
        throw new RuntimeException('Se aceptó una ruta inyectada.');
    }
    $rejected = false;
    try {
        P4NominalStagedFile::resolve($metadata, str_repeat('0', 64));
    } catch (InvalidArgumentException $e) {
        $rejected = true;
    }
    if (!$rejected) {
        throw new RuntimeException('El SHA de origen pudo reemplazarse.');
    }
    file_put_contents($path, 'DATOS ALTERADOS');
    $rejected = false;
    try {
        P4NominalStagedFile::resolve($metadata, $metadata['sha256']);
    } catch (InvalidArgumentException $e) {
        $rejected = true;
    }
    if (!$rejected) {
        throw new RuntimeException('Un Excel modificado no debe poder importarse.');
    }
} finally {
    P4NominalStagedFile::delete($metadata);
}
if (is_file($path)) {
    throw new RuntimeException('No se borró el archivo de uso único.');
}

$root = dirname(__DIR__);
$controller = (string) file_get_contents($root . '/app/Controllers/RequestsController.php');
$view = (string) file_get_contents($root . '/app/Views/requests/show.php');
if (!str_contains($controller, "'import-from-nominal-preview'")
    || !str_contains($controller, 'P4NominalStagedFile::stage(')
    || !str_contains($controller, 'P4NominalStagedFile::resolve(')
    || !str_contains($controller, 'P4NominalStagedFile::delete(')
    || !str_contains($controller, '$session[\'report\'] = $preview;')
    || !str_contains($controller, "'user_id' => (int) (sindesg_user()['id'] ?? 0)")
    || !str_contains($controller, '$this->bulk->importWorkbook(')
    || !str_contains($view, 'import-from-nominal-preview')
    || !str_contains($view, 'Cargar al sistema')
    || !str_contains($view, 'name="accept_document_gap"')
    || !str_contains($view, 'name="allow_partial"')
) {
    throw new RuntimeException('No quedó habilitada la carga directa después de validar incidencias.');
}
echo "[OK] Excel P4 prevalidado puede cargarse en una acción sin re-subirlo; SHA y sesión protegidos.\n";
