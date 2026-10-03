<?php
declare(strict_types=1);
?>
<section class="hero">
    <div>
        <span class="eyebrow">CENTRO DE CONTROL LOGÍSTICO</span>
        <h1>Inventario y despacho en una sola vista</h1>
        <p>Acceso interactivo a prendas, lotes, solicitudes, distribución, P4 y reportes. Las cifras reales aparecerán al activar el módulo de inventario.</p>
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
        <?php foreach ($categories as $index => $category): ?>
            <a class="category-card <?= $index === 1 ? 'featured' : '' ?>"
               href="<?= e(sindesg_url('?module=inventory&category=' . urlencode($category['key']))) ?>"
               data-searchable="<?= e($category['name']) ?>">
                <div class="category-photo-placeholder">
                    <span><?= e($category['initials']) ?></span>
                    <small>Foto real</small>
                </div>
                <div class="category-card-copy">
                    <strong><?= e($category['name']) ?></strong>
                    <span class="stock-placeholder">Stock pendiente</span>
                    <small>Entrar a la categoría</small>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="dashboard-grid">
    <article class="feature-panel">
        <div class="panel-heading">
            <div>
                <span class="panel-icon">CA</span>
                <div>
                    <span class="eyebrow">DETALLE DE PRENDA</span>
                    <h2>Camisa</h2>
                </div>
            </div>
            <a href="<?= e(sindesg_url('?module=inventory&category=camisa')) ?>">Abrir inventario →</a>
        </div>

        <div class="product-focus">
            <div class="product-copy">
                <h3>Uniforme operativo</h3>
                <p>Esta sección mostrará la fotografía real cargada por Servicios Generales, descripción, código, lote, marca, talla y disponibilidad.</p>
                <div class="feature-tags">
                    <span>Código interno</span>
                    <span>Tallas</span>
                    <span>Lotes</span>
                    <span>Kardex</span>
                </div>
            </div>
            <div class="product-image-placeholder">
                <div class="scan-ring"></div>
                <div class="product-monogram">CA</div>
                <span>Imagen pendiente de carga</span>
            </div>
        </div>
    </article>

    <aside class="side-stack">
        <article class="metric-panel">
            <div class="metric-head">
                <span class="panel-icon">ST</span>
                <span>STOCK ACTUAL</span>
            </div>
            <div class="metric-main">
                <strong>—</strong>
                <span>unidades disponibles</span>
            </div>
            <div class="metric-note">Se calculará desde movimientos de inventario.</div>
        </article>

        <article class="metric-panel">
            <div class="metric-head">
                <span class="panel-icon">TA</span>
                <span>TALLAS DISPONIBLES</span>
            </div>
            <div class="size-row">
                <?php foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL'] as $size): ?>
                    <span><?= e($size) ?><small>—</small></span>
                <?php endforeach; ?>
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
                    <h2>Lotes recibidos</h2>
                </div>
            </div>
            <a href="<?= e(sindesg_url('?module=receipts')) ?>">Ver módulo →</a>
        </div>
        <div class="empty-state">
            <strong>Aún no hay lotes en SINDESG</strong>
            <span>La Fase 3 habilitará recepciones y entradas trazables.</span>
        </div>
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
        <span><strong>Fase actual</strong><small>Núcleo técnico y dashboard</small></span>
    </div>
</section>
