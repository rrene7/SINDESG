<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\DistributionPreparationGroups;

$check = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$rows = [];
for ($i = 1; $i <= 516; $i++) {
    $offset = ($i - 1) % 16;
    $rows[] = [
        'request_item_id' => $i,
        'product_variant_id' => 100 + $offset,
        'product_name' => 'Botas Tácticas',
        'category_name' => 'Botas',
        'variant_code' => 'SND-V-' . (100 + $offset),
        'size_name' => (string) (5 + $offset / 2),
        'beneficiary_label_snapshot' => 'Funcionario ' . $i,
        'quantity_requested' => 1,
        'quantity_approved' => 1,
        'quantity_dispatched' => 0,
        'remaining_quantity' => 1,
        'central_stock' => 982,
    ];
}
$groups = DistributionPreparationGroups::groups($rows);
$check(count($groups) === 16, 'Los 516 destinatarios deben quedar agrupados en 16 tallas.');
$check(abs(array_sum(array_column($groups, 'pending')) - 516.0) < 0.00001,
    'Agrupar no cambia unidades pendientes.');
$seen = [];
foreach ($groups as $group) {
    $check(count($group['items']) > 0, 'Un grupo no debe carecer de beneficiarios.');
    $check((float) $group['pending'] === (float) count($group['items']),
        'Los totales agrupados no corresponden a la nómina.');
    foreach ($group['items'] as $item) {
        $id = (int) $item['request_item_id'];
        $check(!isset($seen[$id]), 'Renglón nominal duplicado.');
        $seen[$id] = true;
    }
}
$check(count($seen) === 516, 'Se perdieron funcionarios al agrupar.');

try {
    DistributionPreparationGroups::groups([$rows[0],$rows[0]]);
    throw new RuntimeException('Debe bloquear identificadores nominales duplicados.');
} catch (InvalidArgumentException $expected) {
    // La agrupación se niega a perder o duplicar una identidad.
}

$distributionRequest = ['id'=>5, 'p4_name'=>'15 Zona Policial - Don Bosco'];
$distributionRequestItems = $rows;
$distributionGroups = $groups;
$employeeSearchUrl = '/SINDESG/public/api/employees.php';

ob_start();
require dirname(__DIR__) . '/app/Views/distribution/form.php';
$html = (string) ob_get_clean();

$check(substr_count($html, 'data-transfer-group') >= 16
    && substr_count($html, 'class="distribution-group-row"') === 16,
    'Debe haber 16 filas principales por talla.');
$check(substr_count($html, 'name="transfer_quantity[') === 516,
    'Todos los funcionarios deben conservar exactamente un input nominal.');
$check(substr_count($html, 'data-transfer-item') === 516
    && substr_count($html, 'value="0" required') === 516,
    'Las 516 asignaciones deben iniciar en cero, sin preparación accidental.');
$check(substr_count($html, '<details class="distribution-group-detail">') === 16,
    'Cada talla debe ofrecer detalle nominal desplegable.');
$check(substr_count($html, 'data-transfer-group-quantity') === 16
    && substr_count($html, 'data-transfer-apply') === 16,
    'Debe existir una decisión por talla, con aplicación a funcionarios.');
$check(str_contains($html, 'data-transfer-validate')
    && str_contains($html, 'Validar sin guardar')
    && str_contains($html, 'data-transfer-submit')
    && str_contains($html, 'csrf_token')
       === false /* rendered CSRF becomes a token, not PHP source */ ,
    'Las acciones deben separar validación sin escritura de preparación definitiva.');
$check(str_contains($html, 'name="_token"')
    && str_contains($html, 'name="request_id"')
    && str_contains($html, 'name="prepared_by_employee_id"'),
    'El formulario debe mantener CSRF, solicitud y funcionario preparador.');
$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $data = file_get_contents($root . '/' . $path);
    if (!is_string($data)) throw new RuntimeException('Archivo no encontrado: ' . $path);
    return $data;
};
$js = $read('public/assets/js/distribution.js');
$css = $read('public/assets/css/distribution.css');
$controller = $read('app/Controllers/DistributionController.php');
$repository = $read('app/Repositories/DistributionRepository.php');
$service = $read('app/Services/DistributionService.php');
$bootstrap = $read('app/bootstrap.php');
$check(str_contains($controller, 'DistributionPreparationGroups::groups($items)')
    && str_contains($js, "form.addEventListener('submit'")
    && str_contains($js, 'data-transfer-validate')
    && str_contains($js, 'No se ha creado ninguna transferencia')
    && str_contains($js, 'data-transfer-item')
    && str_contains($js, "window.confirm('Preparar transferencia"),
    'La validación local debe bloquear envíos no revisados y conservar el POST nominal.');
$check(str_contains($css, '.distribution-size-table .distribution-group-row')
    && str_contains($css, '@media(max-width:760px)'),
    'La tabla por tallas debe adaptarse a tarjetas móviles.');
$check(str_contains($repository, 'requiredByVariant')
    && str_contains($repository, '$requiredByVariant[$variantId]')
    && str_contains($repository, 'foreach ($requiredByVariant as $variantId => $required)')
    && str_contains($repository, 'FOR UPDATE')
    && str_contains($repository, 'quantity_dispatched = quantity_dispatched +')
    && str_contains($service, "transfer_quantity"),
    'El servidor debe preservar el kardex, bloqueo transaccional, stock total por talla y filas individuales.');
$check(sindesg_role_action_allowed('distribution','new','GET',['role_codes'=>['CENTRAL']])
    && !sindesg_role_action_allowed('distribution','prepare','POST',['role_codes'=>['P4']]),
    'Roles Central/P4 no deben perder el límite operacional.');
echo "[OK] Distribucion por 16 tallas y 516 destinatarios, seleccion nominal intacta, stock agrupado y validacion sin guardar.\n";
