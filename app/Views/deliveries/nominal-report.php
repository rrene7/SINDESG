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
<style>
.nomrep{display:grid;gap:15px;margin-bottom:24px}
.nomrep .nomrep-hero{display:flex;justify-content:space-between;gap:15px;flex-wrap:wrap;align-items:center;padding:21px 24px;border-radius:15px;background:linear-gradient(113deg,#082c46,#11628a);color:#fff}
.nomrep .nomrep-hero h1{color:#fff;font-size:clamp(1.45rem,2.2vw,2rem);margin:6px 0}
.nomrep .nomrep-hero p{color:#cbe8f5;font-size:.87rem;margin:0;max-width:710px}
.nomrep .nomrep-hero a,.nomrep .nomrep-hero button{border:1px solid #91c6e4;border-radius:9px;padding:10px 13px;background:#fff;color:#123c58;text-decoration:none;font-weight:700;cursor:pointer;font-size:.82rem}
.nomrep .nomrep-hero-actions{display:flex;gap:8px;flex-wrap:wrap}
.nomrep .nomrep-panel{background:var(--surface,#fff);border:1px solid var(--border,#d8e3eb);border-radius:13px;padding:17px}
.nomrep .nomrep-panel h2{font-size:1.06rem;margin:0 0 8px}
.nomrep .nomrep-hint{font-size:.78rem;opacity:.8;line-height:1.5}
.nomrep .nomrep-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:11px;align-items:end}
.nomrep label{font-size:.78rem;font-weight:650;display:grid;gap:5px}
.nomrep input,.nomrep select{width:100%;min-width:0;padding:10px 11px;border-radius:8px;border:1px solid #9ab6c9;background:var(--input-bg,#fff);color:inherit;font:inherit;box-sizing:border-box}
.nomrep .nomrep-filter-actions{display:flex;gap:9px;flex-wrap:wrap;align-items:center}
.nomrep .nomrep-submit,.nomrep .nomrep-page-link{display:inline-block;border:1px solid #167cb3;background:#1175ad;color:#fff;padding:10px 14px;border-radius:8px;font-weight:700;text-decoration:none;cursor:pointer;font-size:.83rem}
.nomrep .nomrep-reset{font-size:.81rem}
.nomrep .nomrep-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:11px}
.nomrep .nomrep-kpi{border:1px solid var(--border,#d8e3eb);border-radius:11px;padding:15px;background:var(--surface,#fff)}
.nomrep .nomrep-kpi span{display:block;font-size:.75rem;opacity:.8}
.nomrep .nomrep-kpi strong{font-size:1.6rem;font-variant-numeric:tabular-nums}
.nomrep .nomrep-table-wrap{overflow-x:auto}
.nomrep table{width:100%;border-collapse:collapse;min-width:1250px;font-size:.79rem}
.nomrep th{background:#e6f1f8;color:#153950;font-size:.74rem;white-space:nowrap;text-align:left}
.nomrep th,.nomrep td{padding:11px 12px;border-bottom:1px solid var(--border,#d8e3eb);vertical-align:top}
.nomrep td strong,.nomrep td small{display:block}
.nomrep td small{font-size:.7rem;margin-top:4px;opacity:.8}
.nomrep .nomrep-qty{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.nomrep .nomrep-flag{display:block;color:#9d4d10;font-weight:700;margin-top:5px;font-size:.72rem;max-width:210px}
.nomrep .nomrep-state{font-size:.76rem;line-height:1.45;white-space:normal;min-width:160px}
.nomrep .nomrep-actions{display:grid;gap:5px;min-width:130px}
.nomrep .nomrep-actions a{color:#1685b9;text-decoration:underline;font-weight:650}
.nomrep .nomrep-pagebar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-top:15px;font-size:.85rem}
.nomrep .nomrep-empty{padding:22px;text-align:center}
.nomrep .nomrep-caption{font-size:.75rem;opacity:.8;line-height:1.5}
@media(max-width:680px){.nomrep .nomrep-hero,.nomrep .nomrep-panel{padding:15px}.nomrep .nomrep-kpis{grid-template-columns:1fr}.nomrep .nomrep-hero-actions{width:100%}}
@media print{
 body *{visibility:hidden}
 .nomrep,.nomrep *{visibility:visible}
 .nomrep{position:absolute;top:0;left:0;width:100%;color:#000}
 .nomrep .nomrep-hero{background:#fff;color:#000;border:1px solid #777}
 .nomrep .nomrep-hero h1,.nomrep .nomrep-hero p{color:#000}
 .nomrep .nomrep-hero-actions,.nomrep .nomrep-filters,.nomrep .nomrep-filter-actions,.nomrep .nomrep-pagebar,.nomrep .nomrep-actions{display:none!important}
 .nomrep .nomrep-table-wrap{overflow:visible}
 .nomrep table{min-width:0;font-size:7pt}
 .nomrep th,.nomrep td{padding:4px}
}
</style>
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
            <label>Funcionario, cédula o artículo
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
                                <td><strong><?= e($row['request_code']) ?></strong>
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
