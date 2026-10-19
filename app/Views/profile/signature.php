<?php
declare(strict_types=1);
?>
<style>
.p4-profile{max-width:930px;display:grid;gap:14px;margin:0 auto}
.p4-profile .p4-profile-hero,.p4-profile .p4-profile-card{background:#071e35;border:1px solid #23577b;border-radius:14px;padding:20px 24px}
.p4-profile h1{font-size:clamp(1.5rem,2.6vw,2rem);margin:6px 0}
.p4-profile h2{font-size:1.15rem;margin:0 0 12px}
.p4-profile p,.p4-profile small{color:#b0cee0;line-height:1.55}
.p4-profile canvas{display:block;max-width:100%;width:100%;height:170px;border:1px solid #d7e4ee;background:#fff;border-radius:9px;cursor:crosshair;touch-action:none}
.p4-profile input[type=file],.p4-profile input[type=password]{display:block;max-width:100%;width:100%;margin:8px 0 12px;background:#06182b;border:1px solid #2a5878;border-radius:7px;color:#f2fbff;padding:12px}
.p4-profile label{font-weight:650;font-size:.88rem}
.p4-profile .p4-profile-controls{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:12px 0}
.p4-profile button{background:#187bb8;border:1px solid #46a5d7;color:white;font-weight:650;padding:11px 15px;border-radius:8px;cursor:pointer}
.p4-profile .p4-outline{background:transparent}
.p4-profile .p4-profile-preview{height:100px;max-width:100%;padding:10px;border:1px solid #dce3ec;border-radius:8px;background:white;object-fit:contain}
</style>
<div class="p4-profile">
    <header class="p4-profile-hero">
        <span class="eyebrow">MI PERFIL · P4</span>
        <h1>Mi firma institucional</h1>
        <p>Registra tu firma una sola vez. Para incorporarla a una entrega deberás autorizar expresamente la operación.</p>
        <a href="<?= e(sindesg_url()) ?>">← Volver al panel P4</a>
    </header>
    <section class="p4-profile-card">
        <h2>Firma registrada</h2>
        <?php if ($currentSignature): ?>
            <img class="p4-profile-preview" alt="Mi firma actual" src="data:image/png;base64,<?= e(base64_encode((string)$currentSignature['signature_png'])) ?>">
            <p>Actualizada: <?= e($currentSignature['updated_at']) ?></p>
            <small>Si la reemplazas, los comprobantes históricos conservarán la firma que tenían al confirmarse.</small>
        <?php else: ?>
            <p>Aún no tienes firma registrada. Completa el formulario para usarla en comprobantes futuros.</p>
        <?php endif; ?>
    </section>
    <form class="p4-profile-card" method="post" enctype="multipart/form-data"
        action="<?= e(sindesg_url('?module=profile&action=signature')) ?>" data-p4-issuer-form>
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <h2>Registrar o reemplazar mi firma</h2>
        <p>Puedes dibujarla con el mouse o el dedo, o subir una imagen PNG. Máximo 160 KB.</p>
        <label for="p4SignatureCanvas">Dibujar firma</label>
        <canvas id="p4SignatureCanvas" width="650" height="170" aria-label="Área para dibujar mi firma"></canvas>
        <input type="hidden" name="signature_png" data-issuer-value>
        <div class="p4-profile-controls"><button type="button" class="p4-outline" data-issuer-clear>Limpiar dibujo</button></div>
        <label for="p4SignatureUpload">O subir archivo PNG</label>
        <input id="p4SignatureUpload" type="file" accept="image/png,.png" name="signature_file" data-issuer-upload>
        <label for="p4SignaturePassword">Confirma con tu contraseña de SINDESG</label>
        <input id="p4SignaturePassword" type="password" name="password" autocomplete="current-password" required>
        <button type="submit">Guardar mi firma</button>
        <p><small>Solo tú puedes registrar tu firma. Su uso posterior requiere que confirmes cada entrega; no equivale a una firma electrónica certificada.</small></p>
    </form>
</div>
<script>
(() => {
  const form=document.querySelector('[data-p4-issuer-form]');if(!form)return;
  const canvas=form.querySelector('canvas'),ctx=canvas.getContext('2d'),value=form.querySelector('[data-issuer-value]'),upload=form.querySelector('[data-issuer-upload]');
  let drawing=false,drawn=false;ctx.lineWidth=2.8;ctx.lineCap='round';ctx.lineJoin='round';ctx.strokeStyle='#132940';
  const pos=e=>{const r=canvas.getBoundingClientRect();return {x:(e.clientX-r.left)*canvas.width/r.width,y:(e.clientY-r.top)*canvas.height/r.height}};
  canvas.addEventListener('pointerdown',e=>{drawing=true;const p=pos(e);ctx.beginPath();ctx.moveTo(p.x,p.y);ctx.lineTo(p.x+.1,p.y+.1);ctx.stroke();drawn=true;canvas.setPointerCapture(e.pointerId);});
  canvas.addEventListener('pointermove',e=>{if(drawing){const p=pos(e);ctx.lineTo(p.x,p.y);ctx.stroke();}});
  ['pointerup','pointercancel'].forEach(name=>canvas.addEventListener(name,()=>drawing=false));
  form.querySelector('[data-issuer-clear]').addEventListener('click',()=>{ctx.clearRect(0,0,canvas.width,canvas.height);drawn=false;value.value='';});
  form.addEventListener('submit',e=>{
    if(!drawn && !upload.files.length){e.preventDefault();alert('Dibuja o selecciona tu firma en PNG.');return;}
    if(drawn){value.value=canvas.toDataURL('image/png');if(value.value.length>220000){e.preventDefault();alert('Firma demasiado grande. Intenta nuevamente.');}}
  });
})();
</script>
