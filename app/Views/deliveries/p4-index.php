<?php
declare(strict_types=1);

$totalPending = count($nominalQueue ?? []);
$sizeOptions = [];
$requestOptions = [];
foreach (($nominalQueue ?? []) as $line) {
    $size = trim((string) ($line['size_name'] ?? ''));
    $request = trim((string) ($line['request_code'] ?? ''));
    if ($size !== '') $sizeOptions[$size] = true;
    if ($request !== '') $requestOptions[$request] = true;
}
uksort($sizeOptions, 'strnatcasecmp');
ksort($requestOptions, SORT_NATURAL);
?>
<section class="p4-delivery-workspace" data-p4-delivery-list>
    <header class="p4-delivery-header">
        <div>
            <span class="eyebrow">P4 · ENTREGA NOMINAL</span>
            <h1>Entregas pendientes</h1>
            <p>Funcionarios de tus almacenes autorizados, con prendas recibidas pendientes de entregar.</p>
        </div>
        <div class="p4-delivery-header-actions">
            <div class="p4-delivery-count"><strong><?= e($totalPending) ?></strong><span>renglones cargados</span></div>
            <a class="secondary-button" href="<?= e(sindesg_url('?module=dashboard')) ?>">← Mi panel P4</a>
        </div>
    </header>

    <div class="p4-delivery-toolbar" role="search" aria-label="Filtrar entregas pendientes">
        <label class="p4-delivery-search">
            <span>Buscar funcionario, cédula o artículo</span>
            <input type="search" data-p4-filter-search placeholder="Nombre, cédula o artículo…" autocomplete="off">
        </label>
        <label>
            <span>Talla</span>
            <select data-p4-filter-size>
                <option value="">Todas las tallas</option>
                <?php foreach (array_keys($sizeOptions) as $size): ?>
                    <option value="<?= e($size) ?>"><?= e($size) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Solicitud</span>
            <select data-p4-filter-request>
                <option value="">Todas las solicitudes</option>
                <?php foreach (array_keys($requestOptions) as $request): ?>
                    <option value="<?= e($request) ?>"><?= e($request) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="button" class="p4-delivery-reset" data-p4-filter-reset>Limpiar filtros</button>
    </div>

    <div class="p4-delivery-results-meta" aria-live="polite">
        <span><strong data-p4-visible-count><?= e($totalPending) ?></strong> de <?= e($totalPending) ?> renglones visibles</span>
        <span>Ordenados por registro · solo entregas recibidas pendientes</span>
    </div>

    <?php if (empty($nominalQueue)): ?>
        <div class="p4-delivery-empty"><strong>Sin entregas pendientes</strong><p>No hay renglones nominales recibidos pendientes en tus almacenes P4.</p></div>
    <?php else: ?>
        <div class="p4-delivery-list">
            <?php foreach ($nominalQueue as $line): ?>
                <article class="p4-delivery-row"
                    data-p4-delivery-row
                    data-size="<?= e((string) ($line['size_name'] ?? '')) ?>"
                    data-request="<?= e((string) ($line['request_code'] ?? '')) ?>"
                    data-search="<?= e(strtolower(implode(' ', [
                        (string) ($line['beneficiary_label_snapshot'] ?? ''),
                        (string) ($line['beneficiary_document_snapshot'] ?? ''),
                        (string) ($line['product_name'] ?? ''),
                        (string) ($line['variant_code'] ?? ''),
                        (string) ($line['request_code'] ?? ''),
                    ]))) ?>">
                    <div class="p4-delivery-person">
                        <strong><?= e($line['beneficiary_label_snapshot'] ?: 'Funcionario') ?></strong>
                        <small><?= e($line['beneficiary_document_snapshot'] ?: 'Sin documento') ?> · <?= e($line['request_code']) ?></small>
                    </div>
                    <div class="p4-delivery-product">
                        <span><?= e($line['product_name']) ?></span>
                        <small><?= e($line['variant_code']) ?></small>
                    </div>
                    <div class="p4-delivery-size">
                        <span class="p4-delivery-cell-label">Talla</span>
                        <strong><?= e($line['size_name'] ?: '—') ?></strong>
                    </div>
                    <div class="p4-delivery-quantity">
                        <span class="p4-delivery-cell-label">Pendiente</span>
                        <strong><?= e(number_format((float) $line['pending_quantity'], 3, '.', ',')) ?></strong>
                    </div>
                    <a class="p4-delivery-action" href="<?= e(sindesg_url('?module=deliveries&action=new&request_item_id=' . (int) $line['request_item_id'])) ?>">Preparar entrega <span aria-hidden="true">→</span></a>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="p4-delivery-empty" data-p4-empty-filter hidden>No hay destinatarios que coincidan con los filtros.</div>
    <?php endif; ?>
    <p class="p4-delivery-note">Hasta 100 renglones pendientes por consulta. Los filtros actúan sobre los resultados cargados; las cantidades y existencias se comprueban nuevamente en el servidor al confirmar.</p>
</section>
<script>
(() => {
    const root = document.querySelector('[data-p4-delivery-list]');
    if (!root) return;
    const rows = Array.from(root.querySelectorAll('[data-p4-delivery-row]'));
    const search = root.querySelector('[data-p4-filter-search]');
    const size = root.querySelector('[data-p4-filter-size]');
    const request = root.querySelector('[data-p4-filter-request]');
    const counter = root.querySelector('[data-p4-visible-count]');
    const empty = root.querySelector('[data-p4-empty-filter]');
    const normalize = value => (value || '').toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    const apply = () => {
        const term = normalize(search.value);
        let visible = 0;
        for (const row of rows) {
            const matches = (!term || normalize(row.dataset.search).includes(term))
                && (!size.value || row.dataset.size === size.value)
                && (!request.value || row.dataset.request === request.value);
            row.hidden = !matches;
            if (matches) visible++;
        }
        counter.textContent = String(visible);
        if (empty) empty.hidden = visible !== 0;
    };
    search.addEventListener('input', apply);
    size.addEventListener('change', apply);
    request.addEventListener('change', apply);
    root.querySelector('[data-p4-filter-reset]').addEventListener('click', () => {
        search.value = '';
        size.value = '';
        request.value = '';
        apply();
        search.focus();
    });
})();
</script>
