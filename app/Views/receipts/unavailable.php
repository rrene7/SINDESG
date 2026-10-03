<?php
declare(strict_types=1);
?>
<section class="receipt-empty standalone">
    <div class="receipt-empty-mark">!</div>
    <h1>Recepción no disponible</h1>
    <p><?= e($receiptError ?? 'El módulo no pudo cargarse.') ?></p>
    <a class="secondary-button" href="<?= e(sindesg_url()) ?>">← Volver al dashboard</a>
</section>
