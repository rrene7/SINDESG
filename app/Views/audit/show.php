<?php
declare(strict_types=1);

$decode = static function (?string $json): mixed {
    if ($json === null || trim($json) === '') {
        return null;
    }
    return json_decode($json, true);
};
$details = $decode($auditEvent['details_json']);
$before = $decode($auditEvent['before_json']);
$after = $decode($auditEvent['after_json']);
?>
<section class="security-hero compact">
    <div>
        <span class="eyebrow">AUDITORÍA #<?= e($auditEvent['id']) ?></span>
        <h1><?= e($auditEvent['action_code']) ?></h1>
        <p><?= e($auditEvent['module_code']) ?> · <?= e(date('d/m/Y H:i:s', strtotime((string) $auditEvent['created_at']))) ?></p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=audit')) ?>">← Auditoría</a>
</section>

<section class="security-panel">
    <dl class="security-dl">
        <div><dt>Usuario</dt><dd><?= e($auditEvent['user_label_snapshot'] ?: 'Sistema') ?></dd></div>
        <div><dt>Cuenta</dt><dd><?= e($auditEvent['username'] ?: '—') ?></dd></div>
        <div><dt>Módulo</dt><dd><?= e($auditEvent['module_code']) ?></dd></div>
        <div><dt>Acción</dt><dd><?= e($auditEvent['action_code']) ?></dd></div>
        <div><dt>Registro</dt><dd><?= e(($auditEvent['record_type'] ?: '—') . ($auditEvent['record_id'] ? ' #' . $auditEvent['record_id'] : '')) ?></dd></div>
        <div><dt>IP</dt><dd><?= e($auditEvent['ip_address'] ?: '—') ?></dd></div>
        <div><dt>User-Agent</dt><dd><?= e($auditEvent['user_agent'] ?: '—') ?></dd></div>
    </dl>
</section>

<?php foreach ([['Detalle', $details], ['Valor anterior', $before], ['Valor nuevo', $after]] as [$title, $data]): ?>
    <?php if ($data !== null): ?>
        <section class="security-panel">
            <div class="security-section-head"><div><span class="eyebrow">AUDITORÍA</span><h2><?= e($title) ?></h2></div></div>
            <pre class="audit-json"><?= e(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
        </section>
    <?php endif; ?>
<?php endforeach; ?>
