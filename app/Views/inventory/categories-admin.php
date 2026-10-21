<?php
declare(strict_types=1);
?>
<section class="inventory-hero">
    <div>
        <span class="eyebrow">ADMINISTRADOR · CONTROL DE CATÁLOGO</span>
        <h1>Administrar categorías</h1>
        <p>Edite sus datos, cambie el orden y retire del catálogo activo categorías sin artículos. Toda modificación es auditada; los códigos institucionales se conservan.</p>
    </div>
    <div class="inventory-actions">
        <a class="secondary-button" href="<?= e(sindesg_url('?module=inventory')) ?>">← Inventario</a>
        <a class="primary-button" href="<?= e(sindesg_url('?module=inventory&action=new-category')) ?>">+ Nueva categoría</a>
    </div>
</section>

<section class="category-admin-notice">
    <strong>Protección del inventario y de los funcionarios</strong>
    <p>Eliminar del catálogo significa <strong>archivar</strong>, no borrar datos. Si existe al menos un artículo asociado, aunque sea histórico o esté inactivo, SINDESG impedirá la operación. El stock, kardex, entregas y comprobantes nunca se eliminan desde esta pantalla.</p>
</section>

<section class="category-admin-panel">
    <div class="inventory-section-head">
        <div>
            <span class="eyebrow">GOBIERNO DEL CATÁLOGO</span>
            <h2>Listado de categorías</h2>
        </div>
        <small><?= count($managedCategories ?? []) ?> categorías registradas, incluidas las archivadas</small>
    </div>

    <div class="responsive-table">
        <table class="category-admin-table">
            <thead>
                <tr><th>Código</th><th>Categoría</th><th>Orden</th><th>Artículos vinculados</th><th>Estado</th><th>Acciones administrativas</th></tr>
            </thead>
            <tbody>
            <?php foreach (($managedCategories ?? []) as $category): ?>
                <?php
                    if (!is_array($category)) continue;
                    $id = (int) $category['id'];
                    $active = (int) $category['is_active'] === 1;
                    $used = (int) $category['all_product_count'];
                ?>
                <tr>
                    <td><code><?= e($category['code']) ?></code></td>
                    <td><strong><?= e($category['name']) ?></strong>
                        <small class="table-subline"><?= e((string) ($category['description'] ?? 'Sin descripción')) ?></small>
                    </td>
                    <td><?= (int) $category['sort_order'] ?></td>
                    <td><?= $used ?> <?= $used === 1 ? 'artículo' : 'artículos' ?></td>
                    <td><span class="category-status <?= $active ? 'is-active' : 'is-archived' ?>"><?= $active ? 'Activa' : 'Archivada' ?></span></td>
                    <td class="category-admin-cell">
                        <a class="secondary-button category-action" href="<?= e(sindesg_url('?module=inventory&action=edit-category&id=' . $id)) ?>">Editar</a>
                        <?php if ($active && $used === 0): ?>
                            <details class="category-danger-details">
                                <summary>Eliminar del catálogo</summary>
                                <form method="post" action="<?= e(sindesg_url('?module=inventory&action=archive-category')) ?>">
                                    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="category_id" value="<?= $id ?>">
                                    <label>Motivo obligatorio
                                        <input type="text" name="reason" minlength="8" maxlength="500" required placeholder="Ej.: Duplicada sin uso">
                                    </label>
                                    <label>Escriba ARCHIVAR para confirmar
                                        <input type="text" name="confirm" pattern="ARCHIVAR" required autocomplete="off" placeholder="ARCHIVAR">
                                    </label>
                                    <button type="submit" class="category-danger-button">Confirmar retiro</button>
                                </form>
                            </details>
                        <?php elseif ($active): ?>
                            <small class="category-blocked">No se puede eliminar: tiene artículos relacionados.</small>
                        <?php else: ?>
                            <details class="category-restore-details">
                                <summary>Restaurar</summary>
                                <form method="post" action="<?= e(sindesg_url('?module=inventory&action=restore-category')) ?>">
                                    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="category_id" value="<?= $id ?>">
                                    <label>Motivo obligatorio
                                        <input type="text" name="reason" minlength="8" maxlength="500" required placeholder="Ej.: Reactivación autorizada">
                                    </label>
                                    <input type="hidden" name="confirm" value="RESTAURAR">
                                    <button type="submit" class="secondary-button">Confirmar restauración</button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="category-admin-notice">
    <strong>Otras facultades del Administrador SINDESG</strong>
    <p>El módulo Administración permite gestionar usuarios, roles y alcances P4; Auditoría consulta los registros de acciones; Inventario y Almacenes mantienen la trazabilidad del catálogo y de la dotación. Las operaciones de existencias se realizan únicamente por sus movimientos autorizados.</p>
    <div class="inventory-actions">
        <a class="secondary-button" href="<?= e(sindesg_url('?module=admin')) ?>">Usuarios y roles</a>
        <a class="secondary-button" href="<?= e(sindesg_url('?module=audit')) ?>">Consultar auditoría</a>
        <a class="secondary-button" href="<?= e(sindesg_url('?module=warehouses')) ?>">Almacenes y P4</a>
    </div>
</section>
