<?php
declare(strict_types=1);

/** @var array|null $bootsPreview */
$report = $bootsPreview ?? null;
$issues = is_array($report) ? (array) ($report['issues'] ?? []) : [];
?>
<section class="request-hero compact">
    <div>
        <span class="eyebrow">PILOTO DE DOTACIÓN · SIN MOVIMIENTOS</span>
        <h1>Validar listado de botas para P4</h1>
        <p>Adjunte el Excel o CSV de dotación al P4. Comprueba requisición, cantidades,
           posiciones repetidas y tallas antes de generar una transferencia real.</p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=requests')) ?>">← Solicitudes</a>
</section>

<section class="request-panel">
    <h2>Carga de archivo para revisión</h2>
    <p>Esta etapa es una <strong>vista previa que no se guarda</strong>. No crea solicitudes,
       transferencias, existencias, kardex ni entregas individuales. No carga datos a RRHH2029.</p>
    <form method="post" enctype="multipart/form-data"
          action="<?= e(sindesg_url('?module=requests&action=preview-boots')) ?>">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <label>
            <span>Listado nominal de dotación (XLSX o CSV, hasta 15 MB)</span>
            <input type="file" name="boots_workbook" accept=".xlsx,.csv" required>
        </label>
        <button type="submit" class="primary-button">Analizar archivo sin registrar movimientos</button>
    </form>
</section>

<?php if (is_array($report)): ?>
<section class="request-panel">
    <div class="request-section-head">
        <div>
            <span class="eyebrow">DOCUMENTO DE ORIGEN · SOLO CONSULTA</span>
            <h2><?= e($report['document']['source_filename']) ?></h2>
        </div>
        <span class="request-total"><?= (int) $report['nominal_count'] ?> asignaciones</span>
    </div>
    <dl class="request-dl">
        <div><dt>Zona indicada</dt><dd><?= e($report['document']['zone'] ?: 'No detectada') ?></dd></div>
        <div><dt>Requisición</dt><dd><?= e($report['document']['requisition'] ?: 'No detectada') ?></dd></div>
        <div><dt>Contrato</dt><dd><?= e($report['document']['contract'] ?: 'No detectado') ?></dd></div>
        <div><dt>Fecha del documento</dt><dd><?= e($report['document']['date'] ?: 'No detectada') ?></dd></div>
        <div><dt>Unidades en encabezado</dt><dd><?= e($report['document']['declared_quantity'] ?? '—') ?></dd></div>
        <div><dt>Filas nominales</dt><dd><?= (int) $report['nominal_count'] ?></dd></div>
        <div><dt>Tallas válidas, pendiente RRHH</dt><dd><?= (int) $report['validated_size_count'] ?></dd></div>
        <div><dt>Total resumen por tallas</dt><dd><?= e($report['summary_total'] ?? '—') ?></dd></div>
        <div><dt>Filas con firma anotada</dt><dd><?= (int) $report['signature_rows'] ?></dd></div>
    </dl>
</section>

<section class="request-panel">
    <div class="request-section-head">
        <div><span class="eyebrow">CONCILIACIÓN</span><h2>Incidencias a resolver</h2></div>
        <span class="request-total"><?= count($issues) ?> observaciones</span>
    </div>
    <?php if ($issues === []): ?>
        <p>No se detectaron incidencias estructurales. Aún falta validar RRHH y disponibilidad física.</p>
    <?php else: ?>
        <div class="responsive-table">
            <table>
                <thead><tr><th>Fila Excel</th><th>Tipo</th><th>Observación</th></tr></thead>
                <tbody>
                <?php foreach ($issues as $issue): ?>
                    <tr>
                        <td><?= (int) $issue['excel_row'] ?: 'Encabezado / resumen' ?></td>
                        <td><?= e($issue['type']) ?></td>
                        <td><?= e($issue['message']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="request-panel">
    <div class="request-section-head">
        <div><span class="eyebrow">PLANIFICACIÓN</span><h2>Asignaciones por talla validada</h2></div>
        <span class="request-total">NO es stock disponible</span>
    </div>
    <div class="responsive-table">
        <table>
            <thead><tr><th>Talla</th><th>Listado válido</th><th>Resumen Excel</th><th>Diferencia</th></tr></thead>
            <tbody>
            <?php foreach ($report['size_counts'] as $size => $total): ?>
                <?php $source = (int) ($report['summary_sizes'][$size] ?? 0); ?>
                <tr>
                    <td><?= e($size) ?></td>
                    <td><?= (int) $total ?></td>
                    <td><?= $source ?></td>
                    <td><?= (int) $total - $source ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p><strong>Próximo paso:</strong> conciliar posiciones con RRHH2029, normalizar tallas,
    identificar el producto/variante oficial, y vincular el comprobante firmado al P4 correcto.
    Las 723 asignaciones no serán descontadas de un saldo inicial todavía desconocido.</p>
</section>
<?php endif; ?>
