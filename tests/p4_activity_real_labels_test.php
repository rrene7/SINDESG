<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Services/P4QuantityDisplay.php';
require dirname(__DIR__) . '/app/Services/P4ActivityLabel.php';

use App\Services\P4ActivityLabel;

$example = [
 'person_label'=>'Funcionario Receptor',
 'product_name'=>'Botas Tácticas',
 'size_name'=>'9.5',
 'total_quantity'=>1.000,
 'item_count'=>1,
 'status'=>'confirmed',
 'code'=>'DSP-000004'
];
$title = P4ActivityLabel::title('deliveries',$example);
if ($title !== 'Entrega a Funcionario Receptor · Botas Tácticas · Talla 9.5 · 1 unidad'
    || str_contains($title,'DSP-')
    || $example['code'] !== 'DSP-000004') {
    throw new RuntimeException('P4 delivery activity label is not based on historic data.');
}
if (P4ActivityLabel::title('transfers',[
    'status'=>'received','total_quantity'=>1000,
    'item_count'=>2,'product_name'=>'Botas Tácticas'
]) !== 'Transferencia recibida de Central · Botas Tácticas · 2 renglones · 1 000 unidades') {
    throw new RuntimeException('Received transfer title is ambiguous.');
}
if (P4ActivityLabel::title('requests',[
    'person_label'=>'Responsable Pacora','total_quantity'=>2,
    'status'=>'submitted'
]) !== 'Solicitud de Responsable Pacora · 2 unidades') {
    throw new RuntimeException('Request title does not identify its author.');
}
if (P4ActivityLabel::title('deliveries', ['person_label'=>'', 'total_quantity'=>0])
    !== 'Entrega individual de dotación') {
    throw new RuntimeException('Missing historical snapshot was invented.');
}
$root = dirname(__DIR__);
$dashboard = (string)file_get_contents($root.'/app/Views/dashboard-p4.php');
if (!str_contains($dashboard,'P4ActivityLabel::title(')) {
    throw new RuntimeException('P4 dashboard does not use real operational labels.');
}
echo "[OK] Scoped P4 activity uses real persons, products and accurate quantities.\n";
