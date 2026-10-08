<?php
declare(strict_types=1);

$statusLabels = [
    'staged' => 'En staging',
    'partially_imported' => 'Parcialmente archivado',
    'imported' => 'Archivado',
    'cancelled' => 'Cancelado',
];
?>
<section class="migration-hero">
    <div>
        <span class="eyebrow">FASE 13A · MIGRACIÓN CONTROLADA</span>
        <h1>Históricos sin contaminar el inventario</h1>
        <p>Cargue XLSX o CSV a una zona de staging. SINDESG conserva archivo, hoja y fila de origen, identifica incidencias y solo archiva filas listas. Esta fase no altera el kardex operativo.</p>
    </div>
    <div class="migration-safety-badge">
        <strong>0 movimientos</strong>
        <span>al cargar un archivo</span>
    </div>
</section>

<section class="migration-panel">
    <div class="migration-section-head">
        <div>
            <span class="eyebrow">NUEVO LOTE</span>
            <h2>Cargar archivo histórico</h2>
            <p>Máximo 30 MB. Formatos XLSX o CSV. Una huella SHA-256 impide cargar dos veces el mismo archivo.</p>
        </div>
    </div>

    <form class="migration-upload-form"
          method="post"
          enctype="multipart/form-data"
          action="<?= e(sindesg_url('?module=migration&action=upload')) ?>"
          data-migration-upload>
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">

        <label class="migration-file-box">
            <span>Archivo histórico *</span>
            <input type="file" name="historical_file" accept=".xlsx,.csv" required data-migration-file>
            <strong data-migration-filename>Seleccione un XLSX o CSV</strong>
            <small>El archivo original se conserva dentro del lote.</small>
        </label>

        <label>
            <span>Notas del lote</span>
            <textarea name="notes" rows="4" placeholder="Ej.: BASE DE DATOS REGISTRO 2025 · revisión inicial..."></textarea>
        </label>

        <button class="primary-button" type="submit">Cargar a staging</button>
    </form>
</section>

<section class="migration-panel">
    <div class="migration-section-head">
        <div><span class="eyebrow">LOTES</span><h2>Archivos cargados</h2></div>
        <span><?= e(count($historicalBatches)) ?> lotes</span>
    </div>

    <?php if ($historicalBatches === []): ?>
        <div class="migration-empty">
            <div class="migration-empty-mark">HIS</div>
            <h3>Sin históricos cargados</h3>
            <p>El primer archivo aparecerá aquí después de pasar por staging.</p>
        </div>
    <?php else: ?>
        <div class="migration-table-wrap">
            <table class="migration-table">
                <thead>
                <tr><th>Lote</th><th>Archivo</th><th>Hojas</th><th>Filas</th><th>Listas</th><th>Revisión</th><th>Archivadas</th><th>Estado</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($historicalBatches as $batch): ?>
                    <tr>
                        <td><strong><?= e($batch['batch_code']) ?></strong><small><?= e(date('d/m/Y H:i', strtotime((string) $batch['created_at']))) ?></small></td>
                        <td><span class="migration-filename"><?= e($batch['source_filename']) ?></span><small><?= e(strtoupper($batch['file_extension'])) ?> · <?= e(number_format(((int) $batch['file_size']) / 1024 / 1024, 2)) ?> MB</small></td>
                        <td><?= e($batch['sheet_count']) ?></td>
                        <td><?= e($batch['row_count']) ?></td>
                        <td class="migration-good"><?= e($batch['ready_count']) ?></td>
                        <td class="migration-warn"><?= e($batch['review_count']) ?></td>
                        <td><?= e($batch['imported_count']) ?></td>
                        <td><span class="migration-status <?= e($batch['status']) ?>"><?= e($statusLabels[$batch['status']] ?? $batch['status']) ?></span></td>
                        <td><a class="migration-link" href="<?= e(sindesg_url('?module=migration&action=show&id=' . (int) $batch['id'])) ?>">Revisar →</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
