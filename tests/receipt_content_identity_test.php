<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\ReceiptContentSummary;

$check = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$boots = [];
foreach (range(1, 17) as $i) {
    $boots[] = [
        'product_id' => 27,
        'product_name' => 'Botas Tácticas',
        'size_name' => (string) (5 + $i * .5),
        'quantity' => $i === 1 ? 1537 : ($i === 2 ? 1200 : 866.6666666666666),
        'category_name' => 'Botas',
        'variant_code' => 'BT-' . $i,
        'brand_name' => 'Prueba',
        'institutional_code' => 'BT-' . $i,
        'notes' => null,
    ];
}
// Exact test quantity: 1537 + 1200 + 15*866.666666... = 15737.
$info = ReceiptContentSummary::fromItems($boots);
$check($info['product_count'] === 1
    && $info['primary_name'] === 'Botas Tácticas'
    && count($info['products']) === 1
    && $info['products'][0]['variant_count'] === 17
    && abs($info['total_quantity'] - 15737.0) < .00001,
    'Los 17 renglones de botas deben presentar un artículo y el total real.');
$check(abs($info['products'][0]['quantity'] - $info['total_quantity']) < .00001,
    'El subtotal del artículo no coincide con su lote.');
$mult = ReceiptContentSummary::fromItems([
    ['product_id' => 27, 'product_name' => 'Botas Tácticas', 'quantity' => 12],
    ['product_id' => 28, 'product_name' => 'Camisas', 'quantity' => 4],
    ['product_id' => 27, 'product_name' => 'Botas Tácticas', 'quantity' => 3],
]);
$check($mult['product_count'] === 2 && $mult['primary_name'] === null
    && $mult['products'][0]['quantity'] === 15.0 && $mult['products'][1]['quantity'] === 4.0
    && $mult['total_quantity'] === 19.0,
    'Un lote mixto debe mostrar dos artículos con totales desglosados.');
$empty = ReceiptContentSummary::fromItems([]);
$check($empty['primary_name'] === null && $empty['product_count'] === 0
    && $empty['total_quantity'] === 0.0, 'El lote sin artículos no puede inventar un nombre.');

$receipt = [
    'id'=>2,
    'receipt_code'=>'REC-000002',
    'lot_code'=>'LOT-000002',
    'status'=>'confirmed',
    'source_type'=>'purchase',
    'supplier_or_origin'=>'MÁX GEAR, S.A.',
    'received_at'=>'2026-10-14 12:26:00',
    'warehouse_name'=>'Almacén Central',
    'document_number'=>'DA-038-2025',
    'received_by_label'=>'Funcionario de prueba',
    'received_by_document_snapshot'=>'N/D',
    'received_by_position_snapshot'=>'N/D',
    'received_by_rank_snapshot'=>'N/D',
    'received_by_unit_name_snapshot'=>'Servicios Generales',
    'notes'=>null,
];
$sourceTypes = ['purchase'=>'Compra'];
$variantOptions = [];
$receiptVerification = [
    'all_verified'=>true,'matched'=>0,'expected'=>0,'rows'=>[],
];
$render = static function (array $items) use (&$receipt, &$sourceTypes, &$receiptVerification, &$variantOptions): string {
    $receiptItems = $items;
    ob_start();
    require dirname(__DIR__) . '/app/Views/receipts/show.php';
    return (string) ob_get_clean();
};
$html = $render($boots);
$check(str_contains($html, 'Botas Tácticas') && str_contains($html, 'ARTÍCULO RECIBIDO')
    && str_contains($html, '15 737')
    && str_contains($html, '17 tallas o variantes registradas'),
    'La ficha de una recepción debe identificar el artículo y su total.');
$check(!str_contains($html, 'Camisas'),
    'La ficha de botas no debe mostrar artículos ausentes.');
$htmlMixed = $render([
    $boots[0],
    [
        'product_id'=>28,'product_name'=>'Camisas <script>alert(1)</script>',
        'size_name'=>'M', 'quantity'=>6, 'category_name'=>'Camisas',
        'variant_code'=>'CAM-M', 'brand_name'=>'Prueba',
        'institutional_code'=>'CAM-M', 'notes'=>null
    ],
]);
$check(str_contains($htmlMixed, '2 artículos diferentes')
    && str_contains($htmlMixed, 'Botas Tácticas')
    && str_contains($htmlMixed, 'Camisas')
    && !str_contains($htmlMixed, '<script>alert(1)</script>')
    && str_contains($htmlMixed, '&lt;script&gt;'),
    'Un lote mixto debe listar los artículos y escapar nombres no confiables.');
$css = file_get_contents(dirname(__DIR__) . '/public/assets/css/receipts.css');
$check(is_string($css) && str_contains($css, '.receipt-product-identity')
    && str_contains($css, '@media(max-width:760px)'),
    'La identificación del producto debe ser legible también en celulares.');
echo "[OK] Recepción: artículo real visible, botas 15 737, lote mixto, nombres escapados y vista móvil.\n";
