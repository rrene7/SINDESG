<?php
declare(strict_types=1);

$cards = $dashboardProducts ?? [];
$selectedProductId = (int) ($featuredProduct['id'] ?? 0);
$featuredInstitutionalStock = (float) ($featuredProduct['stock_total'] ?? 0);
$featuredAvailableStock = (float) ($featuredStockBreakdown['available'] ?? 0);
$featuredCentralStock = (float) ($featuredStockBreakdown['central'] ?? 0);
$featuredCommittedStock = (float) ($featuredStockBreakdown['committed'] ?? 0);
$featuredName = (string) ($featuredProduct['name'] ?? 'Sin artículos registrados');
$featuredCategory = (string) ($featuredProduct['category_name'] ?? 'Inventario');
$featuredDescription = trim((string) ($featuredProduct['description'] ?? ''))
    ?: 'Artículo del catálogo institucional. Seleccione otra tarjeta para consultar sus tallas y existencias.';
$featuredUrl = $featuredProduct
    ? sindesg_url('?module=inventory&action=product&id=' . $selectedProductId)
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

$productFocusPayload = [];
foreach (($dashboardProductFocus ?? []) as $productId => $entry) {
    $product = $entry['product'] ?? [];
    $entry['product']['url'] = sindesg_url((string) ($product['url'] ?? '?module=inventory'));
    $entry['product']['image_url'] = !empty($product['image_path'])
        ? sindesg_upload_url((string) $product['image_path'])
        : '';
    unset($entry['product']['image_path']);
    $productFocusPayload[(string) $productId] = $entry;
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

<section class="section-block" aria-labelledby="product-carousel-heading">
    <div class="section-heading">
        <div>
            <span class="eyebrow">CATÁLOGO VISUAL · ARTÍCULOS</span>
            <h2 id="product-carousel-heading">Uniformes, equipos y artículos registrados</h2>
            <p class="product-carousel-hint">Seleccione un artículo para ver su imagen, tallas y existencias. Todos aparecen, incluso los que aún no tienen stock.</p>
        </div>
        <div class="carousel-controls">
            <button type="button" data-carousel-prev aria-label="Anterior artículo">←</button>
            <button type="button" data-carousel-next aria-label="Siguiente artículo">→</button>
        </div>
    </div>

    <?php if ($cards === []): ?>
        <div class="dashboard-product-empty">Todavía no hay artículos activos en el catálogo.</div>
    <?php else: ?>
        <div class="product-carousel" data-carousel aria-label="Carrusel de artículos del catálogo">
            <?php foreach ($cards as $product): ?>
                <?php
                    $productId = (int) ($product['id'] ?? 0);
                    $photo = (string) ($product['primary_image'] ?? '');
                    $focus = $dashboardProductFocus[$productId] ?? [];
                    $available = (float) ($focus['stock']['available'] ?? 0);
                    $institutional = (float) ($product['stock_total'] ?? 0);
                    $selected = $productId === $selectedProductId;
                ?>
                <article class="product-carousel-card <?= $selected ? 'featured is-selected' : '' ?>"
                         data-dashboard-product-id="<?= $productId ?>"
                         data-searchable="<?= e($product['name'] . ' ' . $product['category_name']) ?>"
                         role="button" tabindex="0" aria-pressed="<?= $selected ? 'true' : 'false' ?>"
                         aria-label="Seleccionar <?= e($product['name']) ?>">
                    <div class="product-carousel-photo">
                        <?php if ($photo !== ''): ?>
                            <img src="<?= e(sindesg_upload_url($photo)) ?>"
                                 alt="<?= e($product['name']) ?>" loading="lazy">
                        <?php else: ?>
                            <span aria-hidden="true">SG</span>
                            <small>Sin fotografía</small>
                        <?php endif; ?>
                    </div>
                    <div class="product-carousel-copy">
                        <small class="product-carousel-category"><?= e($product['category_name']) ?></small>
                        <strong><?= e($product['name']) ?></strong>
                        <small class="product-carousel-code"><?= e((string) ($product['internal_code'] ?? '')) ?></small>
                        <span class="stock-placeholder">
                            <?= $available > 0
                                ? e(sindesg_units($available)) . ' disponibles'
                                : 'Sin existencias disponibles' ?>
                        </span>
                        <small><?= e(sindesg_units($institutional)) ?> unidades institucionales</small>
                        <a class="product-enter-link"
                           href="<?= e(sindesg_url('?module=inventory&action=product&id=' . $productId)) ?>">Ver artículo →</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script type="application/json" data-dashboard-product-focus>
<?= json_encode(
    $productFocusPayload,
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
                <h3 data-feature-color><?= e((string) ($featuredProduct['color'] ?? '') ?: 'Sin color registrado') ?></h3>
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
            <div class="product-image-placeholder dashboard-product-image digital-turntable"
                 data-digital-turntable role="group"
                 aria-label="Exhibición digital del artículo seleccionado; presentación visual en 2D">
                <div class="digital-turntable__atmosphere" aria-hidden="true"></div>
                <div class="digital-turntable__product" data-feature-image
                     title="Tocar para repetir la presentación visual">
                    <?php if (!empty($featuredProduct['primary_image'])): ?>
                        <img src="<?= e(sindesg_upload_url((string) $featuredProduct['primary_image'])) ?>"
                             alt="<?= e($featuredName) ?>">
                    <?php else: ?>
                        <div class="scan-ring"></div>
                        <div class="product-monogram">PR</div>
                        <span>Imagen pendiente</span>
                    <?php endif; ?>
                </div>
                <div class="digital-turntable__platter" aria-hidden="true">
                    <div class="digital-turntable__platter-rim"></div>
                    <div class="digital-turntable__platter-surface"></div>
                    <div class="digital-turntable__platter-orbit"></div>
                </div>
                <button class="digital-turntable__replay" type="button" data-turntable-replay
                        aria-label="Repetir animación de presentación del artículo"
                        title="Repetir presentación (efecto visual 2D)">↻ <span>Presentar</span></button>
                <span class="digital-turntable__caption" aria-hidden="true">PLATAFORMA DIGITAL · PRESENTACIÓN 2D</span>
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

<?php // The institutional module directory is for ADMIN; operational users
      // retain their own activity panels and the permission-filtered top menu. ?>
<?php if (\App\Services\DashboardModuleTiles::visibleFor((array) sindesg_user())): ?>
<section class="module-grid">
    <?php foreach ($modules as $key => $moduleItem): ?>
        <?php if (!sindesg_can(sindesg_permission_for($key, 'index', 'GET') ?? 'dashboard.view')
            || !sindesg_role_action_allowed($key, 'index', 'GET', (array) sindesg_user())) continue; ?>
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
<?php endif; ?>

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
