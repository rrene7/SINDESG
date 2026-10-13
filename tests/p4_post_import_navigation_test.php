<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$index = (string) file_get_contents($root . '/app/Views/requests/index.php');
$show = (string) file_get_contents($root . '/app/Views/requests/show.php');
$controller = (string) file_get_contents($root . '/app/Controllers/RequestsController.php');

foreach ([
    '$draftReady = null;',
    "(string) (\$candidate['status'] ?? '') === 'draft'",
    "(int) (\$candidate['item_count'] ?? 0) > 0",
    "request-send-panel",
    'No vuelva a importar el Excel.',
    'Revisar borrador y enviar al Central',
] as $required) {
    if (!str_contains($index,$required)) {
        throw new RuntimeException('Falta aviso en listado de solicitudes: ' . $required);
    }
}
foreach ([
    "\$serviceRequest['status'] === 'draft' && \$requestItems !== []",
    'renglones cargados en el borrador',
    'id="request-send-panel"',
    'href="#request-send-panel"',
    "action=submit",
    'Cargar al sistema',
    'no necesita',
] as $required) {
    if (!str_contains($show,$required)) {
        throw new RuntimeException('Falta siguiente paso visible en solicitud: ' . $required);
    }
}
if (str_contains($show,'Después de resolver las incidencias, seleccione el mismo archivo')
    || !str_contains($controller, "if (\$action === 'import-from-nominal-preview')")
    || !str_contains($controller, "if (\$action === 'validate-nominal-selected')")) {
    throw new RuntimeException('La navegación P4 volvió a exigir subir el Excel o perdió la validación masiva.');
}
// La página dirige a una revisión humana, nunca envía o mueve stock
// automáticamente al visitar el borrador.
if (!str_contains($show, '<form method="post" action="<?= e(sindesg_url(\'?module=requests&action=submit\')) ?>">')) {
    throw new RuntimeException('El envío al Central debe continuar siendo una acción explícita POST.');
}
echo "[OK] Solicitud P4 cargada: siguiente paso a borrador, revisión y envío explícito, sin nueva importación.\n";
