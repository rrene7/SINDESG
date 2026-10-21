<?php
declare(strict_types=1);

/**
 * Regression for the actual Pacora screenshot: white fallback cards
 * were inheriting the application's light-on-dark text.
 */
$root=dirname(__DIR__);
$css=(string)file_get_contents($root.'/public/assets/css/nominal-report.css');
$view=(string)file_get_contents($root.'/app/Views/deliveries/nominal-report.php');
$layout=(string)file_get_contents($root.'/app/Views/layout.php');
$app=(string)file_get_contents($root.'/public/assets/css/app.css');

if (!str_contains($app, '--text: #edf8ff')
    || !str_contains($app, 'color: var(--text)')) {
    throw new RuntimeException('App theme contract changed; audit report contrast.');
}
if (!str_contains($css, '.nomrep .nomrep-panel')
    || !str_contains($css, 'background: var(--nomrep-panel)')
    || !str_contains($css, '--nomrep-panel: #0b2a45')
    || !str_contains($css, '--nomrep-text: #f2f8fd')
    || !str_contains($css, '.nomrep .nomrep-filters input')
    || !str_contains($css, '.nomrep td small')
    || !str_contains($css, '.nomrep .nomrep-qty')
    || !str_contains($css, '.nomrep .nomrep-actions a')) {
    throw new RuntimeException('High-contrast report surface or text rules are missing.');
}
if (str_contains($css,'var(--surface') || str_contains($css,'var(--border')
    || str_contains($css,'var(--input-bg') || str_contains($view,'<style>')
    || !str_contains($view,'nomrep-filter-search')) {
    throw new RuntimeException('Undefined light-surface fallback or obsolete inline styles.');
}
if (!str_contains($css,'color-scheme: dark')
    || !str_contains($css,'input::placeholder')
    || !str_contains($css,'input:focus-visible')
    || !str_contains($css,'@media (max-width: 680px)')
    || !str_contains($css,'@media print')
    || !str_contains($css,'color: #111 !important')) {
    throw new RuntimeException('Inputs, responsive or print contrast absent.');
}
if (!str_contains($layout, '$currentAction === \'nominal-report\'')
    || !str_contains($layout, "sindesg_asset('css/nominal-report.css')")) {
    throw new RuntimeException('Missing route-scoped, cache-busted stylesheet.');
}
if (!str_contains($view, 'name="per_page"') || !str_contains($view,'name="situation"')
    || !str_contains($view,'nomrep-table-wrap')
    || !str_contains($view, "last_safe_delivery_id")) {
    throw new RuntimeException('The redesigned view lost a key nominal-report interaction.');
}
echo "[OK] Nominal report dark contrast, readable controls, responsive and print modes.\n";
