<?php
declare(strict_types=1);

/**
 * Selector reutilizable con búsqueda por coincidencia.
 *
 * $catalogPicker = [
 *   'id' => string, 'name' => string, 'label' => string,
 *   'placeholder' => string,
 *   'options' => list<array{value:int,label:string,terms:string}>
 * ]
 * Solo presenta opciones activas consultadas desde RequestRepository.
 */
$pickerId = (string) ($catalogPicker['id'] ?? '');
$pickerName = (string) ($catalogPicker['name'] ?? '');
$pickerOptions = (array) ($catalogPicker['options'] ?? []);
$pickerLabel = (string) ($catalogPicker['label'] ?? 'Artículo y variante');
$pickerHint = (string) ($catalogPicker['placeholder'] ?? 'Ej.: botas talla 8.5, código ISTMO, marca...');
?>
<div class="request-variant-field request-catalog-picker" data-catalog-picker>
    <label for="<?= e($pickerId . '-search') ?>">
        <span>Buscar por coincidencia</span>
        <input id="<?= e($pickerId . '-search') ?>"
               type="search"
               data-catalog-search
               autocomplete="off"
               placeholder="<?= e($pickerHint) ?>"
               aria-controls="<?= e($pickerId) ?>">
    </label>

    <label for="<?= e($pickerId) ?>">
        <span><?= e($pickerLabel) ?> *</span>
        <select id="<?= e($pickerId) ?>" name="<?= e($pickerName) ?>"
                data-catalog-select required>
            <option value="">Seleccione una coincidencia...</option>
            <?php foreach ($pickerOptions as $entry): ?>
                <option value="<?= (int) $entry['value'] ?>"
                        data-search-terms="<?= e((string) $entry['terms']) ?>">
                    <?= e((string) $entry['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <p class="request-catalog-result-count" data-catalog-count role="status" aria-live="polite">
        <?= count($pickerOptions) ?> opciones activas disponibles. Buscar no requiere tener existencias.
    </p>
    <div class="request-catalog-no-matches" data-catalog-empty
         <?= $pickerOptions !== [] ? 'hidden' : '' ?>>
        <strong>No hay coincidencias activas en el catálogo.</strong>
        <p>Si buscas botas, verifica que el artículo y sus tallas estén creados y activos.
           Las variantes con stock 0 también aparecen aquí, si están activas.</p>
        <div class="request-catalog-actions">
            <a href="<?= e(sindesg_url('?module=inventory&q=bota')) ?>">
                Buscar botas en Inventario
            </a>
            <a href="<?= e(sindesg_url('?module=inventory&action=new-product')) ?>">
                Registrar artículo o botas
            </a>
        </div>
        <small>Registrar el artículo o sus tallas NO agrega existencias al Almacén Central.</small>
    </div>
</div>
