<?php
declare(strict_types=1);
?>
<section class="dashboard-hero">
    <div>
        <span class="eyebrow">OPERACIÓN P4</span>
        <h1>Panel de mi unidad</h1>
        <p>Solicitudes, transferencias y entregas de las unidades P4 expresamente autorizadas.</p>
    </div>
</section>
<section class="dashboard-grid">
    <article class="dashboard-card">
        <span class="eyebrow">MIS SOLICITUDES RECIENTES</span>
        <h2><?= count($p4DashboardRequests) ?></h2>
        <p>Solicitudes visibles de mis unidades, hasta las 100 más recientes.</p>
        <a href="<?= e(sindesg_url('?module=requests')) ?>">Consultar solicitudes →</a>
    </article>
    <article class="dashboard-card">
        <span class="eyebrow">MIS TRANSFERENCIAS RECIENTES</span>
        <h2><?= count($p4DashboardTransfers) ?></h2>
        <p>Transferencias correspondientes a mis unidades autorizadas.</p>
        <a href="<?= e(sindesg_url('?module=distribution')) ?>">Consultar transferencias →</a>
    </article>
    <article class="dashboard-card">
        <span class="eyebrow">MIS ENTREGAS RECIENTES</span>
        <h2><?= count($p4DashboardDeliveries) ?></h2>
        <p>Entregas individuales registradas en mis unidades.</p>
        <a href="<?= e(sindesg_url('?module=deliveries')) ?>">Consultar entregas →</a>
    </article>
</section>
<?php if ($p4DashboardRequests === [] && $p4DashboardTransfers === [] && $p4DashboardDeliveries === []): ?>
<section class="request-panel">
    <p>No hay operaciones recientes dentro de las unidades P4 autorizadas, o su cuenta todavía no tiene un P4 asignado.</p>
</section>
<?php endif; ?>
