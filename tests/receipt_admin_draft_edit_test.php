<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$repository = file_get_contents($root.'/app/Repositories/ReceiptRepository.php');
$controller = file_get_contents($root.'/app/Controllers/ReceiptsController.php');
$view = file_get_contents($root.'/app/Views/receipts/show.php');
if (!str_contains($repository, "FOR UPDATE")
    || !str_contains($repository, "draft_header_updated")
    || !str_contains($repository, "status = \"draft\"")
    || !str_contains($repository, "rollBack()")
    || !str_contains($controller, "sindesg_can('admin.manage')")
    || !str_contains($controller, "updateDraftHeader")
    || !str_contains($view, "receipt['status'] === 'draft' && sindesg_can('admin.manage')")
    || !str_contains($view, "action=update-draft")) {
    throw new RuntimeException('Administrative edit of receipt draft is not fully guarded and audited.');
}
echo "[OK] Edición administrativa de recepción borrador protegida y auditada.\n";
