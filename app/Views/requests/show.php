<?php
declare(strict_types=1);

$statusLabels = [
    'draft' => 'Borrador',
    'submitted' => 'Enviada',
    'in_review' => 'En revisión',
    'approved' => 'Aprobada',
    'partially_approved' => 'Aprobada parcial',
    'rejected' => 'Rechazada',
    'fulfilled' => 'Atendida por Central',
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
    $stock = sindesg_units((float) $entry['central_stock']);
    $label = $entry['category_name'] . ' · ' . $entry['product_name']
        . ' · Talla ' . ($entry['size_name'] ?: '—')
        
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
        <span class="eyebrow">SOLICITUD DE DOTACIÓN</span>
        <h1><?= e($serviceRequest['p4_name_snapshot'] ?: $serviceRequest['p4_internal_name']) ?></h1>
        <p><?= e($serviceRequest['requested_by_label_snapshot']) ?> · <?= e(date('d/m/Y H:i', strtotime((string) $serviceRequest['requested_at']))) ?></p>
    </div>
    <div class="request-detail-status">
        <span class="request-status <?= e($serviceRequest['status']) ?>"><?= e($statusLabels[$serviceRequest['status']] ?? $serviceRequest['status']) ?></span>
        <strong><?= e(sindesg_units($totalRequested)) ?></strong>
        <small>unidades solicitadas</small>
    </div>
</section>

<?php if ($serviceRequest['status'] === 'draft' && $requestItems !== [] && sindesg_role_action_allowed('requests', 'submit', 'POST', (array) sindesg_user())): ?>
<section class="request-panel request-draft-next-step" role="status">
    <div class="request-section-head">
        <div>
            <span class="eyebrow">NÓMINA YA REGISTRADA · SIGUIENTE PASO</span>
            <h2><?= count($requestItems) ?> renglones cargados en el borrador</h2>
            <p>
                Las asignaciones ya están guardadas. No vuelva a importar el Excel.
                Revise el detalle y, si es correcto, envíe esta solicitud al
                Almacén Central para que la evalúe. Todavía no hay entrega ni
                movimiento de existencias.
            </p>
        </div>
        <a class="primary-button" href="#request-send-panel">
            Revisar y enviar al Central →
        </a>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($requestTracking)): ?>
    <?php require __DIR__ . '/tracking.php'; ?>
<?php endif; ?>

<section class="request-detail-grid">
    <article class="request-panel">
        <span class="eyebrow">CABECERA</span>
        <h2>Datos de la solicitud</h2>
        <dl class="request-dl">
            <div><dt>Referencia para auditoría</dt><dd><details><summary>Ver folio interno</summary><?= e($serviceRequest['request_code']) ?></details></dd></div>
            <div><dt>Unidad solicitante</dt><dd><?= e($serviceRequest['p4_name_snapshot'] ?: $serviceRequest['p4_internal_name']) ?></dd></div>
            <div><dt>Unidad P4</dt><dd><?= e($serviceRequest['p4_name_snapshot'] ?: '—') ?></dd></div>
            <div><dt>Solicitante</dt><dd><?= e($serviceRequest['requested_by_label_snapshot']) ?></dd></div>
            <div><dt>Cédula</dt><dd><?= e($serviceRequest['requested_by_document_snapshot'] ?: '—') ?></dd></div>
            <div><dt>Posición</dt><dd><?= e($serviceRequest['requested_by_position_snapshot'] ?: '—') ?></dd></div>
        </dl>
        <?php if (!empty($serviceRequest['notes'])): ?>
            <div class="request-note"><strong>Observaciones</strong><p><?= e($serviceRequest['notes']) ?></p></div>
        <?php endif; ?>
    </article>

    <aside class="request-panel request-workflow" id="request-send-panel">
        <?php if (sindesg_role_action_allowed('requests', 'central-review', 'GET', (array) sindesg_user())): ?>
        <a class="secondary-button request-central-review-link"
           href="<?= e(sindesg_url('?module=requests&action=central-review&id=' . (int) $serviceRequest['id'])) ?>">
            Ver revisión del Almacén Central por talla →
        </a>
        <?php endif; ?>
        <span class="eyebrow">FLUJO</span>
        <h2><?= e($statusLabels[$serviceRequest['status']] ?? $serviceRequest['status']) ?></h2>

        <?php if ($serviceRequest['status'] === 'draft' && sindesg_role_action_allowed('requests', 'submit', 'POST', (array) sindesg_user())): ?>
            <p>Agregue todos los artículos requeridos y luego envíe la solicitud al Almacén Central.</p>
            <form method="post" action="<?= e(sindesg_url('?module=requests&action=submit')) ?>">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="request_id" value="<?= e($serviceRequest['id']) ?>">
                <button class="primary-button request-full-button" type="submit" <?= $requestItems === [] ? 'disabled' : '' ?>>Enviar solicitud</button>
            </form>
        <?php elseif ($serviceRequest['status'] === 'draft'): ?>
            <p>Solicitud en preparación por su P4 de origen. Almacén Central podrá revisarla cuando haya sido enviada.</p>
        <?php elseif ($serviceRequest['status'] === 'submitted' && sindesg_role_action_allowed('requests', 'central-review', 'GET', (array) sindesg_user())): ?>
            <p>La solicitud fue enviada y está pendiente de revisión en Servicios Generales.</p>
            <p class="request-cell-sub">Antes de aprobar, consulte el consolidado por talla del Almacén Central.</p>
            <form method="post" action="<?= e(sindesg_url('?module=requests&action=start-review')) ?>">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="request_id" value="<?= e($serviceRequest['id']) ?>">
                <button class="primary-button request-full-button" type="submit">Iniciar revisión</button>
            </form>
        <?php elseif ($serviceRequest['status'] === 'in_review' && sindesg_role_action_allowed('requests', 'central-review', 'GET', (array) sindesg_user())): ?>
            <p>Revise las cantidades por talla en la pantalla del Central. La aprobación compromete disponibilidad, pero todavía no mueve inventario.</p>
            <a class="primary-button request-full-button" href="<?= e(sindesg_url('?module=requests&action=central-review&id=' . (int) $serviceRequest['id'])) ?>">Revisar y aprobar por talla →</a>
        <?php else: ?>
            <div class="request-decision-summary">
                <span><strong><?= e(sindesg_units($totalApproved)) ?></strong><small>unidades aprobadas</small></span>
                <span><strong><?= e($serviceRequest['reviewed_by_label_snapshot'] ?: '—') ?></strong><small>revisor</small></span>
            </div>
            <?php if (!empty($serviceRequest['review_notes'])): ?>
                <div class="request-note"><strong>Observación de revisión</strong><p><?= e($serviceRequest['review_notes']) ?></p></div>
            <?php endif; ?>

            <?php if (in_array($serviceRequest['status'], ['approved', 'partially_approved'], true) && $remainingToDistribute > 0 && sindesg_role_action_allowed('distribution', 'new', 'GET', (array) sindesg_user())): ?>
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

    <?php if (!empty($nominalPreviewSuccess)): ?>
        <p class="request-cell-sub" role="status"><strong><?= e($nominalPreviewSuccess) ?></strong></p>
    <?php endif; ?>
    <div class="request-verification-summary">
        <span><strong><?= e(sindesg_units((float) ($requestCommitmentEvidence['approved'] ?? 0))) ?></strong><small>Aprobado</small></span>
        <span><strong><?= e(sindesg_units((float) ($requestCommitmentEvidence['committed'] ?? 0))) ?></strong><small>Comprometido</small></span>
        <span><strong><?= e(sindesg_units((float) ($requestCommitmentEvidence['dispatched'] ?? 0))) ?></strong><small>Despachado</small></span>
        <span><strong><?= e(sindesg_units((float) ($requestCommitmentEvidence['received'] ?? 0))) ?></strong><small>Recibido P4</small></span>
    </div>

    <p class="request-verification-copy">
        Aprobar una solicitud no mueve inventario. La cantidad aprobada queda comprometida hasta que una distribución genera los movimientos reales Central → Tránsito → P4.
    </p>
</section>
<?php endif; ?>

<?php if ($serviceRequest['status'] === 'draft' && sindesg_role_action_allowed('requests', 'submit', 'POST', (array) sindesg_user())): ?>
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

<section class="request-panel" data-request-nominal-list>
    <div class="request-section-head">
        <div><span class="eyebrow">DETALLE</span><h2>Necesidades registradas</h2></div>
        <span class="request-total"><?= e(count($requestItems)) ?> renglones</span>
    </div>

    <?php if ($requestItems === []): ?>
        <div class="request-empty compact"><p>El borrador todavía no contiene artículos.</p></div>
    <?php else: ?>
        <div class="request-nominal-tools" data-request-nominal-controls hidden>
            <label>
                <span>Buscar funcionario, artículo o talla</span>
                <input type="search" data-request-nominal-search placeholder="Escriba nombre, artículo o talla…" autocomplete="off">
            </label>
            <label>
                <span>Situación de entrega</span>
                <select data-request-nominal-filter>
                    <option value="all">Todas las situaciones</option>
                    <option value="ready">Disponible para entregar</option>
                    <option value="waiting">Pendiente de recepción</option>
                    <option value="delivered">Entregado</option>
                    <option value="review">Pendiente de aprobación</option>
                    <option value="consolidated">Cantidad consolidada</option>
                </select>
            </label>
            <label>
                <span>Renglones por página</span>
                <select data-request-nominal-page-size>
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                </select>
            </label>
            <p data-request-nominal-count aria-live="polite"></p>
        </div>
        <div class="responsive-table">
            <table>
                <thead>
                <tr><th>Artículo</th><th>Variante</th><th>Talla</th><th>Beneficiario</th><th>Solicitado</th><th>Stock central</th><th>Comprometible</th><th>Aprobado</th><th>Despachado</th><th>Recibido P4</th><th>Entregado funcionario</th><th>Situación</th><?php if ($serviceRequest['status'] === 'draft' && sindesg_role_action_allowed('requests', 'submit', 'POST', (array) sindesg_user())): ?><th></th><?php endif; ?></tr>
                </thead>
                <tbody>
                <?php foreach ($requestItems as $item): ?>
                    <?php $nominalState = \App\Services\P4NominalLineState::classify($item); ?>
                    <tr data-request-nominal-row data-request-line-state="<?= e($nominalState['key']) ?>">
                        <td><strong><?= e($item['product_name']) ?></strong><small class="request-cell-sub"><?= e($item['category_name']) ?></small></td>
                        <td><?= e(($item['size_name'] ?? '') ? 'Talla ' . $item['size_name'] : 'Presentación estándar') ?></td>
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
                        <td class="request-qty"><?= e(sindesg_units((float) $item['quantity_requested'])) ?></td>
                        <td><?= e(sindesg_units((float) $item['central_stock'])) ?></td>
                        <td><?= e(sindesg_units((float) $item['available_to_approve'])) ?></td>
                        <td class="request-qty approved"><?= e(sindesg_units((float) $item['quantity_approved'])) ?></td>
                        <td><?= e(sindesg_units((float) ($item['quantity_dispatched'] ?? 0))) ?></td>
                        <td><?= e(sindesg_units((float) ($item['quantity_received'] ?? 0))) ?></td>
                        <td>
                            <?= e(sindesg_units((float) ($item['quantity_delivered'] ?? 0))) ?>
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
                                    Entregar <?= e(sindesg_units($pendingIndividual)) ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td><span class="request-nominal-state state-<?= e($nominalState['key']) ?>"><?= e($nominalState['label']) ?></span></td>
                        <?php if ($serviceRequest['status'] === 'draft' && sindesg_role_action_allowed('requests', 'submit', 'POST', (array) sindesg_user())): ?>
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
        <p class="request-nominal-empty" data-request-nominal-empty hidden>No hay renglones que coincidan con los filtros seleccionados.</p>
        <nav class="request-nominal-pagination" data-request-nominal-pagination aria-label="Páginas de renglones nominales" hidden>
            <button type="button" class="secondary-button" data-request-nominal-prev>← Anterior</button>
            <span data-request-nominal-page aria-live="polite"></span>
            <button type="button" class="secondary-button" data-request-nominal-next>Siguiente →</button>
        </nav>
        <p class="request-nominal-help">La situación se calcula a partir de lo aprobado, recibido y entregado en esta solicitud. No representa el stock libre de todo el P4.</p>
    <?php endif; ?>
</section>

<?php if ($serviceRequest['status'] === 'draft' && sindesg_role_action_allowed('requests', 'submit', 'POST', (array) sindesg_user())): ?>
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
          data-bulk-nominal-picker data-search-url="<?= e(sindesg_url('public/api/employees.php?scope=p4')) ?>"
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
           zona o dirección, incluyendo personal prestado o en licencia.
           Solo se excluyen Vacaciones por jubilación e Inactivo.
           La fecha de última entrega es informativa: el P4 decide si corresponde solicitar.
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
          action="<?= e(sindesg_url('?module=requests&action=preview-nominal-file')) ?>">
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
        <?php if (sindesg_role_action_allowed('requests', 'import-nominal-file', 'POST', (array) sindesg_user())): ?>
        <label class="request-cell-sub">
            <input type="checkbox" name="allow_partial" value="1">
            Autorizo cargar solo los funcionarios y tallas verificados; las filas
            con incidencias quedarán sin registrar, para su corrección posterior.
        </label>
        <label class="request-cell-sub">
            <input type="checkbox" name="accept_document_gap" value="1">
            Si la requisición declara más unidades que funcionarios listados, autorizo
            incorporar <strong>todos los destinatarios verificados del Excel</strong>
            y dejar la diferencia registrada como <strong>pendiente documental sin
            beneficiario</strong>. No constituye inventario, despacho ni dotación.
            Esta opción NO permite saltarse filas con identidad sin validar.
        </label>
        <?php endif; ?>
        <div class="request-nominal-actions">
            <button type="submit" class="secondary-button">
                Prevalidar y ver incidencias sin guardar
            </button>
            <?php if (sindesg_role_action_allowed('requests', 'import-nominal-file', 'POST', (array) sindesg_user())): ?>
            <button type="submit" class="primary-button"
                    formaction="<?= e(sindesg_url('?module=requests&action=import-nominal-file')) ?>">
                Cargar todos los funcionarios verificados al borrador
            </button>
            <?php endif; ?>
        </div>
        <p class="request-cell-sub">
            La prevalidación solo consulta RRHH2029 y presenta incidencias.
            No incorpora funcionarios al borrador ni altera existencias.
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
                No se importaron funcionarios ni se movió inventario.
                Las validaciones manuales requieren confirmación expresa y quedan auditadas.
            </p>
        </div>
    </div>
    <?php if (!empty($nominalReviewFileReady) && sindesg_role_action_allowed('requests', 'import-from-nominal-preview', 'POST', (array) sindesg_user())): ?>
        <form method="post" class="p4-import-from-preview"
              action="<?= e(sindesg_url('?module=requests&action=import-from-nominal-preview')) ?>">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="request_id" value="<?= (int) $serviceRequest['id'] ?>">
            <input type="hidden" name="product_id" value="<?= (int) $nominalPreview['product_id'] ?>">
            <strong>Archivo ya cargado y conciliado.</strong>
            <p class="request-cell-sub">
                No tiene que seleccionar el Excel nuevamente. Después de validar
                las filas que correspondan, pulse «Cargar al sistema».
                Antes de guardar se volverán a comprobar los datos de RRHH.
            </p>
            <?php if ((int) ($nominalPreview['blocked_count'] ?? 0) > 0): ?>
                <label class="request-cell-sub">
                    <input type="checkbox" name="allow_partial" value="1">
                    Autorizo carga parcial: dejar sin asignar las
                    <?= (int) $nominalPreview['blocked_count'] ?> incidencias pendientes.
                </label>
            <?php endif; ?>
            <?php if (isset($nominalPreview['declared_quantity'])
                && (int) $nominalPreview['declared_quantity'] > (int) $nominalPreview['total']): ?>
                <label class="request-cell-sub">
                    <input type="checkbox" name="accept_document_gap" value="1"
                           <?= (int) ($nominalPreview['blocked_count'] ?? 0) === 0 ? 'required' : '' ?>>
                    Autorizo registrar la diferencia de
                    <?= (int) $nominalPreview['declared_quantity'] - (int) $nominalPreview['total'] ?>
                    unidad(es) declarada(s) sin destinatario como
                    pendiente documental, sin inventar funcionarios ni stock.
                </label>
            <?php endif; ?>
            <button class="primary-button" type="submit">
                Cargar al sistema <?= (int) ($nominalPreview['valid_count'] ?? 0) ?>
                funcionarios verificados
            </button>
            <small class="request-cell-sub">
                La carga incorpora las filas verificadas al borrador,
                no aprueba ni transfiere botas. La conciliación caduca tras una hora.
            </small>
        </form>
    <?php else: ?>
        <p class="request-cell-sub">
            La prevalidación no encontró un archivo temporal disponible para su
            incorporación. Vuelva a adjuntar y prevalidar el Excel si desea
            cargar los registros verificados al borrador.
        </p>
    <?php endif; ?>
    <div class="request-verification-summary">
        <span><strong><?= (int) $nominalPreview['total'] ?></strong><small>Filas nominales</small></span>
        <span><strong><?= (int) $nominalPreview['valid_count'] ?></strong><small>Verificables únicos</small></span>
        <span><strong><?= (int) ($nominalPreview['duplicate_count'] ?? 0) ?></strong><small>Repetidos que se omitirán</small></span>
        <span><strong><?= (int) $nominalPreview['blocked_count'] ?></strong><small>Pendientes de revisión</small></span>
    </div>
    <?php if (isset($nominalPreview['declared_quantity'])
        && (int) $nominalPreview['declared_quantity'] !== (int) $nominalPreview['total']): ?>
        <p class="request-cell-sub" role="alert">
            <strong>Atención: el documento declara
                <?= (int) $nominalPreview['declared_quantity'] ?> unidades,
                pero hay <?= (int) $nominalPreview['total'] ?> filas nominales.</strong>
            Puede cargar todas las filas verificadas marcando la autorización
            para dejar el sobrante declarado <strong>pendiente documental</strong>.
            No se crea un beneficiario ficticio ni una unidad adicional.
            Si hay identidades sin verificar, deberán resolverse o autorizar
            la importación parcial por separado.
        </p>
    <?php endif; ?>
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
        <?php
        $approvableCount = 0;
        foreach ($nominalPreview['issues'] as $reviewIssue) {
            if (\App\Services\NominalBulkRequestService::isManuallyValidatable($reviewIssue)) {
                $approvableCount++;
            }
        }
        ?>
        <?php if ($approvableCount > 0 && sindesg_role_action_allowed('requests', 'validate-nominal-selected', 'POST', (array) sindesg_user())): ?>
            <form id="p4-bulk-review-form" method="post"
                  action="<?= e(sindesg_url('?module=requests&action=validate-nominal-selected')) ?>"
                  class="p4-bulk-review-toolbar">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="request_id" value="<?= (int) $serviceRequest['id'] ?>">
                <label class="request-cell-sub">
                    <input type="checkbox" name="confirm_bulk" value="1" required>
                    Confirmo que revisé la identidad institucional de cada fila
                    seleccionada con evidencia independiente, especialmente cuando
                    el nombre del Excel y el de RRHH son personas aparentemente distintas.
                    No seleccionaré filas sin identidad comprobada.
                </label>
                <button type="button" class="secondary-button" data-p4-review-select-all>
                    Seleccionar todas las incidencias
                </button>
                <span data-p4-review-counter aria-live="polite">0 seleccionadas</span>
                <button type="submit" class="primary-button" data-p4-review-submit>
                    Validar seleccionados
                </button>
                <small class="request-cell-sub">
                    Una sola confirmación, sin escribir cédulas ni motivos repetidos.
                    Al pulsar «Validar seleccionados», el P4 acepta la responsabilidad
                    de las identidades marcadas. Revise cuidadosamente los nombres:
                    si Excel y RRHH representan personas diferentes, NO seleccione esa fila.
                    Todas las decisiones quedan auditadas individualmente.
                </small>
            </form>
        <?php endif; ?>
        <div class="responsive-table request-nominal-review-table">
            <table>
                <thead>
                <tr><th>Fila</th><th>Posición Excel</th><th>Nombre Excel</th>
                    <th>Nombre encontrado RRHH</th><th>Origen RRHH</th>
                    <th>Talla</th><th>Resultado</th><th>Acción</th></tr>
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
                        <td>
                            <?php if (
                                (int) ($issue['excel_row'] ?? 0) > 0
                                && \App\Services\NominalBulkRequestService::isManuallyValidatable($issue)
                                && sindesg_role_action_allowed('requests', 'validate-nominal-selected', 'POST', (array) sindesg_user())
                            ): ?>
                                <label class="p4-bulk-review-checkbox">
                                    <input type="checkbox" name="review_rows[]"
                                           form="p4-bulk-review-form" data-p4-review-row
                                           value="<?= (int) $issue['excel_row'] ?>">
                                    <span>Seleccionar</span>
                                </label>
                            <?php else: ?>
                                <small class="request-cell-sub">Requiere corregir Excel o RRHH</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php if (sindesg_role_action_allowed('requests', 'validate-nominal-selected', 'POST', (array) sindesg_user())): ?>
    <p class="request-cell-sub">
        Marque las diferencias de nombres que el P4 haya comprobado contra el
        expediente institucional y pulse «Validar seleccionados» una sola vez.
        SINDESG verifica nuevamente la cédula y la posición sin pedirle
        escribirlas, y guarda una evidencia independiente para cada fila.
        Si realmente son personas distintas, NO las valide: corrija el Excel.
        Una vez resueltas las incidencias, utilice el botón
        «Cargar al sistema» situado al inicio de este resultado: no necesita
        adjuntar otra vez el Excel mientras la sesión siga vigente.
        Si declara 723 unidades y contiene 722 destinatarios, podrá
        registrar la diferencia documental con autorización explícita.
        No se crean beneficiarios ni existencias adicionales.
        Si las posiciones corresponden a personas realmente distintas,
        corrija el origen del listado en lugar de forzar la validación.
        Cuando la nómina ya figure en el borrador, continúe con
        «Revisar y enviar al Central», sin repetir la importación.
    </p>
    <?php else: ?>
    <p class="request-cell-sub">
        Resultado de prevalidación sin cambios de datos. Revise las incidencias y
        corrija la plantilla en Excel. No se habilitan acciones de importación ni
        confirmación excepcional de identidades para este rol.
    </p>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php endif; ?>

<?php if ($serviceRequest['status'] === 'in_review' && $requestItems !== [] && sindesg_role_action_allowed('requests', 'central-review', 'GET', (array) sindesg_user())): ?>
<section class="request-panel review-panel">
    <p class="request-cell-sub">
        Para solicitudes grandes, recomendamos la
        <a href="<?= e(sindesg_url('?module=requests&action=central-review&id=' . (int) $serviceRequest['id'])) ?>">revisión agrupada por tallas</a>
        en lugar de aprobar a cada funcionario por separado.
    </p>
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
                    <span><?= e($item['product_name'] . (!empty($item['size_name']) ? ' · Talla ' . $item['size_name'] : '')) ?></span>
                    <small>Solicitado <?= e(sindesg_units((float) $item['quantity_requested'])) ?> · Comprometible <?= e(sindesg_units((float) $item['available_to_approve'])) ?></small>
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
