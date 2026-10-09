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

$isClosedHistoricalBatch = ($historicalBatch['status'] ?? '') === 'imported';
$pendingHistoricalRows = (int) ($historicalBatch['ready_count'] ?? 0)
    + (int) ($historicalBatch['review_count'] ?? 0);
?>
<section class="migration-hero compact">
    <div>
        <span class="eyebrow"><?= e($historicalBatch['batch_code']) ?> · FASE 13A / 13B / 13C / 13D</span>
        <h1><?= e($historicalBatch['source_filename']) ?></h1>
        <p>Cargado por <?= e($historicalBatch['uploaded_by_label_snapshot']) ?> · SHA-256 <?= e(substr((string) $historicalBatch['file_sha256'], 0, 18)) ?>…</p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=migration')) ?>">← Históricos</a>
</section>

<section class="migration-stats">
    <?php if ($isClosedHistoricalBatch): ?>
        <article><span>Filas históricas</span><strong><?= e($historicalBatch['row_count']) ?></strong><small><?= e($historicalBatch['sheet_count']) ?> hojas conservadas</small></article>
        <article><span>Snapshots archivados</span><strong><?= e($snapshotCount) ?></strong><small>Control de inventario histórico</small></article>
        <article><span>Referencias conservadas</span><strong><?= e($historicalBatch['skipped_count'] ?? 0) ?></strong><small>Fuera de stock y kardex</small></article>
        <article><span>Pendientes</span><strong><?= e($pendingHistoricalRows) ?></strong><small>Lote cerrado sin incidencias</small></article>
    <?php else: ?>
        <article><span>Filas staged</span><strong><?= e($historicalBatch['row_count']) ?></strong><small><?= e($historicalBatch['sheet_count']) ?> hojas</small></article>
        <article><span>Listas</span><strong><?= e($historicalBatch['ready_count']) ?></strong><small>Mapeo inequívoco</small></article>
        <article><span>Revisión</span><strong><?= e($historicalBatch['review_count']) ?></strong><small>No se archivan automáticamente</small></article>
        <article><span>Archivadas</span><strong><?= e($snapshotCount) ?></strong><small>Snapshot histórico, no stock vivo</small></article>
    <?php endif; ?>
</section>

<section class="migration-panel">
    <div class="migration-section-head">
        <div>
            <span class="eyebrow">SEGURIDAD DEL LOTE</span>
            <h2><?= e($statusLabels[$historicalBatch['status']] ?? $historicalBatch['status']) ?></h2>
            <p><?= $isClosedHistoricalBatch
                ? 'El lote quedó cerrado y preservado como histórico. No alteró existencias ni movimientos del kardex.'
                : 'Archivar filas listas crea registros históricos de referencia. No inserta entradas ni salidas en el kardex actual.' ?></p>
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

<?php if (($mappingDiagnostics['total'] ?? 0) > 0): ?>
<section class="migration-panel">
    <div class="migration-section-head">
        <div>
            <span class="eyebrow">DIAGNÓSTICO DE CATÁLOGO · FASE 13B</span>
            <h2>Mapeo del Control de inventario</h2>
            <p>Este diagnóstico compara los 182 registros del control con el catálogo maestro. Preparar 13B crea únicamente datos maestros; no crea existencias, entradas, salidas ni movimientos.</p>
            <?php if (!($historicalCatalogStatus['schema_ready'] ?? false)): ?>
                <p class="migration-muted">Fase 13B pendiente de esquema: aplique <code>017_historical_catalog_preparation.sql</code>.</p>
            <?php elseif ((int) ($historicalCatalogStatus['variants'] ?? 0) === 182): ?>
                <p class="migration-good">Catálogo 13B preparado: <?= e($historicalCatalogStatus['categories']) ?> categorías · <?= e($historicalCatalogStatus['subcategories']) ?> subcategorías preservadas · <?= e($historicalCatalogStatus['products']) ?> productos · <?= e($historicalCatalogStatus['sizes']) ?> tallas · <?= e($historicalCatalogStatus['variants']) ?> variantes.</p>
            <?php endif; ?>
        </div>
        <?php if (
            ($historicalCatalogStatus['schema_ready'] ?? false)
            && $historicalBatch['status'] === 'staged'
            && (int) $historicalBatch['imported_count'] === 0
            && (int) ($historicalCatalogStatus['variants'] ?? 0) < 182
        ): ?>
            <form method="post"
                  action="<?= e(sindesg_url('?module=migration&action=prepare-catalog')) ?>"
                  data-confirm-message="Se crearán o actualizarán únicamente datos maestros del catálogo normalizado 2026 (5 categorías, 24 productos, 41 tallas y 182 variantes) y luego se reprocesará este lote. NO se modificará stock ni kardex. ¿Continuar?">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="batch_id" value="<?= e($historicalBatch['id']) ?>">
                <button class="primary-button" type="submit">Preparar catálogo 13B</button>
            </form>
        <?php endif; ?>
    </div>

    <section class="migration-stats">
        <article><span>Registros control</span><strong><?= e($mappingDiagnostics['total']) ?></strong><small>Base histórica detectada</small></article>
        <article><span>Mapeados</span><strong><?= e($mappingDiagnostics['mapped']) ?></strong><small>Código vinculado a variante actual</small></article>
        <article><span>Sin coincidencia</span><strong><?= e($mappingDiagnostics['missing_catalog']) ?></strong><small>No existe código equivalente en catálogo</small></article>
        <article><span>Ambiguos</span><strong><?= e($mappingDiagnostics['ambiguous']) ?></strong><small>Más de una variante coincide</small></article>
        <article><span>Diferencias entradas</span><strong><?= e($mappingDiagnostics['entry_mismatch']) ?></strong><small>Control ≠ detalle de ENTRADAS</small></article>
        <article><span>Diferencias salidas</span><strong><?= e($mappingDiagnostics['exit_mismatch']) ?></strong><small>Control ≠ detalle de SALIDAS</small></article>
        <article><span>Diferencia aritmética</span><strong><?= e($mappingDiagnostics['arithmetic_mismatch']) ?></strong><small>Inicial + entradas - salidas ≠ inventario</small></article>
        <article><span>Listas</span><strong><?= e($mappingDiagnostics['ready']) ?></strong><small>Sin incidencias pendientes</small></article>
    </section>

    <?php if ($unmappedControlExamples !== []): ?>
        <div class="migration-table-wrap">
            <table class="migration-table">
                <thead>
                <tr><th colspan="4">Ejemplos sin mapeo al catálogo actual</th></tr>
                <tr><th>Código ISTMO</th><th>Descripción</th><th>Tipo</th><th>Motivo</th></tr>
                </thead>
                <tbody>
                <?php foreach ($unmappedControlExamples as $example): ?>
                    <?php $exampleIssues = $decodeIssues($example['issues_json'] ?? null); ?>
                    <tr>
                        <td><code><?= e($example['normalized_code'] ?: '—') ?></code></td>
                        <td><?= e($example['normalized_description'] ?: '—') ?></td>
                        <td><?= e($example['normalized_category'] ?: '—') ?></td>
                        <td><?= e($exampleIssues[0] ?? 'Sin coincidencia de variante') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if (($mappingDiagnostics['exit_mismatch'] ?? 0) > 0): ?>
<section class="migration-panel">
    <div class="migration-section-head">
        <div>
            <span class="eyebrow">CONCILIACIÓN HISTÓRICA · FASE 13C</span>
            <h2>Incidencia pendiente del Control de inventario</h2>
            <p>La conciliación conserva el valor original y registra el valor normalizado aprobado. Solo actualiza staging/snapshot histórico; no modifica existencias ni movimientos vivos.</p>
        </div>
    </div>

    <?php if (!($reconciliationSchemaReady ?? false)): ?>
        <div class="migration-empty">
            <p>Aplique <code>database/schema/018_historical_reconciliation.sql</code> para habilitar la conciliación documentada.</p>
        </div>
    <?php elseif (($reconciliationPreview ?? []) === []): ?>
        <div class="migration-empty"><p>No hay una conciliación normalizada disponible para esta incidencia.</p></div>
    <?php else: ?>
        <?php foreach ($reconciliationPreview as $item): ?>
            <div class="migration-table-wrap">
                <table class="migration-table">
                    <thead>
                    <tr>
                        <th>Código ISTMO</th>
                        <th>Artículo</th>
                        <th>Control original</th>
                        <th>Detalle SALIDAS</th>
                        <th>Referencia normalizada</th>
                        <th>Resultado histórico</th>
                        <th>Acción</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td><code><?= e($item['code']) ?></code></td>
                        <td>
                            <strong><?= e($item['product'] ?: '—') ?></strong>
                            <?php if ($item['size'] !== ''): ?><small>Talla <?= e($item['size']) ?></small><?php endif; ?>
                        </td>
                        <td>
                            <strong><?= e($item['control_exits'] ?? '—') ?> salidas</strong>
                            <small>Saldo: <?= e($item['control_quantity'] ?? '—') ?></small>
                        </td>
                        <td><strong><?= e($item['detail_exits'] ?? '—') ?> salidas</strong></td>
                        <td>
                            <strong><?= e($item['reference']['exits_quantity']) ?> salidas</strong>
                            <small><?= e($item['reference']['source_variant_id']) ?></small>
                        </td>
                        <td>
                            <strong><?= e($item['reference']['recalculated_quantity']) ?></strong>
                            <small><?= e($item['reference']['reason']) ?></small>
                        </td>
                        <td>
                            <form method="post"
                                  action="<?= e(sindesg_url('?module=migration&action=reconcile-control')) ?>"
                                  data-confirm-message="Se conservará el valor original y se registrará la conciliación documentada de esta única fila usando la fuente normalizada. Esto NO modifica stock ni kardex. ¿Continuar?">
                                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="batch_id" value="<?= e($historicalBatch['id']) ?>">
                                <input type="hidden" name="row_id" value="<?= e($item['row_id']) ?>">
                                <button class="primary-button" type="submit">Conciliar incidencia</button>
                            </form>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php
$referenceReviewTotal = 0;
foreach (($referenceReviewSummary ?? []) as $referenceSummaryRow) {
    $referenceReviewTotal += (int) ($referenceSummaryRow['total'] ?? 0);
}
$controlFullyArchived = (int) ($mappingDiagnostics['total'] ?? 0) > 0
    && (int) $snapshotCount === (int) ($mappingDiagnostics['total'] ?? 0)
    && (int) ($mappingDiagnostics['ready'] ?? 0) === 0
    && (int) ($mappingDiagnostics['review'] ?? 0) === 0;
?>
<?php if ($historicalBatch['status'] !== 'cancelled'): ?>
<section class="migration-panel">
    <div class="migration-section-head">
        <div>
            <span class="eyebrow">CIERRE CONTROLADO · FASE 13D</span>
            <h2>Cerrar referencias históricas</h2>
            <p>Las hojas de ENTRADAS, SALIDAS, rendiciones, reportes y filas sin estructura se conservarán con su archivo, hoja, fila y contenido original, pero quedarán fuera del snapshot de stock. No se convierten en movimientos vivos.</p>
        </div>
        <?php if ($historicalBatch['status'] !== 'imported' && $controlFullyArchived && $referenceReviewTotal > 0): ?>
            <form method="post"
                  action="<?= e(sindesg_url('?module=migration&action=finalize-references')) ?>"
                  data-confirm-message="Se marcarán <?= e($referenceReviewTotal) ?> filas no operativas como referencia histórica conservada. No se borrarán y NO se modificará stock ni kardex. El lote quedará cerrado. ¿Continuar?">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="batch_id" value="<?= e($historicalBatch['id']) ?>">
                <button class="primary-button" type="submit">Cerrar lote histórico</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($historicalBatch['status'] === 'imported'): ?>
        <div class="migration-empty">
            <p class="migration-good">Lote histórico cerrado: <?= e($snapshotCount) ?> snapshots de Control archivados y <?= e($historicalBatch['skipped_count'] ?? 0) ?> filas conservadas como referencia no operativa.</p>
        </div>
    <?php elseif (!$controlFullyArchived): ?>
        <div class="migration-empty">
            <p>Primero archive todas las filas listas del <strong>Control de inventario</strong>. El cierre 13D se habilitará cuando los <?= e($mappingDiagnostics['total'] ?? 0) ?> registros del control estén archivados y sin incidencias.</p>
        </div>
    <?php elseif ($referenceReviewTotal === 0): ?>
        <div class="migration-empty"><p>No quedan referencias históricas pendientes de cierre.</p></div>
    <?php else: ?>
        <div class="migration-table-wrap">
            <table class="migration-table">
                <thead><tr><th>Tipo de referencia</th><th>Filas conservadas</th><th>Tratamiento</th></tr></thead>
                <tbody>
                <?php foreach ($referenceReviewSummary as $referenceSummaryRow): ?>
                    <?php $referenceProfile = (string) ($referenceSummaryRow['row_profile'] ?? ''); ?>
                    <tr>
                        <td><?= e($profileLabels[$referenceProfile] ?? $referenceProfile) ?></td>
                        <td><strong><?= e($referenceSummaryRow['total']) ?></strong></td>
                        <td>Conservar trazabilidad; excluir de snapshot y kardex.</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="migration-panel">
    <div class="migration-section-head">
        <div>
            <span class="eyebrow"><?= $isClosedHistoricalBatch ? 'CONTENIDO HISTÓRICO' : 'HOJAS' ?></span>
            <h2><?= $isClosedHistoricalBatch ? 'Contenido del lote archivado' : 'Clasificación detectada' ?></h2>
        </div>
    </div>

    <div class="migration-sheet-grid">
        <?php foreach ($historicalSheets as $sheet): ?>
            <a class="<?= $selectedSheetId === (int) $sheet['id'] ? 'active' : '' ?>"
               href="<?= e(sindesg_url('?module=migration&action=show&id=' . (int) $historicalBatch['id'] . '&sheet_id=' . (int) $sheet['id'] . ($selectedRowStatus !== '' ? '&status=' . rawurlencode($selectedRowStatus) : ''))) ?>">
                <strong><?= e($sheet['sheet_name']) ?></strong>
                <span><?= e($profileLabels[$sheet['profile']] ?? $sheet['profile']) ?></span>
                <?php if ($isClosedHistoricalBatch): ?>
                    <?php if (in_array($sheet['profile'], ['inventory_normalized_2026', 'inventory_control_2026'], true)): ?>
                        <small><?= e($sheet['imported_rows']) ?> archivadas · <?= e($sheet['ready_rows'] + $sheet['review_rows']) ?> pendientes</small>
                    <?php else: ?>
                        <small><?= e($sheet['skipped_rows'] ?? 0) ?> referencias conservadas · <?= e($sheet['ready_rows'] + $sheet['review_rows']) ?> pendientes</small>
                    <?php endif; ?>
                <?php else: ?>
                    <small><?= e($sheet['staged_rows']) ?> staged · <?= e($sheet['ready_rows']) ?> listas · <?= e($sheet['review_rows']) ?> revisión</small>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="migration-panel">
    <div class="migration-section-head">
        <div>
            <span class="eyebrow"><?= $isClosedHistoricalBatch ? 'ARCHIVO HISTÓRICO' : 'FILAS' ?></span>
            <h2><?= $isClosedHistoricalBatch ? 'Detalle histórico del lote' : 'Revisión de staging' ?></h2>
        </div>
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
        <p class="migration-table-note"><?= $isClosedHistoricalBatch
            ? 'La vista muestra hasta 500 filas del archivo histórico. Omitida significa conservada como referencia y excluida de stock y kardex.'
            : 'La vista muestra hasta 500 filas. Las filas en revisión no se archivan automáticamente.' ?></p>
    <?php endif; ?>
</section>
