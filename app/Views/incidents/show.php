<?php
declare(strict_types=1);
$c=$incident;
$needsAdjustment=in_array($c['case_type'],['return','exchange','deterioration','replacement'],true);
$adjustType=$c['case_type']==='deterioration'?'return':$c['case_type'];
?>
<section class="incident-hero">
 <div><span class="eyebrow">INCIDENCIA <?= e($c['case_code']) ?></span>
 <h1><?= e($incidentTypes[$c['case_type']]??$c['case_type']) ?></h1>
 <p><?= e($incidentStatuses[$c['status']]??$c['status']) ?> · <?= e($c['warehouse_name']) ?></p></div>
 <a class="secondary-button" href="<?= e(sindesg_url('?module=incidents')) ?>">← Incidencias</a>
</section>
<section class="incident-panel">
 <div class="incident-summary">
  <article><span>Funcionario</span><strong><?= e($c['recipient_label_snapshot']) ?></strong><small><?= e($c['recipient_document_snapshot']?:'—') ?></small></article>
  <article><span>Prenda</span><strong><?= e($c['product_name']) ?></strong><small><?= e($c['size_name']?'Talla '.$c['size_name']:'Sin talla') ?></small></article>
  <article><span>Entrega de origen</span><strong><?= e($c['delivery_code']) ?></strong><small><?= e(sindesg_units((float)$c['delivered_quantity'])) ?> entregadas</small></article>
  <article><span>Incidencia</span><strong><?= e(sindesg_units((float)$c['quantity'])) ?> unidades</strong>
   <small><?= (int)$c['physically_received']===1?'Presentada físicamente':'Sin recepción física' ?> · <?= e($c['item_condition']) ?></small></article>
 </div>
 <h2>Motivo registrado</h2><p class="incident-comment"><?= e($c['reason']) ?></p>
 <?php if (!empty($c['resolution_notes'])): ?>
  <h2>Resolución</h2><p class="incident-comment"><?= e($c['resolution_notes']) ?></p>
 <?php endif; ?>
 <?php if ($incidentInstitutional && (int)($c['linked_adjustment_id']??0)>0): ?>
  <a href="<?= e(sindesg_url('?module=adjustments&action=show&id='.(int)$c['linked_adjustment_id'])) ?>">Consultar ajuste confirmado →</a>
 <?php endif; ?>
 <h2>Historial de actuaciones</h2>
 <div class="incident-events">
  <?php foreach($incidentHistory as $e): ?>
   <div><strong><?= e($e['action_code']) ?></strong>
    <small><?= e($e['occurred_at']) ?> · Usuario interno #<?= e((int)$e['actor_user_id']) ?></small>
    <?php if($e['note']): ?><p><?= e($e['note']) ?></p><?php endif; ?></div>
  <?php endforeach; ?>
 </div>
</section>
<?php if($incidentInstitutional && $c['status']==='submitted'): ?>
 <form method="post" class="incident-panel incident-action-form" action="<?= e(sindesg_url('?module=incidents&action=review')) ?>">
  <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= e($c['id']) ?>">
  <h2>Almacén Central: revisión inicial</h2>
  <p>Abrir la revisión no modifica existencias.</p>
  <button type="submit" class="primary-button">Iniciar revisión</button>
 </form>
<?php endif; ?>
<?php if($incidentInstitutional && $c['status']==='in_review' && $needsAdjustment): ?>
 <section class="incident-panel">
  <h2>Regularización autorizada</h2>
  <p>Central puede preparar un ajuste desde la entrega original. Un administrador distinto debe confirmarlo antes de registrar la resolución del caso.</p>
  <a class="secondary-button" href="<?= e(sindesg_url('?module=adjustments&action=new&source_delivery_item_id='.(int)$c['delivery_item_id'].'&type='.rawurlencode($adjustType))) ?>">Preparar ajuste desde la entrega →</a>
  <?php if($c['case_type']==='deterioration'): ?>
   <p class="incident-safety">Prenda deteriorada: gestionar como devolución no reutilizable; nunca reincorporarla automáticamente al stock.</p>
  <?php endif; ?>
 </section>
<?php endif; ?>
<?php if($incidentIsAdmin && $c['status']==='in_review'): ?>
 <section class="incident-panel">
  <h2>Resolución por ADMIN</h2>
  <form method="post" class="incident-action-form" action="<?= e(sindesg_url('?module=incidents&action=resolve')) ?>">
   <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e($c['id']) ?>">
   <label>ID del ajuste confirmado <?= $needsAdjustment?'* (obligatorio)':'(si aplica)' ?>
    <input type="number" min="1" name="adjustment_id" <?= $needsAdjustment?'required':'' ?>></label>
   <label>Justificación de resolución *<textarea name="resolution_notes" minlength="8" maxlength="1000" rows="3" required></textarea></label>
   <button type="submit" class="primary-button">Cerrar como resuelta</button>
  </form>
  <form method="post" class="incident-action-form" action="<?= e(sindesg_url('?module=incidents&action=reject')) ?>">
   <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e($c['id']) ?>">
   <label>Motivo para no proceder *<textarea name="resolution_notes" minlength="8" maxlength="1000" required></textarea></label>
   <button type="submit" class="secondary-button">Cerrar como no procedente</button>
  </form>
 </section>
<?php endif; ?>
<section class="incident-panel"><p><strong>Control institucional:</strong> este expediente no incrementa ni descuenta existencias. Las entregas y firmas originales permanecen intactas. Toda regularización de stock exige un ajuste autorizado.</p></section>
