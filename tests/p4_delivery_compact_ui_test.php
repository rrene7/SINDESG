<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$view = (string) file_get_contents($root . '/app/Views/deliveries/p4-index.php');
$css = (string) file_get_contents($root . '/public/assets/css/dashboard-p4.css');
foreach ([
  'data-p4-delivery-list',
  'data-p4-filter-search',
  'data-p4-filter-size',
  'data-p4-filter-request',
  'data-p4-filter-reset',
  'data-p4-delivery-row',
  'data-p4-visible-count',
  "'?module=deliveries&action=new&request_item_id='",
] as $needle) {
    if (!str_contains($view, $needle)) throw new RuntimeException("Missing P4 delivery UI: $needle");
}
foreach (['p4-delivery-row[hidden]', '@media(max-width:640px)', '.p4-delivery-action'] as $needle) {
    if (!str_contains($css, $needle)) throw new RuntimeException("Missing responsive style: $needle");
}
if (str_contains($view, 'p4-home-grid') || str_contains($view, 'p4-step-card')) {
    throw new RuntimeException('Legacy oversized grid unexpectedly remains');
}
$repo = (string) file_get_contents($root . '/app/Repositories/DeliveryRepository.php');
if (!str_contains($repo, 'w.organization_unit_name_snapshot AS p4_name')
    || !str_contains($repo, 'r.requested_at')
    || !str_contains($view, "Dotación de ")
    || !str_contains($view, "Dotación · ")
    || str_contains($view, '<small><?= e($line')) {
    throw new RuntimeException('Human-readable item and request labels missing.');
}
echo "[OK] Compact searchable P4 delivery list wiring and responsive styles.\n";
