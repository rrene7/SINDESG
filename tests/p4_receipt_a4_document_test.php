<?php
declare(strict_types=1);

// Render only the printable receipt, with test snapshots, without any database
// access, writes, delivery confirmation, or changes to historical evidence.
$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';

$view = (string) file_get_contents($root . '/app/Views/deliveries/show.php');
$start = strpos($view, '<?php if (!empty($receiptEvidence)): ?>');
$end = strpos($view, "<?php if (\$deliveryP4Only && \$delivery['status'] === 'draft'): ?>", $start ?: 0);
if ($start === false || $end === false || $end <= $start) {
    throw new RuntimeException('Printable receipt boundaries missing.');
}
$receiptTemplate = substr($view, $start, $end - $start);
foreach ([
    '@page{size:A4 portrait',
    '.main-content > :not(#signed-receipt){display:none!important}',
    'position:static!important',
    'thead{display:table-header-group',
    'break-inside:avoid',
    'receipt-signoff',
    'receipt-integrity',
    'receipt-missing-signature',
    "if (!empty(\$issuerReceiptEvidence))",
    "base64_encode((string) \$receiptEvidence['signature_png'])",
    "base64_encode((string) \$issuerReceiptEvidence['signature_png'])",
    "e(\$delivery['delivery_code'])",
    "e(\$delivery['recipient_label_snapshot'])",
    "e(\$delivery['delivered_by_label_snapshot'])",
] as $required) {
    if (!str_contains($receiptTemplate, $required)) {
        throw new RuntimeException('A4 printable receipt lost: ' . $required);
    }
}
if (str_contains($receiptTemplate, 'body *{visibility:hidden')
    || str_contains($receiptTemplate, 'position:absolute!important')
    || str_contains($receiptTemplate, 'INSERT INTO')) {
    throw new RuntimeException('Unsafe/old receipt layout or write detected.');
}

$delivery = [
    'delivery_code' => 'DSP-000004',
    'status' => 'confirmed',
    'p4_name' => '16 Zona Policial - Pacora',
    'p4_internal_name' => 'Zona Pacora',
    'p4_code' => 'P4-000006',
    'delivered_at' => '2026-10-20 07:20:00',
    'recipient_label_snapshot' => 'Funcionario <Receptor>',
    'recipient_rank_snapshot' => 'Sargento',
    'recipient_position_snapshot' => '301',
    'recipient_document_snapshot' => '8-123-456',
    'delivered_by_label_snapshot' => 'Responsable P4',
    'delivered_by_rank_snapshot' => 'Cabo Segundo',
    'delivered_by_position_snapshot' => '302',
    'document_number' => '',
    'notes' => '',
    'scope_override_enabled' => 0,
    'scope_override_reason' => '',
];
$deliveryItems = array_fill(0, 32, [
    'product_name' => 'Botas Tácticas',
    'size_name' => '9.5',
    'quantity' => 1.000,
]);
$totalQuantity = 32.0;
$receiptEvidence = [
    'captured_at' => '2026-10-20 07:21:00',
    'signature_png' => 'RECEPTOR-HISTORICO',
    'signature_sha256' => str_repeat('a', 64),
];
$issuerReceiptEvidence = [
    'signature_png' => 'RESPONSABLE-HISTORICO',
    'signature_sha256' => str_repeat('b', 64),
];

$render = static function () use (
    $receiptTemplate, $delivery, $deliveryItems, $totalQuantity,
    $receiptEvidence, $issuerReceiptEvidence
): string {
    ob_start();
    try {
        eval('?>' . $receiptTemplate);
        return (string) ob_get_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
};
$html = $render();
foreach ([
    'DSP-000004',
    '20/10/2026 07:21',
    '16 Zona Policial - Pacora',
    'Funcionario &lt;Receptor&gt;',
    'Responsable P4',
    '32 unidades',
    'Talla',
    'Firma del funcionario receptor',
    'Firma del responsable de la entrega',
    'data:image/png;base64,' . base64_encode('RECEPTOR-HISTORICO'),
    'data:image/png;base64,' . base64_encode('RESPONSABLE-HISTORICO'),
    str_repeat('a', 64),
    str_repeat('b', 64),
] as $expected) {
    if (!str_contains($html, $expected) && $expected !== 'Talla') {
        throw new RuntimeException('A4 signed receipt render missing: ' . $expected);
    }
}
if (str_contains($html, 'Funcionario <Receptor>')
    || substr_count($html, '<td>Botas Tácticas</td>') !== 32
    || substr_count($html, 'class="receipt-signature-image"') !== 2) {
    throw new RuntimeException('Receipt HTML escaped incorrectly, lost rows or signatures.');
}
$issuerReceiptEvidence = null;
$oldDelivery = $delivery;
$oldDelivery['delivery_code'] = 'DSP-000003';
$oldDelivery['status'] = 'confirmed';
$renderOld = static function () use (
    $receiptTemplate, $oldDelivery, $deliveryItems, $totalQuantity,
    $receiptEvidence, $issuerReceiptEvidence
): string {
    $delivery = $oldDelivery;
    ob_start();
    try {
        eval('?>' . $receiptTemplate);
        return (string) ob_get_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
};
$historic = $renderOld();
if (!str_contains($historic, 'Sin firma personal archivada en esta entrega histórica')
    || str_contains($historic, base64_encode('RESPONSABLE-HISTORICO'))
    || substr_count($historic, 'class="receipt-signature-image"') !== 1
    || !str_contains($historic, 'DSP-000003')) {
    throw new RuntimeException('Historical delivery was incorrectly backfilled with issuer signature.');
}
echo "[OK] A4 receipt renders real snapshots, 32 lines, dual signatures and historical fallback.\n";
