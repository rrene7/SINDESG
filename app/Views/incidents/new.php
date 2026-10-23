<?php
declare(strict_types=1);
$s=$incidentSource;
?>
<section class="incident-hero">
 <div><span class="eyebrow">P4 · RECEPCIÓN PRESENCIAL</span><h1>Gestionar devolución o incidencia</h1>
 <p>El almacenista verifica la prenda personalmente y registra el caso; este formulario no modifica existencias.</p></div>
 <a class="secondary-button" href="<?= e(sindesg_url('?module=deliveries&action=show&id='.(int)$s['delivery_id'])) ?>">← Volver</a>
</section>
<form class="incident-panel incident-form" method="post" action="<?= e(sindesg_url('?module=incidents&action=save')) ?>">
 <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
 <input type="hidden" name="delivery_item_id" value="<?= e((int)$s['delivery_item_id']) ?>">
 <div class="incident-summary">
  <article><span>Funcionario receptor</span><strong><?= e($s['recipient_label_snapshot']) ?></strong><small><?= e($s['recipient_document_snapshot']?:'—') ?></small></article>
  <article><span>Artículo entregado</span><strong><?= e($s['product_name']) ?></strong><small><?= e($s['size_name']?'Talla '.$s['size_name']:'Sin talla') ?></small></article>
  <article><span>Almacén de origen</span><strong><?= e($s['warehouse_name']) ?></strong><small><?= e($s['delivery_code']) ?></small></article>
 </div>
 <div class="incident-fields">
  <label>Tipo de incidencia *<select name="case_type" required>
   <option value="">Seleccione...</option>
   <?php foreach($incidentTypes as $key=>$label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
  </select></label>
  <label>Cantidad *<input type="number" name="quantity" required min="0.001" step="0.001"
      max="<?= e(number_format((float)$s['returnable_quantity'],3,'.','')) ?>"
      value="<?= e(number_format(min(1.0,(float)$s['returnable_quantity']),3,'.','')) ?>">
   <small>Entregada: <?= e(sindesg_units((float)$s['delivered_quantity'])) ?> · Máximo sin devolver: <?= e(sindesg_units((float)$s['returnable_quantity'])) ?></small></label>
  <label>Condición de la prenda presentada<select name="item_condition">
    <option value="">Seleccione, si hay recepción física...</option>
    <option value="evaluation_pending">Pendiente de evaluación</option>
    <option value="reusable">Reutilizable</option>
    <option value="non_reusable">No reutilizable</option>
  </select></label>
  <label class="incident-check"><input type="checkbox" name="physically_received" value="1">
   <span>Confirmo haber verificado presencialmente esta prenda en el P4. Obligatorio para devoluciones, cambios y deterioro.</span></label>
  <label class="incident-wide">Motivo / observaciones de recepción *<textarea name="reason" required minlength="8" maxlength="500" rows="3"
    placeholder="Explique la razón y el estado de la prenda o el hecho reportado."></textarea></label>
 </div>
 <p class="incident-safety">La dotación entregada, sus firmas y el kardex permanecen intactos. Central revisa el expediente y cualquier movimiento requiere el ajuste autorizado correspondiente.</p>
 <div class="incident-actions">
  <a href="<?= e(sindesg_url('?module=deliveries&action=show&id='.(int)$s['delivery_id'])) ?>">Cancelar</a>
  <button type="submit" class="primary-button">Registrar incidencia presencial</button>
 </div>
</form>
