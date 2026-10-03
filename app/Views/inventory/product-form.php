<?php
declare(strict_types=1);
?>
<section class="form-shell">
    <div class="form-copy">
        <span class="eyebrow">CATÁLOGO MAESTRO</span>
        <h1>Nuevo artículo</h1>
        <p>SINDESG creará el artículo y una primera variante stockable en un solo paso. Si el equipo no tiene talla, marca ni código externo, deje esos campos en blanco: el sistema generará igualmente su código interno.</p>
    </div>

    <form class="catalog-form" method="post" enctype="multipart/form-data"
          action="<?= e(sindesg_url('?module=inventory&action=save-product')) ?>">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="return_to" value="<?= e($returnTo ?? 'inventory') ?>">

        <label>
            <span>Categoría *</span>
            <select name="category_id" required>
                <option value="">Seleccione...</option>
                <?php foreach ($catalogCategories as $category): ?>
                    <?php if ((int) $category['is_active'] === 1): ?>
                        <option value="<?= e($category['id']) ?>"><?= e($category['name']) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Nombre del artículo *</span>
            <input type="text" name="name" maxlength="180" required placeholder="Ej.: Camisa de faena azul">
        </label>

        <label>
            <span>Aplicación</span>
            <select name="sex">
                <option value="no_aplica">No aplica</option>
                <option value="unisex">Unisex</option>
                <option value="masculino">Masculino</option>
                <option value="femenino">Femenino</option>
            </select>
        </label>

        <label>
            <span>Color general</span>
            <input type="text" name="color" maxlength="80" placeholder="Ej.: Azul">
        </label>

        <label class="span-2">
            <span>Descripción</span>
            <textarea name="description" rows="4" placeholder="Descripción del artículo"></textarea>
        </label>

        <div class="auto-code-note span-2">
            <span class="auto-code-badge">1ª</span>
            <div>
                <strong>Primera variante incluida automáticamente</strong>
                <small>Esto evita crear un artículo que luego no pueda recibirse en inventario. Los códigos internos del artículo y la variante los genera SINDESG.</small>
            </div>
        </div>

        <label>
            <span>Talla inicial</span>
            <input type="text" name="initial_size_value" maxlength="40" placeholder="Ej.: M, 34, 8">
        </label>

        <label>
            <span>Marca inicial</span>
            <input type="text" name="initial_brand_name" maxlength="120" placeholder="Opcional">
        </label>

        <label>
            <span>Código institucional / ISTMO</span>
            <input type="text" name="initial_institutional_code" maxlength="120" placeholder="Opcional">
        </label>

        <label>
            <span>Código del proveedor</span>
            <input type="text" name="initial_supplier_code" maxlength="120" placeholder="Opcional">
        </label>

        <label>
            <span>Color específico de la variante</span>
            <input type="text" name="initial_variant_color" maxlength="80" placeholder="Opcional">
        </label>

        <label class="upload-field">
            <span>Fotografía principal</span>
            <input type="file" name="main_image" accept="image/jpeg,image/png,image/webp">
            <small>JPG, PNG o WEBP. Máximo 6 MB.</small>
        </label>

        <div class="form-actions span-2">
            <a href="<?= e(($returnTo ?? 'inventory') === 'receipts'
                ? sindesg_url('?module=receipts&action=new')
                : sindesg_url('?module=inventory')) ?>">Cancelar</a>
            <button class="primary-button" type="submit">
                <?= ($returnTo ?? 'inventory') === 'receipts' ? 'Crear y volver a recepción' : 'Crear artículo' ?>
            </button>
        </div>
    </form>
</section>
