<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Services\BootsP4WorkbookPreview;
use App\Services\NominalBulkRequestService;

$service = (new ReflectionClass(NominalBulkRequestService::class))->newInstanceWithoutConstructor();
$compare = new ReflectionMethod(NominalBulkRequestService::class, 'namesCompatible');
$cases = [
    ['ANTHONY BETHANCOURT', 'ANTONY FERNANDO', 'BETHANCOURTT', true],
    ['JEANPAUL ABREGO', 'JEANPOL JAFET', 'ABREGO BARRIA', true],
    // Exactamente dos letras del primer nombre intercambiadas, apellido
    // confirmado. Posición vigente y única se verifica ANTES en el servicio.
    ['ELMER TAMAYO', 'ERMEL', 'TAMAYO CEDENO', true],
    ['AZALE TYLER', 'AZAEL LEE', 'TYLER LAM', true],
    ['JHONS RODRIGUEZ', 'JOHNS JUNIOR', 'RODRIGUEZ PEREZ', true],
    ['AZALE WHITE', 'AZAEL LEE', 'TYLER LAM', false],
    ['OBETH WHITE', 'OBED ABDIAS', 'WHITE RODRIGUEZ', false],
    ['BERNANDIMO PANEZO', 'BERNARDINO', 'PANESO ZABUGARA', false],
    ['CARLOS LARA', 'OMAR', 'BARRERA MARTINEZ', false],
    ['EVERT MONTERO', 'MANUEL ALFONSO', 'HERNANDEZ NAVAS', false],
];
foreach ($cases as [$excel, $first, $last, $expected]) {
    if ($compare->invoke($service, $excel, $first, $last) !== $expected) {
        throw new RuntimeException('Coincidencia inesperada: ' . $excel);
    }
}
$transpose = new ReflectionMethod(NominalBulkRequestService::class, 'isSingleTransposition');
foreach ([
    ['ELMER', 'ERMEL', true],
    ['AZALE', 'AZAEL', true],
    ['JHONS', 'JOHNS', true],
    ['CARLOS', 'OMAR', false],
    ['EVERT', 'MANUEL', false],
    ['LUIS', 'LUIS', false],
    ['OBETH', 'OBED', false],
    ['AZALE', 'AZAELS', false],
] as [$left, $right, $expected]) {
    if ($transpose->invoke(null, $left, $right) !== $expected) {
        throw new RuntimeException('Intercambio ortográfico inseguro: ' . $left . ' / ' . $right);
    }
}
$preview = (new BootsP4WorkbookPreview())->analyze([[
    'name' => 'Dotacion P4',
    'rows' => [
        ['REQUISICION Nº 123456'],
        ['CANTIDAD DE UNIDADES A DOTAR: 723'],
        ['', 'RANGO', 'POSICIÓN', 'NOMBRE', 'APELLIDO', 'TALLA', 'FIRMA'],
        ['', 'AGENTE', '24445', 'SERGIO', 'MONROY', '8 5', ''],
        ['', 'AGENTE', '29508', 'JEANPAUL', 'ABREGO', '9.5', ''],
    ],
]], 'nominal.xlsx');
if ($preview['document']['declared_quantity'] !== 723
    || $preview['nominal_count'] !== 2
    || $preview['nominal'][0]['size'] !== '8.5'
    || $preview['nominal'][0]['raw_size'] !== '8 5'
    || !in_array('quantity', array_column($preview['issues'], 'type'), true)
    || !in_array('size_format', array_column($preview['issues'], 'type'), true)) {
    throw new RuntimeException('No se detectó descuadre documental o formato de talla.');
}
$code = (string) file_get_contents(dirname(__DIR__) . '/app/Services/NominalBulkRequestService.php');
$view = (string) file_get_contents(dirname(__DIR__) . '/app/Views/requests/show.php');
if (!str_contains($code, 'if ($gap !== 0 && (!$allowPartial || $fullyVerified))')
    || !str_contains($code, "'size_format'")
    || !str_contains($view, 'pendiente documental')
    || !str_contains($view, 'name="accept_document_gap"')) {
    throw new RuntimeException('Falta control documental.');
}
echo "[OK] Conciliación ortográfica, talla 8 5 y cantidad declarada distinta.\n";
