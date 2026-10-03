<?php
declare(strict_types=1);
?>
<section class="module-hero">
    <div class="module-hero-icon"><?= e($module['icon']) ?></div>
    <div>
        <span class="eyebrow"><?= e($module['phase']) ?></span>
        <h1><?= e($module['label']) ?></h1>
        <p><?= e($module['description']) ?></p>
    </div>
</section>

<section class="module-placeholder">
    <div class="blueprint">
        <span class="blueprint-line"></span>
        <span class="blueprint-line short"></span>
        <span class="blueprint-line"></span>
        <span class="blueprint-line medium"></span>
    </div>
    <div>
        <span class="eyebrow">MÓDULO PREPARADO EN NAVEGACIÓN</span>
        <h2>Se desarrollará por bloques</h2>
        <p>La Fase 1 deja la navegación operativa y la estructura técnica lista. La funcionalidad de este módulo se incorporará en su fase correspondiente de la hoja de ruta.</p>
        <a class="primary-button" href="<?= e(sindesg_url()) ?>">← Volver al dashboard</a>
    </div>
</section>
