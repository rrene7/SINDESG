<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/RrhhRepository.php');
$directory = file_get_contents($root . '/app/Services/RrhhEmployeeDirectory.php');
$index = file_get_contents($root . '/app/Views/people/index.php');
$show = file_get_contents($root . '/app/Views/people/show.php');
$receiptJs = file_get_contents($root . '/public/assets/js/receipts.js');

foreach (compact('repository', 'directory', 'index', 'show', 'receiptJs') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach ([$repository, $directory] as $content) {
    foreach ([
        "AS direction_name",
        "AS office_name",
        "ancestor.unit_type = 'oficina'",
        "ancestor.unit_type IN ('direccion_nacional', 'direccion_general', 'direccion')",
        "FIND_IN_SET",
        "ou.hierarchy_path",
    ] as $fragment) {
        if (!str_contains($content, $fragment)) {
            fwrite(STDERR, "[ERROR] Falta resolución jerárquica: {$fragment}" . PHP_EOL);
            exit(1);
        }
    }
}

foreach (['Dirección:', 'Oficina:', 'Dependencia actual:'] as $fragment) {
    if (!str_contains($index, $fragment)) {
        fwrite(STDERR, "[ERROR] Resultado de búsqueda no muestra {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['<dt>Dirección</dt>', '<dt>Oficina</dt>', '<dt>Dependencia actual</dt>'] as $fragment) {
    if (!str_contains($show, $fragment)) {
        fwrite(STDERR, "[ERROR] Ficha de funcionario no muestra {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (["'Dirección: ' + employee.direction_name", "'Oficina: ' + employee.office_name"] as $fragment) {
    if (!str_contains($receiptJs, $fragment)) {
        fwrite(STDERR, "[ERROR] Selector de recepción no muestra contexto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Dirección, oficina y dependencia actual validadas." . PHP_EOL;
