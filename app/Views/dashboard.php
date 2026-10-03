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
            $slug = (string) ($category['slug'] ?? 'categoria');
            $parts = array_values(array_filter(explode('-', $slug)));
            if (count($parts) > 1) {
                $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
            } else {
                $initials = strtoupper(substr($parts[0] ?? 'CA', 0, 2));
            }
            $categoryHref = $categoryId > 0
                ? sindesg_url('?module=inventory&category_id=' . $categoryId)
                : sindesg_url('?module=inventory');
            $categoryImage = (string) ($category['image_path'] ?? '');
            $categoryInstitutionalStock = (float) ($category['stock_total'] ?? 0);
            $categoryAvailableStock = (float) ($category['stock_available'] ?? $categoryInstitutionalStock);
            ?>
            <a class="category-card <?= $categoryAvailableStock > 0 ? 'featured' : '' ?>"
               href="<?= e($categoryHref) ?>"
               data-searchable="<?= e($category['name']) ?>">
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
                    <span class="stock-placeholder"><?= e(number_format($categoryAvailableStock, 0, '.', ',')) ?> disponibles</span>
                    <small><?= e((int) ($category['product_count'] ?? 0)) ?> artículos · <?= e(number_format($categoryInstitutionalStock, 0, '.', ',')) ?> institucionales · Entrar</small>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="dashboard-grid">
    <article class="feature-panel">
        <div class="panel-heading">
            <div>
                <span class="panel-icon">PR</span>
                <div>
                    <span class="eyebrow"><?= e($featuredCategory) ?></span>
                    <h2><?= e($featuredName) ?></h2>
                </div>
            </div>
            <a href="<?= e($featuredUrl) ?>">Abrir artículo →</a>
        </div>

        <div class="product-focus">
            <div class="product-copy">
                <h3><?= e($featuredProduct['color'] ?? 'Artículo disponible') ?></h3>
                <p><?= e($featuredDescription) ?></p>
                <div class="feature-tags">
                    <?php if ($featuredProduct): ?>
                        <span><?= e($featuredProduct['internal_code']) ?></span>
                        <span><?= e(number_format($featuredAvailableStock, 0, '.', ',')) ?> disponibles</span>
                        <span><?= e(number_format($featuredInstitutionalStock, 0, '.', ',')) ?> unidades institucionales</span>
                        <span><?= e($featuredProduct['sex'] ?? 'no aplica') ?></span>
                    <?php else: ?>
                        <span>Sin movimientos</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="product-image-placeholder dashboard-product-image">
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
                <strong><?= e(number_format($featuredAvailableStock, 0, '.', ',')) ?></strong>
                <span>unidades libres para nuevas entregas</span>
            </div>
            <div class="metric-note">
                Central físico <?= e(number_format($featuredCentralStock, 0, '.', ',')) ?>
                · Comprometido <?= e(number_format($featuredCommittedStock, 0, '.', ',')) ?>
                · P4 <?= e(number_format((float) ($featuredStockBreakdown['p4'] ?? 0), 0, '.', ',')) ?>
                · Tránsito <?= e(number_format((float) ($featuredStockBreakdown['transit'] ?? 0), 0, '.', ',')) ?>
                · Institucional <?= e(number_format($featuredInstitutionalStock, 0, '.', ',')) ?>
            </div>
        </article>

        <article class="metric-panel">
            <div class="metric-head">
                <span class="panel-icon">TA</span>
                <span>TALLAS DISPONIBLES</span>
            </div>
            <div class="size-row">
                <?php if ($featuredSizes === []): ?>
                    <span>—<small>Sin tallas con stock</small></span>
                <?php else: ?>
                    <?php foreach ($featuredSizes as $size): ?>
                        <span><?= e($size['size_name'] ?: 'S/T') ?><small><?= e(number_format((float) $size['quantity_on_hand'], 0, '.', ',')) ?></small></span>
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
                        <span><strong><?= e(number_format((float) $row['total_quantity'], 0, '.', ',')) ?></strong><small><?= e($row['status'] === 'confirmed' ? 'Confirmada' : 'Borrador') ?></small></span>
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
                    <span class="eyebrow">GESTIÓN</span>
                    <h2>Solicitudes pendientes</h2>
                </div>
            </div>
            <a href="<?= e(sindesg_url('?module=requests')) ?>">Ver módulo →</a>
        </div>
        <div class="empty-state">
            <strong>Sin solicitudes registradas</strong>
            <span>La Fase 7 habilitará el flujo P4 → Servicios Generales.</span>
        </div>
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
