<?php
declare(strict_types=1);

$excelParams = array_merge(
    $reportFilters,
    ['module' => 'reports', 'action' => 'excel', 'type' => $reportType]
);
$printParams = array_merge(
    $reportFilters,
    ['module' => 'reports', 'action' => 'print', 'type' => $reportType]
);

$selectedWarehouse = (int) ($reportFilters['warehouse_id'] ?? 0);
$selectedCategory = (int) ($reportFilters['category_id'] ?? 0);
$selectedProduct = (int) ($reportFilters['product_id'] ?? 0);
$selectedSize = (int) ($reportFilters['size_id'] ?? 0);
?>
<section class="reports-hero">
    <div>
        <span class="eyebrow">FASE 11 · RENDICIÓN Y ANÁLISIS</span>
        <h1>Centro de reportes SINDESG</h1>
        <p>Consulte la cadena logística completa con filtros combinables. Los reportes son de solo lectura y el stock siempre se deriva del kardex.</p>
    </div>
    <div class="reports-hero-actions">
        <a class="secondary-button" href="<?= e(sindesg_url('?' . http_build_query($printParams))) ?>" target="_blank">PDF / Imprimir</a>
        <a class="primary-button" href="<?= e(sindesg_url('?' . http_build_query($excelParams))) ?>">Exportar Excel</a>
    </div>
</section>

<section class="reports-summary">
    <article><span>Disponible Central</span><strong><?= e(sindesg_units((float) $reportSummary['available_stock'])) ?></strong><small>Central físico <?= e(sindesg_units((float) $reportSummary['central_stock'])) ?> · comprometido <?= e(sindesg_units((float) $reportSummary['committed_stock'])) ?></small></article>
    <article><span>Stock institucional</span><strong><?= e(sindesg_units((float) $reportSummary['institutional_stock'])) ?></strong><small>P4 <?= e(sindesg_units((float) $reportSummary['p4_stock'])) ?> · tránsito <?= e(sindesg_units((float) $reportSummary['transit_stock'])) ?></small></article>
    <article><span>Solicitudes abiertas</span><strong><?= e($reportSummary['open_requests']) ?></strong><small>Pendientes de cierre</small></article>
    <article><span>Entregas confirmadas</span><strong><?= e($reportSummary['confirmed_deliveries']) ?></strong><small><?= e(sindesg_units((float) $reportSummary['movement_count'])) ?> movimientos en kardex</small></article>
</section>

<section class="reports-panel">
    <div class="reports-section-head">
        <div>
            <span class="eyebrow">TIPO DE REPORTE</span>
            <h2><?= e($reportMeta['title']) ?></h2>
            <p><?= e($reportMeta['description']) ?></p>
        </div>
    </div>

    <div class="report-types">
        <?php foreach ($reportDefinitions as $key => $definition): ?>
            <?php
            $params = $reportFilters;
            $params['module'] = 'reports';
            $params['type'] = $key;
            unset($params['threshold']);
            ?>
            <a class="<?= $reportType === $key ? 'active' : '' ?>"
               href="<?= e(sindesg_url('?' . http_build_query($params))) ?>">
                <strong><?= e($definition['title']) ?></strong>
                <span><?= e($definition['description']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="reports-panel">
    <div class="reports-section-head">
        <div><span class="eyebrow">FILTROS</span><h2>Combinar criterios</h2></div>
        <a class="reports-clear" href="<?= e(sindesg_url('?module=reports&type=' . rawurlencode($reportType))) ?>">Limpiar</a>
    </div>

    <form class="reports-filters" method="get" action="<?= e(sindesg_url()) ?>">
        <input type="hidden" name="module" value="reports">
        <input type="hidden" name="type" value="<?= e($reportType) ?>">

        <label>
            <span>Desde</span>
            <input type="date" name="date_from" value="<?= e($reportFilters['date_from']) ?>">
        </label>
        <label>
            <span>Hasta</span>
            <input type="date" name="date_to" value="<?= e($reportFilters['date_to']) ?>">
        </label>

        <label>
            <span>Almacén / P4</span>
            <select name="warehouse_id">
                <option value="0">Todos</option>
                <?php foreach ($reportFilterOptions['warehouses'] as $warehouse): ?>
                    <option value="<?= e($warehouse['id']) ?>" <?= $selectedWarehouse === (int) $warehouse['id'] ? 'selected' : '' ?>>
                        <?= e(($warehouse['warehouse_type'] === 'P4'
                            ? ($warehouse['organization_unit_name_snapshot'] ?: $warehouse['name'])
                            : $warehouse['name'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Categoría</span>
            <select name="category_id">
                <option value="0">Todas</option>
                <?php foreach ($reportFilterOptions['categories'] as $category): ?>
                    <option value="<?= e($category['id']) ?>" <?= $selectedCategory === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Artículo</span>
            <select name="product_id">
                <option value="0">Todos</option>
                <?php foreach ($reportFilterOptions['products'] as $product): ?>
                    <option value="<?= e($product['id']) ?>" <?= $selectedProduct === (int) $product['id'] ? 'selected' : '' ?>><?= e($product['category_name'] . ' · ' . $product['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Talla</span>
            <select name="size_id">
                <option value="0">Todas</option>
                <?php foreach ($reportFilterOptions['sizes'] as $size): ?>
                    <option value="<?= e($size['id']) ?>" <?= $selectedSize === (int) $size['id'] ? 'selected' : '' ?>><?= e($size['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Aplicación</span>
            <select name="sex">
                <option value="">Todas</option>
                <?php foreach (['masculino' => 'Masculino', 'femenino' => 'Femenino', 'unisex' => 'Unisex', 'no_aplica' => 'No aplica'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $reportFilters['sex'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Estado / tipo movimiento</span>
            <input type="text" name="status" value="<?= e($reportFilters['status']) ?>" placeholder="Ej.: confirmed, received...">
        </label>

        <label>
            <span>Dirección kardex</span>
            <select name="direction">
                <option value="">Todas</option>
                <option value="IN" <?= $reportFilters['direction'] === 'IN' ? 'selected' : '' ?>>Entrada</option>
                <option value="OUT" <?= $reportFilters['direction'] === 'OUT' ? 'selected' : '' ?>>Salida</option>
            </select>
        </label>

        <div class="reports-employee-picker"
             data-report-picker
             data-search-url="<?= e($employeeSearchUrl) ?>">
            <label>
                <span>Funcionario</span>
                <input type="search"
                       name="employee_label"
                       value="<?= e($reportFilters['employee_label']) ?>"
                       data-report-search
                       autocomplete="off"
                       placeholder="Nombre, cédula o posición...">
                <input type="hidden" name="employee_id" value="<?= e($reportFilters['employee_id']) ?>" data-report-id>
            </label>
            <div class="reports-picker-results" data-report-results hidden></div>
        </div>

        <label>
            <span>Umbral bajo stock</span>
            <input type="number" name="threshold" min="0" step="1" value="<?= e($reportFilters['threshold']) ?>">
        </label>

        <label class="reports-search-field">
            <span>Búsqueda libre</span>
            <input type="search" name="q" value="<?= e($reportFilters['q']) ?>" placeholder="Código, nombre, documento, referencia...">
        </label>

        <button class="primary-button" type="submit">Aplicar filtros</button>
    </form>
</section>

<section class="reports-panel report-results">
    <div class="reports-section-head">
        <div>
            <span class="eyebrow">RESULTADOS</span>
            <h2><?= e($reportMeta['title']) ?></h2>
            <p>
                <?= e(sindesg_units((int) $reportTotals['rows'])) ?> renglones mostrados
                · total referencial <?= e(sindesg_units((float) $reportTotals['quantity'])) ?>
                <?php if ((int) $reportTotals['rows'] >= $reportScreenLimit): ?>
                    · vista limitada a <?= e($reportScreenLimit) ?> renglones
                <?php endif; ?>
            </p>
        </div>
        <div class="reports-table-actions">
            <a class="secondary-button" href="<?= e(sindesg_url('?' . http_build_query($printParams))) ?>" target="_blank">PDF / Imprimir</a>
            <a class="primary-button" href="<?= e(sindesg_url('?' . http_build_query($excelParams))) ?>">Excel</a>
        </div>
    </div>

    <?php if ($reportRows === []): ?>
        <div class="reports-empty">
            <div class="reports-empty-mark">RE</div>
            <h3>Sin resultados</h3>
            <p>No hay registros que coincidan con esta combinación de filtros.</p>
        </div>
    <?php else: ?>
        <div class="reports-table-wrap">
            <table class="reports-table">
                <thead>
                <tr>
                    <?php foreach ($reportColumns as $label): ?>
                        <th><?= e($label) ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($reportRows as $row): ?>
                    <tr>
                        <?php foreach (array_keys($reportColumns) as $key): ?>
                            <td><?= e((string) ($row[$key] ?? '')) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
