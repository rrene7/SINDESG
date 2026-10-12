<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'view' => '/app/Views/requests/show.php',
    'picker' => '/app/Views/requests/catalog-picker.php',
    'js' => '/public/assets/js/requests.js',
    'css' => '/public/assets/css/requests.css',
    'repository' => '/app/Repositories/RequestRepository.php',
];
$content = [];
foreach ($paths as $name => $path) {
    $source = file_get_contents($root . $path);
    if (!is_string($source)) {
        throw new RuntimeException('No se pudo leer ' . $name);
    }
    $content[$name] = $source;
}

$view = $content['view'];
if (substr_count($view, "require __DIR__ . '/catalog-picker.php'") !== 3) {
    throw new RuntimeException('El buscador debe estar en solicitud simple, múltiple y Excel.');
}

foreach (['product_variant_id', 'product_id', 'catalogVariantOptions', 'catalogProductOptions',
          'request-single-variant', 'request-multiple-variant', 'request-excel-product'] as $token) {
    if (!str_contains($view, $token)) {
        throw new RuntimeException('El selector no recibe opciones correctas: ' . $token);
    }
}

foreach ([
    'data-catalog-picker', 'data-catalog-search', 'data-catalog-select',
    'data-catalog-count', 'data-catalog-empty', 'data-search-terms',
    'required', '?module=inventory&q=bota', 'new-product',
    'NO agrega existencias',
] as $token) {
    if (!str_contains($content['picker'], $token)) {
        throw new RuntimeException('Buscador o ruta de catálogo incompleta: ' . $token);
    }
}

foreach ([
    "normalize('NFD')",
    "replace(/[\\u0300-\\u036f]/g, '')",
    "terms.every((term) => searchable.includes(term))",
    'select.replaceChildren(placeholder',
    'select.value =',
    'noMatches.hidden = matches.length !== 0',
    'search.addEventListener',
] as $token) {
    if (!str_contains($content['js'], $token)) {
        throw new RuntimeException('El buscador no filtra correctamente: ' . $token);
    }
}

foreach (['pv.institutional_code', 'pv.supplier_code', 'pv.color AS variant_color',
          "p.status = 'active'", "pv.status = 'active'"] as $token) {
    if (!str_contains($content['repository'], $token)) {
        throw new RuntimeException('Falta origen de búsqueda o filtro de activos: ' . $token);
    }
}

if (!str_contains($content['css'], '.request-catalog-no-matches[hidden]')) {
    throw new RuntimeException('El mensaje de catálogo debe ocultarse cuando sí hay resultados.');
}

// Esta funcionalidad solo muestra catálogo; no cambia stock ni fuerza datos maestros.
if (preg_match('/\b(?:INSERT\s+INTO|DELETE\s+FROM|UPDATE\s+inventory_movements)\b/i',
    $content['picker'] . $content['js'])) {
    throw new RuntimeException('El buscador no debe generar movimientos.');
}

echo "[OK] Búsqueda por coincidencia en variantes y diagnóstico de botas ausentes." . PHP_EOL;
