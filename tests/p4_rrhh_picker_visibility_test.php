<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$css = (string) file_get_contents($root . '/public/assets/css/requests.css');
$js = (string) file_get_contents($root . '/public/assets/js/requests.js');
$view = (string) file_get_contents($root . '/app/Views/requests/show.php');
$api = (string) file_get_contents($root . '/public/api/employees.php');

// Regression: .request-picker-results was originally an absolute popup
// for single-employee inputs; bulk results inherited that positioning
// and therefore appeared detached from the multi-select form.
if (!str_contains($css, '.request-picker-results{position:absolute')) {
    throw new RuntimeException('Unexpected base employee popup selector.');
}
preg_match_all(
    '/\[data-bulk-nominal-picker\]\s+\[data-bulk-results\]\s*\{([^}]+)\}/s',
    $css,
    $matches
);
$lastRule = end($matches[1]);
if (!is_string($lastRule)
    || !preg_match('/position\s*:\s*static\s*;/i', $lastRule)) {
    throw new RuntimeException('P4 bulk RRHH results must use normal document flow.');
}
foreach (['display:block;', 'max-height:320px;', 'overflow-y:auto;'] as $rule) {
    if (!str_contains($lastRule, $rule)) {
        throw new RuntimeException('Missing list visual rule: ' . $rule);
    }
}
if (!str_contains($css, '[data-bulk-results][hidden]{display:none!important}')) {
    throw new RuntimeException('Bulk results hidden attr must remain functional.');
}
if (!str_contains($css, '.request-picker-result span{')) {
    throw new RuntimeException('RRHH employee names must be visible.');
}
foreach (['data-bulk-search', 'data-bulk-results-count', 'data-bulk-results',
          'data-bulk-select-page', 'data-bulk-selection'] as $token) {
    if (!str_contains($view, $token)) {
        throw new RuntimeException('P4 bulk form missing: ' . $token);
    }
}
foreach ([
    'resultCount.textContent = list.length',
    'renderResults(items)',
    'renderResults(shown)',
    "results.textContent = 'Consultando RRHH2029...'",
    "selectPage.hidden = list.length === 0",
    "input.addEventListener('input', debounce(",
] as $token) {
    if (!str_contains($js, $token)) {
        throw new RuntimeException('Bulk search state not visible: ' . $token);
    }
}
if (!str_contains($api, '$directory->search($query, 20)')) {
    throw new RuntimeException('Global RRHH search API must remain wired.');
}
echo "[OK] P4 RRHH result list displayed beneath search, with visible feedback." . PHP_EOL;
