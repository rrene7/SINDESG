<?php
declare(strict_types=1);
$pageData = (array) ($nominalQueuePage ?? []);
$nominalQueue = (array) ($pageData['rows'] ?? []);
$totalPending = (int) ($pageData['total'] ?? 0);
$pageNumber = (int) ($pageData['page'] ?? 1);
$pages = (int) ($pageData['pages'] ?? 1);
$perPage = (int) ($pageData['per_page'] ?? 25);
$filters = (array) ($nominalFilters ?? []);
$filterSearch = (string) ($filters['search'] ?? '');
$filterSize = (string) ($filters['size'] ?? '');
$filterRequest = (string) ($filters['request'] ?? '');
$sizeOptions = [];
$requestOptions = [];
foreach (($nominalQueueOptions ?? []) as $line) {
    $size = trim((string) ($line['size_name'] ?? ''));
    $request = trim((string) ($line['request_code'] ?? ''));
    if ($size !== '') $sizeOptions[$size] = true;
    if ($request !== '' && !isset($requestOptions[$request])) {
        $date = trim((string) ($line['requested_at'] ?? ''));
        $dateLabel = $date !== '' ? substr($date, 0, 10) : '';
        $requestOptions[$request] = 'Dotación de ' . (string) ($line['product_name'] ?? 'artículos')
            . ' · ' . (string) ($line['p4_name'] ?: 'P4')
            . ($dateLabel !== '' ? ' · ' . $dateLabel : '');
    }
}
uksort($sizeOptions, 'strnatcasecmp');
ksort($requestOptions, SORT_NATURAL);
$firstRow = $totalPending > 0 ? ($pageNumber - 1) * $perPage + 1 : 0;
$lastRow = min($pageNumber * $perPage, $totalPending);
$pageUrl = static function (int $page) use ($filterSearch, $filterSize, $filterRequest, $perPage): string {
    return sindesg_url('?' . http_build_query([
        'module'=>'deliveries', 'q'=>$filterSearch, 'size'=>$filterSize,
        'request'=>$filterRequest, 'per_page'=>$perPage, 'page'=>$page,
    ], '', '&', PHP_QUERY_RFC3986));
};
?>
<section class="p4-delivery-workspace" data-p4-delivery-list>
    <header class="p4-delivery-header">
        <div>
            <span class="eyebrow">P4 · ENTREGA NOMINAL</span>
            <h1>Entregas pendientes</h1>
            <p>Funcionarios de tus almacenes autorizados, con prendas recibidas pendientes de entregar.</p>
        </div>
        <div class="p4-delivery-header-actions">
            <div class="p4-delivery-count"><strong><?= e($totalPending) ?></strong><span>renglones pendientes que coinciden</span></div>
            <a class="secondary-button" href="<?= e(sindesg_url('?module=deliveries&action=nominal-report')) ?>">Reporte nominal →</a>
            <a class="secondary-button" href="<?= e(sindesg_url('?module=dashboard')) ?>">← Mi panel P4</a>
        </div>
    </header>

    <form class="p4-delivery-toolbar" method="get" action="<?= e(sindesg_url('')) ?>" role="search" aria-label="Filtrar todas las entregas pendientes">
        <input type="hidden" name="module" value="deliveries">
        <label class="p4-delivery-search">
            <span>Buscar funcionario, cédula o artículo</span>
            <input type="search" data-p4-filter-search name="q" value="<?= e($filterSearch) ?>" placeholder="Nombre, cédula o artículo…" maxlength="120" autocomplete="off">
        </label>
        <label>
            <span>Talla</span>
            <select data-p4-filter-size name="size">
                <option value="">Todas las tallas</option>
                <?php foreach (array_keys($sizeOptions) as $size): ?>
                    <option value="<?= e($size) ?>" <?= $size === $filterSize ? 'selected' : '' ?>><?= e($size) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Solicitud</span>
            <select data-p4-filter-request name="request">
                <option value="">Todas las solicitudes</option>
                <?php foreach ($requestOptions as $request => $label): ?>
                    <option value="<?= e($request) ?>" title="<?= e($request) ?>" <?= $request === $filterRequest ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="p4-delivery-page-size"><span>Por página</span>
            <select name="per_page">
                <?php foreach ([10, 25, 50] as $pageSize): ?>
                    <option value="<?= $pageSize ?>" <?= $perPage === $pageSize ? 'selected' : '' ?>><?= $pageSize ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="p4-delivery-apply">Aplicar filtros</button>
        <a class="p4-delivery-reset" data-p4-filter-reset href="<?= e(sindesg_url('?module=deliveries')) ?>">Limpiar filtros</a>
    </form>

    <div class="p4-delivery-results-meta" aria-live="polite">
        <span>Mostrando <strong data-p4-visible-count><?= e($firstRow) ?>–<?= e($lastRow) ?></strong> de <?= e($totalPending) ?> coincidencias reales</span>
        <span>Filtrados en el servidor · solo tus P4 autorizados</span>
    </div>

    <?php if (empty($nominalQueue)): ?>
        <div class="p4-delivery-empty"><strong>Sin entregas pendientes</strong><p><?= $filterSearch !== '' || $filterSize !== '' || $filterRequest !== '' ? 'Ninguna coincidencia para estos filtros.' : 'No hay renglones nominales recibidos pendientes en tus almacenes P4.' ?></p></div>
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
                        <small><?= e($line['beneficiary_document_snapshot'] ?: 'Sin documento') ?></small>
                    </div>
                    <div class="p4-delivery-product">
                        <span><?= e($line['product_name']) ?></span>
                        <small title="<?= e($line['request_code'] . ' · ' . $line['variant_code']) ?>"><?= e('Dotación · ' . ($line['p4_name'] ?: 'P4')) ?></small>
                    </div>
                    <div class="p4-delivery-size">
                        <span class="p4-delivery-cell-label">Talla</span>
                        <strong><?= e($line['size_name'] ?: '—') ?></strong>
                    </div>
                    <div class="p4-delivery-quantity">
                        <span class="p4-delivery-cell-label">Pendiente</span>
                        <strong><?= e(sindesg_units((float) $line['pending_quantity'])) ?></strong>
                    </div>
                    <?php
                    $openDraftCount = (int) ($line['open_draft_count'] ?? 0);
                    $safeDraftId = (int) ($line['draft_delivery_id'] ?? 0);
                    $hasBlockingDraft = (int) ($line['blocking_draft_exists'] ?? 0) > 0;
                    ?>
                    <?php if ($openDraftCount > 0 || $hasBlockingDraft): ?>
                        <div class="p4-draft-reuse">
                            <span class="p4-draft-warning">
                                <?php if ($safeDraftId <= 0): ?>
                                    Existe un borrador relacionado que requiere revisión. No se puede iniciar otro.
                                <?php elseif ($openDraftCount === 1): ?>
                                    Ya existe un borrador para esta entrega
                                <?php else: ?>
                                    <?= e($openDraftCount) ?> borradores abiertos: revisar posibles duplicados
                                <?php endif; ?>
                            </span>
                            <?php if ($safeDraftId > 0): ?>
                                <a class="p4-delivery-action p4-resume-draft"
                                   href="<?= e(sindesg_url('?module=deliveries&action=show&id=' . $safeDraftId)) ?>">
                                    Continuar borrador <span aria-hidden="true">→</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <a class="p4-delivery-action" href="<?= e(sindesg_url('?module=deliveries&action=new&request_item_id=' . (int) $line['request_item_id'])) ?>">Preparar entrega <span aria-hidden="true">→</span></a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="p4-delivery-empty" data-p4-empty-filter hidden>No hay destinatarios que coincidan con los filtros.</div>
    <?php endif; ?>
    <?php if ($pages > 1): ?>
        <nav class="p4-delivery-pagination" aria-label="Páginas de entregas pendientes">
            <?php if ($pageNumber > 1): ?>
                <a class="secondary-button" href="<?= e($pageUrl($pageNumber - 1)) ?>">← Anterior</a>
            <?php else: ?><span class="p4-page-disabled">← Anterior</span><?php endif; ?>
            <strong>Página <?= e($pageNumber) ?> de <?= e($pages) ?></strong>
            <?php if ($pageNumber < $pages): ?>
                <a class="secondary-button" href="<?= e($pageUrl($pageNumber + 1)) ?>">Siguiente →</a>
            <?php else: ?><span class="p4-page-disabled">Siguiente →</span><?php endif; ?>
        </nav>
    <?php endif; ?>
    <p class="p4-delivery-note">Los filtros incluyen todos los pendientes autorizados, no solo los primeros 100. Las cantidades y existencias se verifican nuevamente en el servidor al confirmar.</p>
</section>
