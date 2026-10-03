<?php
declare(strict_types=1);
?>
<section class="form-shell">
    <div class="form-copy">
        <span class="eyebrow">CATÁLOGO MAESTRO</span>
        <h1>Nueva categoría</h1>
        <p>Las categorías permiten agrupar artículos como camisas, pantalones, botas, gorras, kepis o materiales. No use una categoría distinta solo para representar una talla.</p>
    </div>

    <form class="catalog-form" method="post" action="<?= e(sindesg_url('?module=inventory&action=save-category')) ?>">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">

        <label class="span-2">
            <span>Nombre *</span>
            <input type="text" name="name" maxlength="120" required placeholder="Ej.: Camisa">
        </label>

        <div class="auto-code-note span-2">
            <span class="auto-code-badge">AUTO</span>
            <div>
                <strong>Código de categoría generado por SINDESG</strong>
                <small>El sistema asignará la secuencia automáticamente, por ejemplo: CAT-000011, CAT-000012, CAT-000013. El usuario no podrá modificarla.</small>
            </div>
        </div>

        <label class="span-2">
            <span>Descripción</span>
            <textarea name="description" rows="5" placeholder="Descripción general de la categoría"></textarea>
        </label>

        <div class="form-actions span-2">
            <a href="<?= e(sindesg_url('?module=inventory')) ?>">Cancelar</a>
            <button class="primary-button" type="submit">Guardar categoría</button>
        </div>
    </form>
</section>
