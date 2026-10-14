<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$repository = file_get_contents($root.'/app/Repositories/ReceiptRepository.php');
$controller = file_get_contents($root.'/app/Controllers/ReceiptsController.php');
$view = file_get_contents($root.'/app/Views/receipts/show.php');
foreach ([
    [$repository, 'addMissingDraftVariant('],
    [$repository, 'FOR UPDATE'],
    [$repository, "status'] !== 'draft'"],
    [$repository, 'draft_variant_added'],
    [$repository, 'rollBack()'],
    [$repository, 'product_variants'],
    [$repository, 'receipt_items'],
    [$controller, "sindesg_can('admin.manage')"],
    [$controller, 'addMissingDraftVariant('],
    [$view, 'action=add-missing-variant'],
    [$view, '$existingVariantIds'],
] as [$text, $required]) {
    if (!str_contains($text, $required)) {
        throw new RuntimeException('Missing safety feature: '.$required);
    }
}
echo "[OK] Variant already cataloged but absent from draft can be added with admin audit.\n";
