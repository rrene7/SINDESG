<?php
declare(strict_types=1);

$statusLabels = [
    'staged' => 'En staging',
    'partially_imported' => 'Parcialmente archivado',
    'imported' => 'Archivado',
    'cancelled' => 'Cancelado',
];
$rowStatusLabels = [
    'ready' => 'Lista',
    'review' => 'Revisión',
    'imported' => 'Archivada',
    'skipped' => 'Omitida',
];
$profileLabels = [
    'inventory_normalized_2026' => 'Inventario normalizado',
    'inventory_control_2026' => 'Control de inventario',
    'historical_entries_2024_2026' => 'Entradas históricas',
    'historical_exits_2024_2026' => 'Salidas históricas',
    'reference_report' => 'Reporte de referencia',
    'rendition_report' => 'Rendición histórica',
    'legacy_inventory_summary' => 'Resumen histórico',
    'source_issues' => 'Incidencias de origen',
    'unclassified' => 'Sin clasificar',
];

$decodeIssues = static function (?string $json): array {
    if ($json === null || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
};
?>
<section class="migration-hero compact">
    <div>
        <span class="eyebrow"><?= e($historicalBatch['batch_code']) ?> · FASE 13A</span>
        <h1><?= e($historicalBatch['source_filename']) ?></h1>
        <p>Cargado por <?= e($historicalBatch['uploaded_by_label_snapshot']) ?> · SHA-256 <?= e(substr((string) $historicalBatch['file_sha256'], 0, 18)) ?>…</p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=migration')) ?>">← Históricos</a>
</section>

<section class="migration-stats">
    <article><span>Filas staged</span><strong><?= e($historicalBatch['row_count']) ?></strong><small><?= e($historicalBatch['sheet_count']) ?> hojas</small></article>
    <article><span>Listas</span><strong><?= e($historicalBatch['ready_count']) ?></strong><small>Mapeo inequívoco</small></article>
    <article><span>Revisión</span><strong><?= e($historicalBatch['review_count']) ?></strong><small>No se archivan automáticamente</small></article>
    <article><span>Archivadas</span><strong><?= e($snapshotCount) ?></strong><small>Snapshot histórico, no stock vivo</small></article>
</section>

<section class="migration-panel">
    <div class="migration-section-head">
        <div>
            <span class="eyebrow">SEGURIDAD DEL LOTE</span>
            <h2><?= e($statusLabels[$historicalBatch['status']] ?? $historicalBatch['status']) ?></h2>
            <p>Archivar filas listas crea registros históricos de referencia. No inserta entradas ni salidas en el kardex actual.</p>
        </div>
        <div class="migration-actions">
            <?php if (!in_array($historicalBatch['status'], ['cancelled', 'imported'], true) && (int) $historicalBatch['ready_count'] > 0): ?>
                <form method="post"
                      action="<?= e(sindesg_url('?module=migration&action=archive-ready')) ?>"
                      data-confirm-message="Se archivarán únicamente las filas listas. El stock actual NO cambiará. ¿Continuar?">
                    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="batch_id" value="<?= e($historicalBatch['id']) ?>">
                    <button class="primary-button" type="submit">Archivar <?= e($historicalBatch['ready_count']) ?> filas listas</button>
                </form>
            <?php endif; ?>

            <?php if ($historicalBatch['status'] === 'staged' && (int) $historicalBatch['imported_count'] === 0): ?>
                <form method="post"
                      action="<?= e(sindesg_url('?module=migration&action=reprocess')) ?>"
                      data-confirm-message="Se volverá a leer el archivo original y se reemplazará únicamente el staging de este lote. El inventario y el kardex NO cambiarán. ¿Continuar?">
                    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="batch_id" value="<?= e($historicalBatch['id']) ?>">
                    <button class="secondary-button" type="submit">Reprocesar lote</button>
                </form>

                <form method="post"
                      action="<?= e(sindesg_url('?module=migration&action=cancel')) ?>"
                      data-confirm-message="El lote quedará cancelado. No se eliminará la trazabilidad. ¿Continuar?">
                    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="batch_id" value="<?= e($historicalBatch['id']) ?>">
                    <button class="migration-danger-button" type="submit">Cancelar lote</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="migration-panel">
    <div class="migration-section-head">
        <div><span class="eyebrow">HOJAS</span><h2>Clasificación detectada</h2></div>
    </div>

    <div class="migration-sheet-grid">
        <?php foreach ($historicalSheets as $sheet): ?>
            <a class="<?= $selectedSheetId === (int) $sheet['id'] ? 'active' : '' ?>"
               href="<?= e(sindesg_url('?module=migration&action=show&id=' . (int) $historicalBatch['id'] . '&sheet_id=' . (int) $sheet['id'] . ($selectedRowStatus !== '' ? '&status=' . rawurlencode($selectedRowStatus) : ''))) ?>">
                <strong><?= e($sheet['sheet_name']) ?></strong>
                <span><?= e($profileLabels[$sheet['profile']] ?? $sheet['profile']) ?></span>
                <small><?= e($sheet['staged_rows']) ?> staged · <?= e($sheet['ready_rows']) ?> listas · <?= e($sheet['review_rows']) ?> revisión</small>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="migration-panel">
    <div class="migration-section-head">
        <div><span class="eyebrow">FILAS</span><h2>Revisión de staging</h2></div>
        <form class="migration-row-filters" method="get" action="<?= e(sindesg_url()) ?>">
            <input type="hidden" name="module" value="migration">
            <input type="hidden" name="action" value="show">
            <input type="hidden" name="id" value="<?= e($historicalBatch['id']) ?>">

            <select name="sheet_id">
                <option value="0">Todas las hojas</option>
                <?php foreach ($historicalSheets as $sheet): ?>
                    <option value="<?= e($sheet['id']) ?>" <?= $selectedSheetId === (int) $sheet['id'] ? 'selected' : '' ?>><?= e($sheet['sheet_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="status">
                <option value="">Todos los estados</option>
                <?php foreach ($rowStatusLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $selectedRowStatus === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>

            <button class="secondary-button" type="submit">Filtrar</button>
        </form>
    </div>

    <?php if ($historicalRows === []): ?>
        <div class="migration-empty"><p>No hay filas para este filtro.</p></div>
    <?php else: ?>
        <div class="migration-table-wrap migration-rows-wrap">
            <table class="migration-table migration-rows-table">
                <thead>
                <tr><th>Origen</th><th>Código</th><th>Descripción</th><th>Talla</th><th>Inicial</th><th>Entradas</th><th>Salidas</th><th>Recalculado</th><th>Mapeo SINDESG</th><th>Estado</th><th>Incidencias</th></tr>
                </thead>
                <tbody>
                <?php foreach ($historicalRows as $row): ?>
                    <?php $issues = $decodeIssues($row['issues_json']); ?>
                    <tr>
                        <td><strong><?= e($row['sheet_name']) ?></strong><small>Fila <?= e($row['source_row_number']) ?></small></td>
                        <td><code><?= e($row['normalized_code'] ?: '—') ?></code></td>
                        <td>
                            <strong><?= e($row['normalized_product'] ?: $row['normalized_description'] ?: '—') ?></strong>
                            <?php if ($row['normalized_category']): ?><small><?= e($row['normalized_category']) ?></small><?php endif; ?>
                        </td>
                        <td><?= e($row['normalized_size'] ?: '—') ?></td>
                        <td><?= e($row['normalized_initial_quantity'] ?? '—') ?></td>
                        <td><?= e($row['normalized_entries_quantity'] ?? '—') ?></td>
                        <td><?= e($row['normalized_exits_quantity'] ?? '—') ?></td>
                        <td><strong><?= e($row['normalized_quantity'] ?? '—') ?></strong></td>
                        <td>
                            <?php if ($row['mapping_variant_id']): ?>
                                <strong><?= e($row['mapped_product_name']) ?></strong>
                                <small><?= e($row['mapped_internal_code']) ?><?= $row['mapped_size_name'] ? ' · ' . e($row['mapped_size_name']) : '' ?></small>
                            <?php else: ?>
                                <span class="migration-muted">Sin mapeo</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="migration-row-status <?= e($row['row_status']) ?>"><?= e($rowStatusLabels[$row['row_status']] ?? $row['row_status']) ?></span></td>
                        <td>
                            <?php if ($issues === []): ?>
                                <span class="migration-good">Sin incidencias</span>
                            <?php else: ?>
                                <ul class="migration-issue-list">
                                    <?php foreach ($issues as $issue): ?><li><?= e($issue) ?></li><?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="migration-table-note">La vista muestra hasta 500 filas. Las filas en revisión no se archivan automáticamente.</p>
    <?php endif; ?>
</section>
