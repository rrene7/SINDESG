<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$repo = file_get_contents($root.'/app/Repositories/ReceiptRepository.php');
$controller = file_get_contents($root.'/app/Controllers/ReceiptsController.php');
$view = file_get_contents($root.'/app/Views/receipts/show.php');
$required = [
    [$repo, 'increaseDraftItem('],
    [$repo, 'FOR UPDATE'],
    [$repo, "status'] !== 'draft'"],
    [$repo, 'draft_item_quantity_increased'],
    [$repo, 'rollBack()'],
    [$controller, "sindesg_can('admin.manage')"],
    [$controller, 'increaseDraftItem('],
    [$view, 'action=increase-draft-quantity'],
    [$view, "receipt['status'] === 'draft' && sindesg_can('admin.manage')"],
];
foreach ($required as [$content, $needle]) {
    if (!str_contains($content, $needle)) {
        throw new RuntimeException('Missing draft-only receipt quantity safety: ' . $needle);
    }
}
echo "[OK] Suma de cantidades en borrador restringida a ADMIN y auditada.\n";
