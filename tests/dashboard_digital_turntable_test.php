<?php
declare(strict_types=1);

/**
 * Presentation-only regression: no database dependency, uploads mutation or
 * fabricated multi-view/360° product rotation.
 */
$root = dirname(__DIR__);
$view = file_get_contents($root . '/app/Views/dashboard.php');
$css = file_get_contents($root . '/public/assets/css/app.css');
$javascript = file_get_contents($root . '/public/assets/js/app.js');
// Git checkout on Windows can produce CRLF; check the same JS contract on both OSes.
if (is_string($javascript)) {
    $javascript = str_replace(["\r\n", "\r"], "\n", $javascript);
}
$layout = file_get_contents($root . '/app/Views/layout.php');

foreach (['view' => $view, 'css' => $css, 'javascript' => $javascript, 'layout' => $layout] as $name => $source) {
    if (!is_string($source) || $source === '') {
        throw new RuntimeException('No se pudo leer ' . $name);
    }
}

$require = static function (string $source, array $checks, string $part): void {
    foreach ($checks as $needle) {
        if (!str_contains($source, $needle)) {
            throw new RuntimeException('Falta en ' . $part . ': ' . $needle);
        }
    }
};

$require($view, [
    'class="product-image-placeholder dashboard-product-image digital-turntable"',
    'data-digital-turntable',
    'data-feature-image',
    'class="digital-turntable__product"',
    'class="digital-turntable__platter"',
    'class="digital-turntable__platter-rim"',
    'class="digital-turntable__platter-surface"',
    'class="digital-turntable__platter-orbit"',
    'data-turntable-replay',
    'type="button"',
    'Repetir animación de presentación del artículo',
    'PRESENTACIÓN 2D',
    'sindesg_upload_url((string) $featuredProduct',
    'data-dashboard-product-id',
    'data-feature-available',
    'data-feature-sizes',
], 'vista');

// Guard against CRLF false negatives while still requiring both statements.
$presentScript = "renderImage(entry);\n            replayPresentation();";
if (str_contains($javascript, $presentScript) !== true) {
    throw new RuntimeException('La presentación automática no sigue a la actualización de imagen.');
}
$crlfFixture = str_replace("\n", "\r\n", $javascript);
if (!str_contains(str_replace(["\r\n", "\r"], "\n", $crlfFixture), $presentScript)) {
    throw new RuntimeException('Regresión de compatibilidad Windows CRLF.');
}

$require($javascript, [
    "qs('[data-digital-turntable]', focusPanel)",
    "qs('[data-turntable-replay]', focusPanel)",
    "window.matchMedia('(prefers-reduced-motion: reduce)')",
    'const replayPresentation = () => {',
    "fields.turntable.classList.remove('is-presenting')",
    'void fields.turntable.offsetWidth',
    "fields.turntable.classList.add('is-presenting')",
    "fields.replay?.addEventListener('click', replayPresentation)",
    "fields.image?.addEventListener('click', replayPresentation)",
    "renderImage(entry);\n            replayPresentation();",
    "card.addEventListener('click'",
    "card.addEventListener('keydown'",
    'renderSizes(entry.sizes)',
    'fields.available.textContent = available',
], 'JavaScript');

$require($css, [
    '.dashboard-product-image.digital-turntable',
    '.digital-turntable__product',
    '.digital-turntable__product img',
    '.digital-turntable__platter',
    '.digital-turntable__platter-rim',
    '.digital-turntable__platter-surface',
    '.digital-turntable__platter-orbit',
    '.digital-turntable__replay:focus-visible',
    '@keyframes sindesg-item-present',
    '@keyframes sindesg-pedestal-sweep',
    '@media (max-width: 760px)',
    '@media (prefers-reduced-motion: reduce)',
    'animation: none !important',
], 'CSS');

// Visual fit contract: prevent tops of uniforms leaving the viewer and keep
// caption underneath the product rather than on top of the uniform photo.
if (!preg_match('/\\.digital-turntable__product\\s*\\{([^}]+)\\}/s', $css, $productRules)
    || !str_contains($productRules[1], 'inset: 12% 7% 23%;')
    || !str_contains($productRules[1], 'display: flex;')
    || !str_contains($productRules[1], 'min-height: 0;')) {
    throw new RuntimeException('El uniforme puede salirse por el borde superior del plato.');
}
if (!preg_match('/\\.dashboard-product-image \\.digital-turntable__product img\\s*\\{([^}]+)\\}/s', $css, $imageRules)
    || !str_contains($imageRules[1], 'height: 100%;')
    || !str_contains($imageRules[1], 'max-height: 100%;')
    || !str_contains($imageRules[1], 'max-width: 100%;')
    || !str_contains($imageRules[1], 'object-fit: contain;')) {
    throw new RuntimeException('La foto no respeta el área de encuadre en todos los tamaños.');
}
if (!preg_match('/\\.dashboard-product-image\\.digital-turntable > \\.digital-turntable__caption\\s*\\{([^}]+)\\}/s', $css, $captionRules)
    || !str_contains($captionRules[1], 'position: absolute;')
    || !str_contains($captionRules[1], 'bottom: 11px;')) {
    throw new RuntimeException('La leyenda está superpuesta sobre el uniforme en lugar de ubicarse abajo.');
}
if (!str_contains($css, 'inset: 13% 7% 26%;')) {
    throw new RuntimeException('Falta margen de seguridad del visor en móviles.');
}

$require($layout, ["sindesg_asset('css/app.css')", "sindesg_asset('js/app.js')"], 'carga de recursos');

if (substr_count($view, 'data-feature-image') !== 1 ||
    substr_count($view, 'data-digital-turntable') !== 1 ||
    substr_count($view, 'data-turntable-replay') !== 1) {
    throw new RuntimeException('Debe existir un único visor y un único control de repetición.');
}

if (str_contains($view, 'Últimos artículos incorporados')
    || str_contains($view, 'data-dashboard-category-id')
    || str_contains($view, 'data-dashboard-category-focus')) {
    throw new RuntimeException('El plato no debe duplicar el carrusel de artículos.');
}

echo "[OK] Plato digital: visor persistente, animación 2D repetible, teclado, reducción de movimiento e inventario intacto.\n";
