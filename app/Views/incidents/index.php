<?php
declare(strict_types=1);
$l=$incidentList;
?>
<section class="incident-hero">
 <div><span class="eyebrow">DOTACIÓN · EXPEDIENTES</span><h1><?= $incidentInstitutional?'Incidencias institucionales':'Incidencias de mis P4' ?></h1>
 <p>Devoluciones, cambios y deterioros registrados en el almacén. No hay movimientos de inventario desde esta pantalla.</p></div>
 <a class="secondary-button" href="<?= e(sindesg_url($incidentInstitutional?'?module=adjustments':'?module=deliveries')) ?>">← Volver</a>
</section>
<section class="incident-panel">
 <form class="incident-filters" action="<?= e(sindesg_url()) ?>" method="get">
  <input type="hidden" name="module" value="incidents">
  <label>Filtrar por estado<select name="status">
   <option value="">Todos los estados</option>
   <?php foreach($incidentStatuses as $code=>$name): ?>
    <option value="<?= e($code) ?>" <?= $code===$l['status']?'selected':'' ?>><?= e($name) ?></option>
   <?php endforeach; ?>
  </select></label>
  <button type="submit" class="primary-button">Filtrar</button><span><?= e($l['total']) ?> casos</span>
 </form>
 <?php if (!$l['items']): ?>
  <p class="incident-empty">Sin incidencias para el filtro seleccionado. El P4 inicia el expediente desde la entrega confirmada.</p>
 <?php else: ?>
  <div class="incident-list">
   <?php foreach($l['items'] as $case): ?>
    <a href="<?= e(sindesg_url('?module=incidents&action=show&id='.(int)$case['id'])) ?>">
     <span><strong><?= e($case['case_code']) ?> · <?= e($incidentTypes[$case['case_type']]??$case['case_type']) ?></strong>
      <small><?= e($case['recipient_label_snapshot']) ?> · <?= e($case['warehouse_name']) ?></small></span>
     <span><strong><?= e($incidentStatuses[$case['status']]??$case['status']) ?></strong>
      <small><?= e($case['product_name']) ?> · <?= e(sindesg_units((float)$case['quantity'])) ?> unid.</small></span>
    </a>
   <?php endforeach; ?>
  </div>
 <?php endif; ?>
 <nav class="incident-pages" aria-label="Paginación de incidencias">
  <?php if($l['page']>1): ?><a href="<?= e(sindesg_url('?module=incidents&status='.rawurlencode($l['status']).'&page='.($l['page']-1))) ?>">← Anterior</a><?php endif; ?>
  <strong>Página <?= e($l['page']) ?> de <?= e($l['pages']) ?></strong>
  <?php if($l['page']<$l['pages']): ?><a href="<?= e(sindesg_url('?module=incidents&status='.rawurlencode($l['status']).'&page='.($l['page']+1))) ?>">Siguiente →</a><?php endif; ?>
 </nav>
</section>
