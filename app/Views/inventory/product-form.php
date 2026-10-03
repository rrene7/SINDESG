<?php
declare(strict_types=1);
?>
<section class="form-shell">
    <div class="form-copy">
        <span class="eyebrow">CATÁLOGO MAESTRO</span>
        <h1>Nuevo artículo</h1>
        <p>Registre el artículo base. Las tallas, marcas y códigos específicos se agregan como variantes después de guardar el producto.</p>
    </div>

    <form class="catalog-form" method="post" enctype="multipart/form-data"
          action="<?= e(sindesg_url('?module=inventory&action=save-product')) ?>">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">

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
            <textarea name="description" rows="5" placeholder="Descripción del artículo"></textarea>
        </label>

        <label class="span-2 upload-field">
            <span>Fotografía principal</span>
            <input type="file" name="main_image" accept="image/jpeg,image/png,image/webp">
            <small>JPG, PNG o WEBP. Máximo 6 MB. La fotografía puede agregarse después si aún no está disponible.</small>
        </label>

        <div class="form-actions span-2">
            <a href="<?= e(sindesg_url('?module=inventory')) ?>">Cancelar</a>
            <button class="primary-button" type="submit">Crear artículo</button>
        </div>
    </form>
</section>
