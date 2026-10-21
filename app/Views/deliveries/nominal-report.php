<?php
declare(strict_types=1);

$page=(array)($nominalReportPage??[]);
$rows=(array)($page['rows']??[]);
$f=(array)($nominalReportFilters??[]);
$options=(array)($nominalReportOptions??[]);
$total=(int)($page['total']??0);
$number=(int)($page['page']??1);
$pages=(int)($page['pages']??1);
$perPage=(int)($page['per_page']??25);
$first=$total ? ($number-1)*$perPage+1 : 0;
$last=min($number*$perPage,$total);
$situations=[
    ''=>'Todas las situaciones',
    'review'=>'Pendiente de aprobación',
    'rejected'=>'Solicitud rechazada',
    'waiting_receipt'=>'Pendiente de recepción',
    'ready'=>'Recibido, disponible para entregar',
    'partial'=>'Entrega individual parcial',
    'delivered'=>'Entrega individual completada',
    'anomaly'=>'Cantidades por revisar',
    'duplicate'=>'Posibles renglones duplicados',
];
$url=static function(int $page) use($f):string {
    return sindesg_url('?'.http_build_query(
        array_merge($f,['module'=>'deliveries','action'=>'nominal-report','page'=>$page]),
        '','&',PHP_QUERY_RFC3986
    ));
};
$units=static fn($n):string => sindesg_units((float)$n);
?>

<div class="nomrep" data-nominal-report>
    <header class="nomrep-hero">
        <div>
            <span class="eyebrow">SINDESG · CONSULTA INSTITUCIONAL</span>
            <h1>Reporte nominal de dotaciones</h1>
            <p>Seguimiento por funcionario desde la solicitud hasta la entrega individual, separado de la recepción física en P4.</p>
        </div>
        <div class="nomrep-hero-actions">
            <a href="<?= e(sindesg_url('?module=deliveries')) ?>">← Entregas</a>
            <button type="button" onclick="window.print()">Imprimir página</button>
        </div>
    </header>

    <section class="nomrep-panel">
        <h2>Filtros de consulta</h2>
        <p class="nomrep-hint"><?= !empty($nominalReportInstitutional)
            ? 'Consulta institucional, limitada a solicitudes nominales P4 registradas en SINDESG.'
            : 'Solo aparecen registros de los almacenes P4 activos asignados a tu usuario.' ?></p>
        <form class="nomrep-filters" method="get" action="<?= e(sindesg_url()) ?>" role="search">
            <input type="hidden" name="module" value="deliveries">
            <input type="hidden" name="action" value="nominal-report">
            <label class="nomrep-filter-search">Funcionario, cédula o artículo
                <input type="search" name="q" value="<?= e($f['q']??'') ?>" maxlength="120" placeholder="Buscar en todos los renglones">
            </label>
            <label>Unidad del funcionario
                <input type="search" name="unit" value="<?= e($f['unit']??'') ?>" maxlength="120" placeholder="Unidad registrada en la solicitud">
            </label>
            <label>Solicitud
                <input type="search" name="request" value="<?= e($f['request']??'') ?>" maxlength="80" placeholder="Código exacto">
            </label>
            <label>Almacén P4
                <select name="warehouse_id">
                    <option value="0">Todos los autorizados</option>
                    <?php foreach (($options['warehouses']??[]) as $v): ?>
                        <option value="<?= (int)$v['id'] ?>" <?= (int)($f['warehouse_id']??0)===(int)$v['id']?'selected':'' ?>><?= e($v['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Artículo
                <select name="product_id">
                    <option value="0">Todos los artículos</option>
                    <?php foreach (($options['products']??[]) as $v): ?>
                        <option value="<?= (int)$v['id'] ?>" <?= (int)($f['product_id']??0)===(int)$v['id']?'selected':'' ?>><?= e($v['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Talla
                <select name="size_id">
                    <option value="0">Todas las tallas</option>
                    <?php foreach (($options['sizes']??[]) as $v): ?>
                        <option value="<?= (int)$v['id'] ?>" <?= (int)($f['size_id']??0)===(int)$v['id']?'selected':'' ?>><?= e($v['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Situación
                <select name="situation">
                    <?php foreach ($situations as $key=>$label): ?>
                        <option value="<?= e($key) ?>" <?= ($f['situation']??'')===$key?'selected':'' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Renglones por página
                <select name="per_page">
                    <?php foreach ([10,25,50] as $size): ?>
                        <option value="<?= $size ?>" <?= $perPage===$size?'selected':'' ?>><?= $size ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="nomrep-filter-actions">
                <button type="submit" class="nomrep-submit">Aplicar filtros</button>
                <a class="nomrep-reset" href="<?= e(sindesg_url('?module=deliveries&action=nominal-report')) ?>">Limpiar</a>
            </div>
        </form>
    </section>

    <div class="nomrep-kpis">
        <div class="nomrep-kpi"><span>Renglones coincidentes</span><strong><?= e(sindesg_units($total)) ?></strong></div>
        <div class="nomrep-kpi"><span>Página actual</span><strong><?= e($number) ?> / <?= e($pages) ?></strong></div>
        <div class="nomrep-kpi"><span>Mostrando</span><strong><?= e($first) ?>–<?= e($last) ?></strong></div>
    </div>

    <section class="nomrep-panel">
        <h2>Detalle nominal y trazabilidad</h2>
        <p class="nomrep-caption">Cantidades de cada renglón: solicitado → aprobado → despachado → recibido en P4 → entregado al funcionario. Un registro recibido en P4 no acredita entrega individual. Las alertas son informativas y no modifican datos.</p>
        <?php if ($rows===[]): ?>
            <div class="nomrep-empty"><strong>Sin coincidencias autorizadas</strong><p>Pruebe otros filtros. No se muestran registros de almacenes fuera de su alcance.</p></div>
        <?php else: ?>
            <div class="nomrep-table-wrap">
                <table>
                    <thead><tr>
                        <th>Funcionario / unidad</th><th>Solicitud / P4</th><th>Artículo / talla</th>
                        <th>Solicitado</th><th>Aprobado</th><th>Despachado</th><th>Recibido P4</th>
                        <th>Entregado</th><th>Falta recibir</th><th>Listo para entregar</th>
                        <th>Pendiente individual</th><th>Situación y revisión</th><th>Comprobante / kardex</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $state=$row['classification']; ?>
                            <tr>
                                <td><strong><?= e($row['beneficiary_label_snapshot']?:'Funcionario') ?></strong>
                                    <small><?= e($row['beneficiary_document_snapshot']?:'Sin cédula histórica') ?></small>
                                    <small><?= e($row['beneficiary_unit_name_snapshot']?:'Sin unidad histórica') ?></small>
                                </td>
                                <td><strong><a href="<?= e(sindesg_url('?module=requests&action=show&id='.(int)$row['request_id'])) ?>"><?= e($row['request_code']) ?></a></strong>
                                    <small><?= e($row['p4_name']) ?></small>
                                    <small><?= e(substr((string)$row['requested_at'],0,10)) ?></small>
                                </td>
                                <td><strong><?= e($row['product_name']) ?></strong>
                                    <small>Talla <?= e($row['size_name']) ?> · <?= e($row['variant_code']) ?></small>
                                </td>
                                <td class="nomrep-qty"><?= e($units($row['quantity_requested'])) ?></td>
                                <td class="nomrep-qty"><?= e($units($row['quantity_approved'])) ?></td>
                                <td class="nomrep-qty"><?= e($units($row['quantity_dispatched'])) ?></td>
                                <td class="nomrep-qty"><?= e($units($row['quantity_received'])) ?></td>
                                <td class="nomrep-qty"><strong><?= e($units($row['quantity_delivered'])) ?></strong></td>
                                <td class="nomrep-qty"><?= e($units($state['pending_receive'])) ?></td>
                                <td class="nomrep-qty"><?= e($units($state['ready_to_hand'])) ?></td>
                                <td class="nomrep-qty"><strong><?= e($units($state['pending_individual'])) ?></strong></td>
                                <td class="nomrep-state">
                                    <strong><?= e($state['logistics']) ?></strong>
                                    <small><?= e($state['delivery']) ?></small>
                                    <?php foreach ($state['warnings'] as $warning): ?>
                                        <span class="nomrep-flag">⚠ <?= e($warning) ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td><div class="nomrep-actions">
                                    <?php if (!empty($row['last_safe_delivery_id'])): ?>
                                        <a href="<?= e(sindesg_url('?module=deliveries&action=show&id='.(int)$row['last_safe_delivery_id'])) ?>">Ver último comprobante</a>
                                    <?php else: ?><small>Sin comprobante nominal enlazable</small><?php endif; ?>
                                    <?php if (!empty($row['latest_delivery_at'])): ?>
                                        <small>Última entrega: <?= e($row['latest_delivery_at']) ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($row['last_movement_id'])): ?>
                                        <small>Movimiento #<?= e((int)$row['last_movement_id']) ?></small>
                                    <?php else: ?><small>Sin movimiento vinculado verificable</small><?php endif; ?>
                                    <small><?= e((int)$row['confirmed_deliveries']) ?> entregas confirmadas enlazadas</small>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if ($pages>1): ?>
            <nav class="nomrep-pagebar" aria-label="Paginación del reporte nominal">
                <span>Resultados <?= e($first) ?>–<?= e($last) ?> de <?= e($total) ?> · Filtrados en servidor</span>
                <?php if ($number>1): ?><a class="nomrep-page-link" href="<?= e($url($number-1)) ?>">← Anterior</a><?php endif; ?>
                <strong>Página <?= e($number) ?> de <?= e($pages) ?></strong>
                <?php if ($number<$pages): ?><a class="nomrep-page-link" href="<?= e($url($number+1)) ?>">Siguiente →</a><?php endif; ?>
            </nav>
        <?php endif; ?>
        <p class="nomrep-caption">El total de entregas se contrasta con los despachos confirmados. Las entregas manuales sin solicitud nominal no se mezclan en este reporte. No se crean firmas retroactivas ni se aplica ninguna regla automática de periodicidad.</p>
    </section>
</div>
