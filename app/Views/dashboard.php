<?php
declare(strict_types=1);

$cards = $dashboardCategories !== [] ? $dashboardCategories : $categories;
$featuredInstitutionalStock = (float) ($featuredProduct['stock_total'] ?? 0);
$featuredAvailableStock = (float) ($featuredStockBreakdown['available'] ?? $featuredProduct['stock_available'] ?? 0);
$featuredCentralStock = (float) ($featuredStockBreakdown['central'] ?? 0);
$featuredCommittedStock = (float) ($featuredStockBreakdown['committed'] ?? 0);
$featuredName = (string) ($featuredProduct['name'] ?? 'Sin artículo destacado');
$featuredCategory = (string) ($featuredProduct['category_name'] ?? 'Inventario');
$featuredDescription = (string) ($featuredProduct['description'] ?? 'Cuando exista inventario, SINDESG mostrará aquí el artículo con mayor disponibilidad.');
$featuredUrl = $featuredProduct
    ? sindesg_url('?module=inventory&action=product&id=' . (int) $featuredProduct['id'])
    : sindesg_url('?module=inventory');

$requestStatusLabels = [
    'submitted' => 'Enviada',
    'in_review' => 'En revisión',
    'approved' => 'Aprobada',
    'partially_approved' => 'Aprobada parcial',
    'rejected' => 'Rechazada',
    'fulfilled' => 'Atendida',
    'closed' => 'Cerrada',
];

$categoryInitials = static function (array $category): string {
    $slug = (string) ($category['slug'] ?? 'categoria');
    $parts = array_values(array_filter(explode('-', $slug)));

    if (count($parts) > 1) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }

    return strtoupper(substr($parts[0] ?? 'CA', 0, 2));
};

$featuredCategoryId = (int) ($featuredProduct['category_id'] ?? 0);
if ($featuredCategoryId <= 0 && $cards !== []) {
    $featuredCategoryId = (int) ($cards[0]['id'] ?? 0);
}

$categoryFocusPayload = [];
foreach ($cards as $category) {
    $categoryId = (int) ($category['id'] ?? 0);
    if ($categoryId <= 0) {
        continue;
    }

    $focus = $dashboardCategoryFocus[$categoryId] ?? [];
    $focusProduct = is_array($focus['product'] ?? null) ? $focus['product'] : null;
    $focusStock = is_array($focus['stock'] ?? null) ? $focus['stock'] : [];
    $focusSizes = is_array($focus['sizes'] ?? null) ? $focus['sizes'] : [];
    $categoryUrl = sindesg_url('?module=inventory&category_id=' . $categoryId);

    $categoryFocusPayload[(string) $categoryId] = [
        'category' => [
            'id' => $categoryId,
            'name' => (string) ($category['name'] ?? 'Categoría'),
            'initials' => $categoryInitials($category),
            'url' => $categoryUrl,
        ],
        'product' => $focusProduct === null ? null : [
            'id' => (int) ($focusProduct['id'] ?? 0),
            'name' => (string) ($focusProduct['name'] ?? 'Artículo'),
            'internal_code' => (string) ($focusProduct['internal_code'] ?? ''),
            'description' => (string) (
                $focusProduct['description']
                ?? 'Artículo seleccionado automáticamente por disponibilidad dentro de esta categoría.'
            ),
            'color' => (string) ($focusProduct['color'] ?? 'Artículo disponible'),
            'sex' => (string) ($focusProduct['sex'] ?? 'no aplica'),
            'url' => sindesg_url('?module=inventory&action=product&id=' . (int) ($focusProduct['id'] ?? 0)),
            'image_url' => !empty($focusProduct['primary_image'])
                ? sindesg_upload_url((string) $focusProduct['primary_image'])
                : '',
        ],
        'stock' => [
            'available' => (float) ($focusStock['available'] ?? $category['stock_available'] ?? 0),
            'central' => (float) ($focusStock['central'] ?? 0),
            'committed' => (float) ($focusStock['committed'] ?? 0),
            'p4' => (float) ($focusStock['p4'] ?? 0),
            'transit' => (float) ($focusStock['transit'] ?? 0),
            'institutional' => $focusProduct !== null
                ? (float) ($focusProduct['stock_total'] ?? 0)
                : (float) ($category['stock_total'] ?? 0),
        ],
        'sizes' => array_map(
            static fn (array $size): array => [
                'name' => (string) ($size['size_name'] ?? 'S/T'),
                'quantity' => (float) ($size['quantity_on_hand'] ?? 0),
            ],
            $focusSizes
        ),
    ];
}
?>
<section class="hero">
    <div>
        <span class="eyebrow">CENTRO DE CONTROL LOGÍSTICO</span>
        <h1>Inventario y despacho en una sola vista</h1>
        <p>El dashboard refleja el catálogo, fotografías y existencias reales calculadas desde el kardex.</p>
    </div>
    <div class="hero-status">
        <span class="status-dot <?= $health['sindesg']['ok'] ? 'ok' : 'warn' ?>"></span>
        <div>
            <strong>Base SINDESG</strong>
            <small><?= e($health['sindesg']['message']) ?></small>
        </div>
    </div>
</section>

<section class="section-block">
    <div class="section-heading">
        <div>
            <span class="eyebrow">CATÁLOGO VISUAL</span>
            <h2>Categorías de uniformes y equipo</h2>
        </div>
        <div class="carousel-controls">
            <button type="button" data-carousel-prev aria-label="Anterior">←</button>
            <button type="button" data-carousel-next aria-label="Siguiente">→</button>
        </div>
    </div>

    <div class="category-carousel" data-carousel>
        <?php foreach ($cards as $index => $category): ?>
            <?php
            $categoryId = (int) ($category['id'] ?? 0);
            $initials = $categoryInitials($category);
            $categoryHref = $categoryId > 0
                ? sindesg_url('?module=inventory&category_id=' . $categoryId)
                : sindesg_url('?module=inventory');
            $focus = $dashboardCategoryFocus[$categoryId] ?? [];
            $focusProduct = is_array($focus['product'] ?? null) ? $focus['product'] : null;
            $categoryImage = $focusProduct !== null
                ? (string) ($focusProduct['primary_image'] ?? '')
                : '';
            $categoryInstitutionalStock = (float) ($category['stock_total'] ?? 0);
            $categoryAvailableStock = (float) ($category['stock_available'] ?? $categoryInstitutionalStock);
            $isSelectedCategory = $categoryId > 0 && $categoryId === $featuredCategoryId;
            ?>
            <article class="category-card <?= $isSelectedCategory ? 'featured is-selected' : '' ?>"
                     data-dashboard-category-id="<?= e($categoryId) ?>"
                     data-searchable="<?= e($category['name']) ?>"
                     role="button"
                     tabindex="0"
                     aria-pressed="<?= $isSelectedCategory ? 'true' : 'false' ?>">
                <div class="category-photo-placeholder">
                    <?php if ($categoryImage !== ''): ?>
                        <img src="<?= e(sindesg_upload_url($categoryImage)) ?>" alt="<?= e($category['name']) ?>">
                    <?php else: ?>
                        <span><?= e($initials) ?></span>
                        <small>Sin fotografía</small>
                    <?php endif; ?>
                </div>
                <div class="category-card-copy">
                    <strong><?= e($category['name']) ?></strong>
                    <span class="stock-placeholder"><?= e(sindesg_units($categoryAvailableStock)) ?> disponibles</span>
                    <small><?= e((int) ($category['product_count'] ?? 0)) ?> artículos · <?= e(sindesg_units($categoryInstitutionalStock)) ?> institucionales</small>
                    <a class="category-enter-link" href="<?= e($categoryHref) ?>">Entrar a categoría →</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<script type="application/json" data-dashboard-category-focus>
<?= json_encode(
    $categoryFocusPayload,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
) ?>
</script>

<section class="dashboard-grid" data-dashboard-focus-panel>
    <article class="feature-panel">
        <div class="panel-heading">
            <div>
                <span class="panel-icon" data-feature-icon>PR</span>
                <div>
                    <span class="eyebrow" data-feature-category><?= e($featuredCategory) ?></span>
                    <h2 data-feature-name><?= e($featuredName) ?></h2>
                </div>
            </div>
            <a href="<?= e($featuredUrl) ?>" data-feature-link>Abrir artículo →</a>
        </div>

        <div class="product-focus">
            <div class="product-copy">
                <h3 data-feature-color><?= e($featuredProduct['color'] ?? 'Artículo disponible') ?></h3>
                <p data-feature-description><?= e($featuredDescription) ?></p>
                <div class="feature-tags" data-feature-tags>
                    <?php if ($featuredProduct): ?>
                        <span>Artículo del catálogo institucional</span>
                        <span><?= e(sindesg_units($featuredAvailableStock)) ?> disponibles</span>
                        <span><?= e(sindesg_units($featuredInstitutionalStock)) ?> unidades institucionales</span>
                        <span><?= e($featuredProduct['sex'] ?? 'no aplica') ?></span>
                    <?php else: ?>
                        <span>Sin movimientos</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="product-image-placeholder dashboard-product-image" data-feature-image>
                <?php if (!empty($featuredProduct['primary_image'])): ?>
                    <img src="<?= e(sindesg_upload_url((string) $featuredProduct['primary_image'])) ?>" alt="<?= e($featuredName) ?>">
                <?php else: ?>
                    <div class="scan-ring"></div>
                    <div class="product-monogram">PR</div>
                    <span>Imagen pendiente</span>
                <?php endif; ?>
            </div>
        </div>
    </article>

    <aside class="side-stack">
        <article class="metric-panel">
            <div class="metric-head">
                <span class="panel-icon">ST</span>
                <span>STOCK DISPONIBLE</span>
            </div>
            <div class="metric-main">
                <strong data-feature-available><?= e(sindesg_units($featuredAvailableStock)) ?></strong>
                <span>unidades libres para nuevas entregas</span>
            </div>
            <div class="metric-note" data-feature-breakdown>
                Central físico <?= e(sindesg_units($featuredCentralStock)) ?>
                · Comprometido <?= e(sindesg_units($featuredCommittedStock)) ?>
                · P4 <?= e(sindesg_units((float) ($featuredStockBreakdown['p4'] ?? 0))) ?>
                · Tránsito <?= e(sindesg_units((float) ($featuredStockBreakdown['transit'] ?? 0))) ?>
                · Institucional <?= e(sindesg_units($featuredInstitutionalStock)) ?>
            </div>
        </article>

        <article class="metric-panel">
            <div class="metric-head">
                <span class="panel-icon">TA</span>
                <span>TALLAS DISPONIBLES</span>
            </div>
            <div class="size-row" data-feature-sizes>
                <?php if ($featuredSizes === []): ?>
                    <span>—<small>Sin tallas con stock</small></span>
                <?php else: ?>
                    <?php foreach ($featuredSizes as $size): ?>
                        <span><?= e($size['size_name'] ?: 'S/T') ?><small><?= e(sindesg_units((float) $size['quantity_on_hand'])) ?></small></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </article>
    </aside>
</section>

<section class="operations-grid">
    <article class="table-panel">
        <div class="panel-heading compact">
            <div>
                <span class="panel-icon">LT</span>
                <div>
                    <span class="eyebrow">RECEPCIÓN</span>
                    <h2>Lotes recientes</h2>
                </div>
            </div>
            <a href="<?= e(sindesg_url('?module=receipts')) ?>">Ver módulo →</a>
        </div>
        <?php if ($recentDashboardReceipts === []): ?>
            <div class="empty-state">
                <strong>Aún no hay recepciones registradas</strong>
                <span>Las recepciones aparecerán aquí al guardarse en SINDESG.</span>
            </div>
        <?php else: ?>
            <div class="dashboard-mini-list">
                <?php foreach ($recentDashboardReceipts as $row): ?>
                    <a href="<?= e(sindesg_url('?module=receipts&action=show&id=' . (int) $row['id'])) ?>">
                        <span><strong><?= e($row['receipt_code']) ?></strong><small><?= e($row['lot_code']) ?> · <?= e($row['supplier_or_origin']) ?></small></span>
                        <span><strong><?= e(sindesg_units((float) $row['total_quantity'])) ?></strong><small><?= e($row['status'] === 'confirmed' ? 'Confirmada' : 'Borrador') ?></small></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>

    <article class="table-panel">
        <div class="panel-heading compact">
            <div>
                <span class="panel-icon">SO</span>
                <div>
                    <span class="eyebrow">GESTIÓN OPERATIVA</span>
                    <h2>Solicitudes y distribución</h2>
                </div>
            </div>
            <a href="<?= e(sindesg_url('?module=requests')) ?>">Ver solicitudes →</a>
        </div>

        <div class="dashboard-ops-summary">
            <span><strong><?= e($dashboardOperationalSummary['requests_pending_review']) ?></strong><small>por revisar</small></span>
            <span><strong><?= e($dashboardOperationalSummary['requests_ready_distribution']) ?></strong><small>listas para distribuir</small></span>
            <span><strong><?= e($dashboardOperationalSummary['distributions_in_transit']) ?></strong><small>en tránsito</small></span>
            <span><strong><?= e($dashboardOperationalSummary['active_p4']) ?></strong><small>P4 activos</small></span>
        </div>

        <?php if ($recentDashboardRequests === []): ?>
            <div class="empty-state compact-empty">
                <strong>No hay solicitudes operativas registradas</strong>
                <span>Las solicitudes enviadas, revisadas y aprobadas aparecerán aquí automáticamente.</span>
            </div>
        <?php else: ?>
            <div class="dashboard-mini-list">
                <?php foreach ($recentDashboardRequests as $row): ?>
                    <?php
                    $approvedPending = max(
                        0.0,
                        (float) ($row['quantity_approved'] ?? 0) - (float) ($row['quantity_dispatched'] ?? 0)
                    );
                    ?>
                    <a href="<?= e(sindesg_url('?module=requests&action=show&id=' . (int) $row['id'])) ?>">
                        <span>
                            <strong>Solicitud de dotación</strong>
                            <small><?= e($row['p4_name']) ?></small>
                        </span>
                        <span>
                            <strong><?= e($requestStatusLabels[$row['status']] ?? $row['status']) ?></strong>
                            <small>
                                <?= e((int) ($row['item_count'] ?? 0)) ?> renglones
                                · <?= e(sindesg_units((float) ($row['quantity_requested'] ?? 0))) ?> solicitadas
                                <?php if ($approvedPending > 0): ?>
                                    · <?= e(sindesg_units($approvedPending)) ?> por distribuir
                                <?php endif; ?>
                            </small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>

<section class="module-grid">
    <?php foreach ($modules as $key => $moduleItem): ?>
        <a class="module-card" href="<?= e(sindesg_url('?module=' . $key)) ?>" data-searchable="<?= e($moduleItem['label']) ?>">
            <span class="module-icon"><?= e($moduleItem['icon']) ?></span>
            <span class="module-copy">
                <strong><?= e($moduleItem['label']) ?></strong>
                <small><?= e($moduleItem['phase']) ?></small>
                <p><?= e($moduleItem['description']) ?></p>
            </span>
            <span class="module-arrow">→</span>
        </a>
    <?php endforeach; ?>
</section>

<section class="system-strip">
    <div>
        <span class="status-dot <?= $health['sindesg']['ok'] ? 'ok' : 'warn' ?>"></span>
        <span><strong>SINDESG</strong><small><?= e($health['sindesg']['message']) ?></small></span>
    </div>
    <div>
        <span class="status-dot <?= $health['rrhh']['ok'] ? 'ok' : 'warn' ?>"></span>
        <span><strong>RRHH2029</strong><small><?= e($health['rrhh']['message']) ?> · solo lectura</small></span>
    </div>
    <div>
        <span class="status-dot ok"></span>
        <span><strong>Datos del dashboard</strong><small>Catálogo y kardex en tiempo real</small></span>
    </div>
</section>
