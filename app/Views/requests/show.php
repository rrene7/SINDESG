<?php
declare(strict_types=1);

$statusLabels = [
    'draft' => 'Borrador',
    'submitted' => 'Enviada',
    'in_review' => 'En revisión',
    'approved' => 'Aprobada',
    'partially_approved' => 'Aprobada parcial',
    'rejected' => 'Rechazada',
    'fulfilled' => 'Atendida',
    'closed' => 'Cerrada',
];

$totalRequested = array_sum(array_map(static fn (array $row): float => (float) $row['quantity_requested'], $requestItems));
$totalApproved = array_sum(array_map(static fn (array $row): float => (float) $row['quantity_approved'], $requestItems));
$totalDispatched = array_sum(array_map(static fn (array $row): float => (float) ($row['quantity_dispatched'] ?? 0), $requestItems));
$totalReceived = array_sum(array_map(static fn (array $row): float => (float) ($row['quantity_received'] ?? 0), $requestItems));
$totalDelivered = array_sum(array_map(static fn (array $row): float => (float) ($row['quantity_delivered'] ?? 0), $requestItems));
$remainingToDistribute = max(0.0, $totalApproved - $totalDispatched);

// Se consultan las variantes ACTIVAS aunque su existencia sea cero.
// El filtro busca coincidencias en artículo, categoría, marca y códigos.
$catalogVariantOptions = [];
foreach ($variantOptions as $entry) {
    $stock = number_format((float) $entry['central_stock'], 0, '.', ',');
    $label = $entry['category_name'] . ' · ' . $entry['product_name']
        . ' · Talla ' . ($entry['size_name'] ?: '—')
        . ' · ' . $entry['variant_code']
        . ' · Central: ' . $stock;
    $terms = implode(' ', array_filter([
        $entry['category_name'], $entry['product_name'], $entry['size_name'],
        $entry['brand_name'], $entry['variant_code'], $entry['institutional_code'],
        $entry['supplier_code'], $entry['variant_color'],
    ], static fn ($value): bool => trim((string) $value) !== ''));
    $catalogVariantOptions[] = [
        'value' => (int) $entry['id'],
        'label' => $label,
        'terms' => $terms,
    ];
}
$catalogProductOptions = [];
foreach ($nominalProducts ?? [] as $product) {
    $label = $product['category_name'] . ' · ' . $product['name'];
    $catalogProductOptions[] = [
        'value' => (int) $product['id'],
        'label' => $label,
        'terms' => $label . ' ' . ($product['internal_code'] ?? ''),
    ];
}

?>
<section class="request-detail-hero">
    <div>
        <span class="eyebrow">SOLICITUD <?= e($serviceRequest['request_code']) ?></span>
        <h1><?= e($serviceRequest['p4_code'] . ' · ' . ($serviceRequest['p4_name_snapshot'] ?: $serviceRequest['p4_internal_name'])) ?></h1>
        <p><?= e($serviceRequest['requested_by_label_snapshot']) ?> · <?= e(date('d/m/Y H:i', strtotime((string) $serviceRequest['requested_at']))) ?></p>
    </div>
    <div class="request-detail-status">
        <span class="request-status <?= e($serviceRequest['status']) ?>"><?= e($statusLabels[$serviceRequest['status']] ?? $serviceRequest['status']) ?></span>
        <strong><?= e(number_format($totalRequested, 0, '.', ',')) ?></strong>
        <small>unidades solicitadas</small>
    </div>
</section>

<section class="request-detail-grid">
    <article class="request-panel">
        <span class="eyebrow">CABECERA</span>
        <h2>Datos de la solicitud</h2>
        <dl class="request-dl">
            <div><dt>Código</dt><dd><?= e($serviceRequest['request_code']) ?></dd></div>
            <div><dt>P4</dt><dd><?= e($serviceRequest['p4_code']) ?></dd></div>
            <div><dt>Unidad P4</dt><dd><?= e($serviceRequest['p4_name_snapshot'] ?: '—') ?></dd></div>
            <div><dt>Solicitante</dt><dd><?= e($serviceRequest['requested_by_label_snapshot']) ?></dd></div>
            <div><dt>Cédula</dt><dd><?= e($serviceRequest['requested_by_document_snapshot'] ?: '—') ?></dd></div>
            <div><dt>Posición</dt><dd><?= e($serviceRequest['requested_by_position_snapshot'] ?: '—') ?></dd></div>
        </dl>
        <?php if (!empty($serviceRequest['notes'])): ?>
            <div class="request-note"><strong>Observaciones</strong><p><?= e($serviceRequest['notes']) ?></p></div>
        <?php endif; ?>
    </article>

    <aside class="request-panel request-workflow">
        <span class="eyebrow">FLUJO</span>
        <h2><?= e($statusLabels[$serviceRequest['status']] ?? $serviceRequest['status']) ?></h2>

        <?php if ($serviceRequest['status'] === 'draft'): ?>
            <p>Agregue todos los artículos requeridos y luego envíe la solicitud al Almacén Central.</p>
            <form method="post" action="<?= e(sindesg_url('?module=requests&action=submit')) ?>">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="request_id" value="<?= e($serviceRequest['id']) ?>">
                <button class="primary-button request-full-button" type="submit" <?= $requestItems === [] ? 'disabled' : '' ?>>Enviar solicitud</button>
            </form>
        <?php elseif ($serviceRequest['status'] === 'submitted'): ?>
            <p>La solicitud fue enviada y está pendiente de revisión en Servicios Generales.</p>
            <form method="post" action="<?= e(sindesg_url('?module=requests&action=start-review')) ?>">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="request_id" value="<?= e($serviceRequest['id']) ?>">
                <button class="primary-button request-full-button" type="submit">Iniciar revisión</button>
            </form>
        <?php elseif ($serviceRequest['status'] === 'in_review'): ?>
            <p>Defina las cantidades aprobadas. La aprobación compromete disponibilidad, pero todavía no mueve inventario.</p>
        <?php else: ?>
            <div class="request-decision-summary">
                <span><strong><?= e(number_format($totalApproved, 0, '.', ',')) ?></strong><small>unidades aprobadas</small></span>
                <span><strong><?= e($serviceRequest['reviewed_by_label_snapshot'] ?: '—') ?></strong><small>revisor</small></span>
            </div>
            <?php if (!empty($serviceRequest['review_notes'])): ?>
                <div class="request-note"><strong>Observación de revisión</strong><p><?= e($serviceRequest['review_notes']) ?></p></div>
            <?php endif; ?>

            <?php if (in_array($serviceRequest['status'], ['approved', 'partially_approved'], true) && $remainingToDistribute > 0): ?>
                <a class="primary-button request-full-button"
                   href="<?= e(sindesg_url('?module=distribution&action=new&request_id=' . (int) $serviceRequest['id'])) ?>">
                    Preparar distribución
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </aside>
</section>

<?php if (in_array($serviceRequest['status'], ['approved', 'partially_approved', 'fulfilled'], true)): ?>
<section class="request-panel request-verification-panel">
    <div class="request-section-head">
        <div>
            <span class="eyebrow">CONTROL DE COMPROMISO</span>
            <h2>Solicitud → reserva lógica → distribución</h2>
        </div>
        <span class="request-verification-badge <?= ((int) ($requestCommitmentEvidence['direct_movements'] ?? 0)) === 0 ? 'ok' : 'warn' ?>">
            <?= ((int) ($requestCommitmentEvidence['direct_movements'] ?? 0)) === 0 ? '0 movimientos directos' : 'Revisar movimientos directos' ?>
        </span>
    </div>

    <div class="request-verification-summary">
        <span><strong><?= e(number_format((float) ($requestCommitmentEvidence['approved'] ?? 0), 0, '.', ',')) ?></strong><small>Aprobado</small></span>
        <span><strong><?= e(number_format((float) ($requestCommitmentEvidence['committed'] ?? 0), 0, '.', ',')) ?></strong><small>Comprometido</small></span>
        <span><strong><?= e(number_format((float) ($requestCommitmentEvidence['dispatched'] ?? 0), 0, '.', ',')) ?></strong><small>Despachado</small></span>
        <span><strong><?= e(number_format((float) ($requestCommitmentEvidence['received'] ?? 0), 0, '.', ',')) ?></strong><small>Recibido P4</small></span>
    </div>

    <p class="request-verification-copy">
        Aprobar una solicitud no mueve inventario. La cantidad aprobada queda comprometida hasta que una distribución genera los movimientos reales Central → Tránsito → P4.
    </p>
</section>
<?php endif; ?>

<?php if ($serviceRequest['status'] === 'draft'): ?>
<section class="request-panel">
    <div class="request-section-head">
        <div>
            <span class="eyebrow">NUEVO RENGLÓN</span>
            <h2>Agregar necesidad</h2>
            <p>El beneficiario es opcional. Déjelo vacío cuando el P4 solicite una cantidad consolidada.</p>
        </div>
    </div>

    <form class="request-item-form" method="post" action="<?= e(sindesg_url('?module=requests&action=add-item')) ?>">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="request_id" value="<?= e($serviceRequest['id']) ?>">

        <?php
        $catalogPicker = [
            'id' => 'request-single-variant',
            'name' => 'product_variant_id',
            'label' => 'Artículo y variante',
            'placeholder' => 'Buscar botas, talla 8.5, ISTMO o nombre...',
            'options' => $catalogVariantOptions,
        ];
        require __DIR__ . '/catalog-picker.php';
        ?>

        <label>
            <span>Cantidad *</span>
            <input type="number" name="quantity_requested" min="0.001" step="0.001" required placeholder="0">
        </label>

        <div class="request-employee-picker request-beneficiary" data-beneficiary-picker data-search-url="<?= e($employeeSearchUrl) ?>">
            <label>
                <span>Beneficiario · opcional</span>
                <input type="search" data-beneficiary-search autocomplete="off" placeholder="Nombre, cédula o posición...">
                <input type="hidden" name="beneficiary_employee_id" data-beneficiary-id>
            </label>
            <div class="request-picker-results" data-beneficiary-results hidden></div>
            <div class="request-picker-selection" data-beneficiary-selection hidden></div>
        </div>

        <label>
            <span>Observación</span>
            <input type="text" name="item_notes" maxlength="255" placeholder="Opcional">
        </label>

        <button class="primary-button" type="submit">Agregar</button>
    </form>
</section>
<?php endif; ?>

<section class="request-panel">
    <div class="request-section-head">
        <div><span class="eyebrow">DETALLE</span><h2>Necesidades registradas</h2></div>
        <span class="request-total"><?= e(count($requestItems)) ?> renglones</span>
    </div>

    <?php if ($requestItems === []): ?>
        <div class="request-empty compact"><p>El borrador todavía no contiene artículos.</p></div>
    <?php else: ?>
        <div class="responsive-table">
            <table>
                <thead>
                <tr><th>Artículo</th><th>Variante</th><th>Talla</th><th>Beneficiario</th><th>Solicitado</th><th>Stock central</th><th>Comprometible</th><th>Aprobado</th><th>Despachado</th><th>Recibido P4</th><th>Entregado funcionario</th><?php if ($serviceRequest['status'] === 'draft'): ?><th></th><?php endif; ?></tr>
                </thead>
                <tbody>
                <?php foreach ($requestItems as $item): ?>
                    <tr>
                        <td><strong><?= e($item['product_name']) ?></strong><small class="request-cell-sub"><?= e($item['category_name']) ?></small></td>
                        <td><code><?= e($item['variant_code']) ?></code></td>
                        <td><?= e($item['size_name'] ?: '—') ?></td>
                        <td>
                            <?= e($item['beneficiary_label_snapshot'] ?: 'Cantidad consolidada') ?>
                            <?php if (!empty($item['beneficiary_employee_id'])): ?>
                                <small class="request-cell-sub">
                                    <?php if (!empty($item['last_delivered_same_product_at'])): ?>
                                        Última entrega de este artículo:
                                        <?= e(date('d/m/Y', strtotime((string) $item['last_delivered_same_product_at']))) ?>
                                    <?php else: ?>
                                        Sin entrega registrada de este artículo en SINDESG
                                    <?php endif; ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td class="request-qty"><?= e(number_format((float) $item['quantity_requested'], 3, '.', ',')) ?></td>
                        <td><?= e(number_format((float) $item['central_stock'], 3, '.', ',')) ?></td>
                        <td><?= e(number_format((float) $item['available_to_approve'], 3, '.', ',')) ?></td>
                        <td class="request-qty approved"><?= e(number_format((float) $item['quantity_approved'], 3, '.', ',')) ?></td>
                        <td><?= e(number_format((float) ($item['quantity_dispatched'] ?? 0), 3, '.', ',')) ?></td>
                        <td><?= e(number_format((float) ($item['quantity_received'] ?? 0), 3, '.', ',')) ?></td>
                        <td>
                            <?= e(number_format((float) ($item['quantity_delivered'] ?? 0), 3, '.', ',')) ?>
                            <?php
                            $pendingIndividual = max(
                                0.0,
                                (float) ($item['quantity_received'] ?? 0)
                                - (float) ($item['quantity_delivered'] ?? 0)
                            );
                            ?>
                            <?php if (!empty($item['beneficiary_employee_id']) && $pendingIndividual > 0.000001): ?>
                                <a class="request-cell-sub"
                                   href="<?= e(sindesg_url('?module=deliveries&action=new&request_item_id=' . (int) $item['id'])) ?>">
                                    Entregar <?= e(number_format($pendingIndividual, 3, '.', ',')) ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <?php if ($serviceRequest['status'] === 'draft'): ?>
                            <td>
                                <form method="post" action="<?= e(sindesg_url('?module=requests&action=remove-item')) ?>">
                                    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="request_id" value="<?= e($serviceRequest['id']) ?>">
                                    <input type="hidden" name="item_id" value="<?= e($item['id']) ?>">
                                    <button class="request-remove-button" type="submit">×</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($serviceRequest['status'] === 'draft'): ?>
<section class="request-panel">
    <div class="request-section-head">
        <div>
            <span class="eyebrow">CAPTURA RÁPIDA P4</span>
            <h2>Asignar dotación a varios funcionarios</h2>
            <p>Busque en RRHH, seleccione varios funcionarios y asigne la misma talla
                a todos en una sola operación. También puede importar un archivo XLSX.</p>
        </div>
    </div>

    <form method="post"
          action="<?= e(sindesg_url('?module=requests&action=add-nominal-bulk')) ?>"
          data-bulk-nominal-picker data-search-url="<?= e($employeeSearchUrl) ?>"
          data-last-delivery-url="<?= e(sindesg_url('public/api/last-nominal-delivery.php')) ?>">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="request_id" value="<?= (int) $serviceRequest['id'] ?>">
        <?php
        $catalogPicker = [
            'id' => 'request-multiple-variant',
            'name' => 'product_variant_id',
            'label' => 'Artículo y talla para los funcionarios seleccionados',
            'placeholder' => 'Ej.: botas 8.5, código, categoría, marca...',
            'options' => $catalogVariantOptions,
        ];
        require __DIR__ . '/catalog-picker.php';
        ?>
        <label>
            <span>Buscar por nombre, cédula o posición</span>
            <input type="search" data-bulk-search autocomplete="off"
                   placeholder="Escriba al menos 2 caracteres...">
        </label>
        <p class="request-cell-sub" data-bulk-results-count role="status" aria-live="polite">
            Escriba el nombre, la cédula o la posición para consultar RRHH2029.
        </p>
        <div class="request-picker-results" data-bulk-results hidden
             aria-label="Funcionarios encontrados en RRHH2029"></div>
        <button type="button" class="secondary-button" data-bulk-select-page hidden>
            Seleccionar todos los resultados visibles
        </button>
        <p aria-live="polite" data-bulk-counter>0 funcionarios seleccionados</p>
        <div data-bulk-selection class="request-picker-selection"></div>
        <button type="submit" class="primary-button" data-bulk-submit disabled>
            Agregar selección al borrador
        </button>
        <p class="request-cell-sub">Máximo 500 por operación. Puede seleccionar personal de cualquier
           zona o dirección, incluyendo personal prestado. La fecha de última
           entrega es informativa: el P4 decide si corresponde solicitar.
           Se mantiene la validación de RRHH y de duplicados dentro de esta solicitud.
           Sin descontar inventario.</p>
    </form>
</section>

<section class="request-panel">
    <div class="request-section-head">
        <div>
            <span class="eyebrow">CARGA MASIVA P4</span>
            <h2>Importar listado nominal por Excel</h2>
            <p>Útil para requisiciones como Colón: una fila por funcionario,
                con posición, nombre y talla. Se valida la posición en RRHH2029,
                aunque corresponda a otra zona o dirección. Tras importar,
                verá la fecha de última entrega en el detalle de la solicitud;
                el P4 decide si mantiene la asignación.</p>
            <a class="secondary-button"
               href="<?= e(sindesg_url('?module=requests&action=download-nominal-template')) ?>">
                Descargar plantilla Excel (.xlsx)
            </a>
        </div>
    </div>
    <form method="post" enctype="multipart/form-data"
          action="<?= e(sindesg_url('?module=requests&action=import-nominal-file')) ?>">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="request_id" value="<?= (int) $serviceRequest['id'] ?>">
        <?php
        $catalogPicker = [
            'id' => 'request-excel-product',
            'name' => 'product_id',
            'label' => 'Producto del catálogo para identificar las tallas',
            'placeholder' => 'Buscar botas, calzado, código del artículo...',
            'options' => $catalogProductOptions,
        ];
        require __DIR__ . '/catalog-picker.php';
        ?>
        <label>
            <span>Excel/CSV nominal (máximo 15 MB) *</span>
            <input type="file" name="nominal_workbook" accept=".xlsx,.csv" required>
        </label>
        <label class="request-cell-sub">
            <input type="checkbox" name="allow_partial" value="1">
            Autorizo cargar solo los funcionarios y tallas verificados; las filas
            con incidencias quedarán sin registrar, para su corrección posterior.
        </label>
        <div class="request-nominal-actions">
            <button type="submit" class="secondary-button"
                    formaction="<?= e(sindesg_url('?module=requests&action=preview-nominal-file')) ?>">
                Prevalidar y ver incidencias sin guardar
            </button>
            <button type="submit" class="primary-button">
                Importar solo las filas verificadas al borrador
            </button>
        </div>
        <p class="request-cell-sub">
            La importación NO aprueba ni transfiere existencias. Si encuentra
            diferencias sin autorización para importación parcial, no guardará ninguna fila.
            El Central revisará la solicitud antes de reservar stock real.
        </p>
    </form>
</section>

<?php if (!empty($nominalPreview) && is_array($nominalPreview)): ?>
<section class="request-panel" id="nominal-prevalidation">
    <div class="request-section-head">
        <div>
            <span class="eyebrow">CONCILIACIÓN SOLO LECTURA · RRHH2029</span>
            <h2>Resultado del listado nominal</h2>
            <p>
                Requisición <?= e($nominalPreview['requisition']) ?> ·
                <?= e($nominalPreview['filename']) ?>.
                No se guardaron funcionarios ni movimientos de inventario.
            </p>
        </div>
    </div>
    <div class="request-verification-summary">
        <span><strong><?= (int) $nominalPreview['total'] ?></strong><small>Filas nominales</small></span>
        <span><strong><?= (int) $nominalPreview['valid_count'] ?></strong><small>Verificables únicos</small></span>
        <span><strong><?= (int) ($nominalPreview['duplicate_count'] ?? 0) ?></strong><small>Repetidos que se omitirán</small></span>
        <span><strong><?= (int) $nominalPreview['blocked_count'] ?></strong><small>Pendientes de revisión</small></span>
    </div>
    <p class="request-cell-sub">
        La posición debe coincidir con un funcionario identificable en RRHH.
        Los nombres se comparan completos, aunque los campos de nombre y apellido
        estén distribuidos de manera distinta. Las copias verificadas del mismo
        funcionario y talla se muestran como repetidas y no generan otra dotación.
        Si la posición está duplicada con tallas o nombres contradictorios, se
        bloquean todas las filas afectadas. Licencias, estados no elegibles y
        posiciones anteriores siguen requiriendo revisión; nunca se asignan
        automáticamente por parecido del nombre.
    </p>
    <?php if ($nominalPreview['issues'] === []): ?>
        <p>Todos los registros pasaron la validación nominal preliminar.</p>
    <?php else: ?>
        <div class="responsive-table request-nominal-review-table">
            <table>
                <thead>
                <tr><th>Fila</th><th>Posición Excel</th><th>Nombre Excel</th>
                    <th>Nombre encontrado RRHH</th><th>Origen RRHH</th>
                    <th>Talla</th><th>Resultado</th></tr>
                </thead>
                <tbody>
                <?php foreach ($nominalPreview['issues'] as $issue): ?>
                    <tr>
                        <td><?= (int) $issue['excel_row'] ?: 'Documento' ?></td>
                        <td><?= e($issue['position'] ?: '—') ?></td>
                        <td><?= e($issue['excel_name'] ?: '—') ?></td>
                        <td><?= e($issue['rrhh_name'] ?: 'No determinado') ?></td>
                        <td>
                            <?php
                            $sourceLabel = match ((string) ($issue['position_source'] ?? '')) {
                                'current' => 'Posición del expediente',
                                'assignment' => 'Asignación vigente',
                                'assignment_conflict' => 'Asignación distinta al expediente',
                                'historical' => 'Posición anterior',
                                default => 'No determinada',
                            };
                            ?>
                            <?= e($sourceLabel) ?>
                            <?php if (!empty($issue['rrhh_status'])): ?>
                                <small class="request-cell-sub"><?= e($issue['rrhh_status']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= e($issue['size'] ?: '—') ?></td>
                        <td><?= e($issue['message']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <p class="request-cell-sub">
        Corrija el Excel según la posición actual confirmada en RRHH y vuelva
        a prevalidar. Para importar parcialmente, vuelva a seleccionar el archivo,
        marque la autorización correspondiente y use el botón de importación.
        No cambie una posición basándose únicamente en una coincidencia de nombre.
    </p>
</section>
<?php endif; ?>
<?php endif; ?>

<?php if ($serviceRequest['status'] === 'in_review' && $requestItems !== []): ?>
<section class="request-panel review-panel">
    <div class="request-section-head">
        <div>
            <span class="eyebrow">DECISIÓN</span>
            <h2>Registrar aprobación</h2>
            <p>Puede aprobar cero, parte o todo. El sistema valida el stock comprometible por variante.</p>
        </div>
    </div>

    <form method="post" action="<?= e(sindesg_url('?module=requests&action=decide')) ?>" class="request-review-form">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="request_id" value="<?= e($serviceRequest['id']) ?>">

        <div class="request-review-lines">
            <?php foreach ($requestItems as $item): ?>
                <label>
                    <span><?= e($item['product_name'] . ' · ' . $item['variant_code']) ?></span>
                    <small>Solicitado <?= e(number_format((float) $item['quantity_requested'], 3, '.', ',')) ?> · Comprometible <?= e(number_format((float) $item['available_to_approve'], 3, '.', ',')) ?></small>
                    <input type="number"
                           name="approved_quantity[<?= e($item['id']) ?>]"
                           min="0"
                           max="<?= e($item['quantity_requested']) ?>"
                           step="0.001"
                           value="<?= e($item['quantity_requested']) ?>"
                           required>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="request-employee-picker" data-reviewer-picker data-search-url="<?= e($employeeSearchUrl) ?>">
            <label>
                <span>Funcionario revisor *</span>
                <input type="search" data-reviewer-search autocomplete="off" placeholder="Nombre, cédula o posición..." required>
                <input type="hidden" name="reviewed_by_employee_id" data-reviewer-id>
            </label>
            <div class="request-picker-results" data-reviewer-results hidden></div>
            <div class="request-picker-selection" data-reviewer-selection hidden></div>
        </div>

        <label class="request-notes-field">
            <span>Observación de revisión</span>
            <textarea name="review_notes" rows="3" placeholder="Motivo de ajuste, rechazo o aprobación parcial."></textarea>
        </label>

        <button class="primary-button request-full-button" type="submit">Registrar decisión</button>
    </form>
</section>
<?php endif; ?>

<div class="request-bottom-actions">
    <a class="secondary-button" href="<?= e(sindesg_url('?module=requests')) ?>">← Solicitudes</a>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=warehouses&action=show&id=' . (int) $serviceRequest['p4_warehouse_id'])) ?>">Ver P4</a>
</div>
