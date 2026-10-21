<?php
declare(strict_types=1);
$groupCount = count($duplicateGroups ?? []);
$articleCount = array_sum(array_map(
    static fn (array $group): int => count($group['items']),
    $duplicateGroups ?? []
));
?>
<section class="inventory-hero">
    <div>
        <span class="eyebrow">ADMINISTRADOR · DEPURACIÓN CONTROLADA</span>
        <h1>Posibles artículos repetidos</h1>
        <p>Revisión de nombres iguales ignorando mayúsculas, tildes, puntuación y espacios. Una coincidencia de nombre es una alerta, no prueba que sean el mismo artículo.</p>
    </div>
    <div class="inventory-actions">
        <a class="secondary-button" href="<?= e(sindesg_url('?module=inventory')) ?>">← Inventario</a>
        <a class="secondary-button" href="<?= e(sindesg_url('?module=inventory&action=categories')) ?>">Categorías</a>
    </div>
</section>
<section class="catalog-duplicate-summary">
    <article><span>Grupos de posibles coincidencias</span><strong><?= $groupCount ?></strong></article>
    <article><span>Artículos a revisar</span><strong><?= $articleCount ?></strong></article>
    <article><span>Operación</span><strong>Solo lectura</strong></article>
</section>
<section class="category-admin-notice">
    <strong>Importante: no se han eliminado ni fusionado productos</strong>
    <p>La misma denominación puede corresponder a tallas, marcas, códigos institucionales, tipos de dotación o historiales diferentes. Compare las variantes, categorías y existencias antes de corregir nombres. No transfiera stock ni cambie movimientos para resolver coincidencias.</p>
</section>

<?php if (($duplicateGroups ?? []) === []): ?>
    <section class="catalog-empty">
        <div class="catalog-empty-mark">✓</div>
        <h2>Sin coincidencias nominales exactas</h2>
        <p>No se encontraron dos artículos con el mismo nombre normalizado. Esto no excluye nombres parecidos, abreviaturas ni categorías que deberían conciliarse.</p>
        <a class="secondary-button" href="<?= e(sindesg_url('?module=inventory')) ?>">Regresar a Inventario</a>
    </section>
<?php else: ?>
    <?php foreach ($duplicateGroups as $group): ?>
        <section class="catalog-duplicate-group">
            <div class="inventory-section-head">
                <div>
                    <span class="eyebrow">POSIBLE COINCIDENCIA · NO CONFIRMADA</span>
                    <h2><?= e($group['name']) ?></h2>
                    <p><?= count($group['items']) ?> registros distintos<?= (int)$group['category_count'] > 1 ? ' en ' . (int)$group['category_count'] . ' categorías' : ' en una misma categoría' ?>.</p>
                </div>
            </div>
            <div class="responsive-table">
                <table class="catalog-duplicate-table">
                    <thead>
                        <tr>
                            <th>Artículo / código</th>
                            <th>Categoría</th>
                            <th>Aplicación / color</th>
                            <th>Variantes (tallas y marcas)</th>
                            <th>Stock institucional</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($group['items'] as $item): ?>
                        <tr>
                            <td>
                                <strong><?= e($item['name']) ?></strong>
                                <small class="table-subline"><?= e($item['internal_code'] ?: 'Sin código') ?></small>
                                <small class="table-subline">ID <?= (int)$item['id'] ?> · <?= e($item['status']) ?></small>
                            </td>
                            <td><?= e($item['category_name']) ?></td>
                            <td><?= e($item['sex']) ?> · <?= e($item['color'] ?: 'Sin color') ?></td>
                            <td>
                                <?php if (($item['variants'] ?? []) === []): ?>
                                    <small>Sin variantes activas</small>
                                <?php else: ?>
                                    <?php foreach (array_slice($item['variants'], 0, 6) as $variant): ?>
                                        <small class="catalog-duplicate-variant">
                                            <?= e(($variant['size_name'] ?? '') ?: 'S/T') ?>
                                            · <?= e(($variant['brand_name'] ?? '') ?: 'Sin marca') ?>
                                            · <?= e(($variant['institutional_code'] ?? '') ?: (($variant['internal_code'] ?? '') ?: 'Sin código')) ?>
                                        </small>
                                    <?php endforeach; ?>
                                    <?php if (count($item['variants']) > 6): ?>
                                        <small>+<?= count($item['variants']) - 6 ?> más; consultar detalle</small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= e(sindesg_units((float)($item['stock']['stock_total'] ?? 0))) ?></strong>
                                <small class="table-subline">Disponible Central: <?= e(sindesg_units((float)($item['stock']['stock_available'] ?? 0))) ?></small>
                                <small class="table-subline">P4: <?= e(sindesg_units((float)($item['stock']['stock_p4'] ?? 0))) ?></small>
                                <small class="table-subline">Tránsito: <?= e(sindesg_units((float)($item['stock']['stock_transit'] ?? 0))) ?></small>
                            </td>
                            <td>
                                <div class="catalog-duplicate-actions">
                                    <a class="secondary-button" href="<?= e(sindesg_url('?module=inventory&action=product&id=' . (int)$item['id'])) ?>">Ver artículo y kardex</a>
                                    <a class="secondary-button" href="<?= e(sindesg_url('?module=inventory&action=edit-product&id=' . (int)$item['id'])) ?>">Editar descripción</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="catalog-duplicate-disclaimer">No existe fusión ni borrado automático. Las existencias y documentos permanecen vinculados a sus identificadores originales.</p>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
