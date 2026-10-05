<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ReportRepository;
use App\Support\Database;
use InvalidArgumentException;
use Throwable;

final class ReportsController
{
    private ReportRepository $repository;

    public function __construct(private Database $database)
    {
        $this->repository = new ReportRepository($database->connection('sindesg'));
    }

    public function handle(string $action, array $query): array
    {
        try {
            $type = trim((string) ($query['type'] ?? 'stock'));
            if (!$this->repository->hasReport($type)) {
                $type = 'stock';
            }

            $filters = $this->normalizeFilters($query);
            $meta = $this->repository->reportMeta($type);
            $columns = $this->repository->columns($type);

            if ($action === 'excel') {
                $rows = $this->repository->run($type, $filters, 20000);
                $this->exportExcel($type, $meta, $columns, $rows, $filters);
            }

            if ($action === 'print') {
                $rows = $this->repository->run($type, $filters, 5000);

                return [
                    'view' => SINDESG_ROOT . '/app/Views/reports/print.php',
                    'title' => $meta['title'],
                    'data' => [
                        'reportType' => $type,
                        'reportMeta' => $meta,
                        'reportColumns' => $columns,
                        'reportRows' => $rows,
                        'reportFilters' => $filters,
                        'reportTotals' => $this->totals($rows),
                        'reportGeneratedAt' => date('d/m/Y H:i'),
                    ],
                ];
            }

            $rows = $this->repository->run($type, $filters, 500);

            return [
                'view' => SINDESG_ROOT . '/app/Views/reports/index.php',
                'title' => 'Reportes',
                'data' => [
                    'reportDefinitions' => $this->repository->reportDefinitions(),
                    'reportType' => $type,
                    'reportMeta' => $meta,
                    'reportColumns' => $columns,
                    'reportRows' => $rows,
                    'reportFilters' => $filters,
                    'reportFilterOptions' => $this->repository->filterOptions(),
                    'reportSummary' => $this->repository->summary(),
                    'reportTotals' => $this->totals($rows),
                    'employeeSearchUrl' => sindesg_url('public/api/employees.php'),
                    'reportScreenLimit' => 500,
                ],
            ];
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());

            return [
                'view' => SINDESG_ROOT . '/app/Views/reports/index.php',
                'title' => 'Reportes',
                'data' => [
                    'reportDefinitions' => $this->repository->reportDefinitions(),
                    'reportType' => 'stock',
                    'reportMeta' => $this->repository->reportMeta('stock'),
                    'reportColumns' => $this->repository->columns('stock'),
                    'reportRows' => [],
                    'reportFilters' => $this->normalizeFilters([]),
                    'reportFilterOptions' => $this->repository->filterOptions(),
                    'reportSummary' => $this->repository->summary(),
                    'reportTotals' => ['rows' => 0, 'quantity' => 0.0],
                    'employeeSearchUrl' => sindesg_url('public/api/employees.php'),
                    'reportScreenLimit' => 500,
                ],
            ];
        } catch (Throwable $exception) {
            return [
                'view' => SINDESG_ROOT . '/app/Views/reports/unavailable.php',
                'title' => 'Reportes',
                'data' => [
                    'reportError' => 'No fue posible generar el reporte. Verifique que las Fases 1 a 10 estén aplicadas en esta instalación.',
                ],
            ];
        }
    }

    private function normalizeFilters(array $query): array
    {
        $dateFrom = trim((string) ($query['date_from'] ?? ''));
        $dateTo = trim((string) ($query['date_to'] ?? ''));

        if ($dateFrom !== '' && !$this->validDate($dateFrom)) {
            $dateFrom = '';
        }
        if ($dateTo !== '' && !$this->validDate($dateTo)) {
            $dateTo = '';
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'warehouse_id' => max(0, (int) ($query['warehouse_id'] ?? 0)),
            'category_id' => max(0, (int) ($query['category_id'] ?? 0)),
            'product_id' => max(0, (int) ($query['product_id'] ?? 0)),
            'size_id' => max(0, (int) ($query['size_id'] ?? 0)),
            'sex' => trim((string) ($query['sex'] ?? '')),
            'status' => trim((string) ($query['status'] ?? '')),
            'direction' => strtoupper(trim((string) ($query['direction'] ?? ''))),
            'employee_id' => max(0, (int) ($query['employee_id'] ?? 0)),
            'employee_label' => trim((string) ($query['employee_label'] ?? '')),
            'threshold' => max(0.0, min((float) ($query['threshold'] ?? 10), 1000000.0)),
            'q' => trim((string) ($query['q'] ?? '')),
        ];
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function totals(array $rows): array
    {
        $quantityKeys = [
            'available_quantity',
            'quantity',
            'quantity_delivered',
            'quantity_received',
            'quantity_approved',
            'quantity_requested',
        ];

        $quantity = 0.0;
        foreach ($rows as $row) {
            foreach ($quantityKeys as $key) {
                if (array_key_exists($key, $row) && is_numeric($row[$key])) {
                    $quantity += (float) $row[$key];
                    break;
                }
            }
        }

        return [
            'rows' => count($rows),
            'quantity' => $quantity,
        ];
    }

    private function exportExcel(
        string $type,
        array $meta,
        array $columns,
        array $rows,
        array $filters
    ): never {
        $filename = 'SINDESG-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $type)
            . '-' . date('Ymd-His') . '.xls';

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        echo "\xEF\xBB\xBF";
        echo '<!doctype html><html><head><meta charset="utf-8"><style>';
        echo 'table{border-collapse:collapse;font-family:Arial,sans-serif;font-size:10pt}';
        echo 'th,td{border:1px solid #888;padding:5px;vertical-align:top}';
        echo 'th{background:#dceaf7;font-weight:bold}h1{font-family:Arial,sans-serif;font-size:16pt}';
        echo '</style></head><body>';
        echo '<h1>' . htmlspecialchars('SINDESG · ' . $meta['title'], ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '<p>Generado: ' . htmlspecialchars(date('d/m/Y H:i'), ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<table><thead><tr>';
        foreach ($columns as $label) {
            echo '<th>' . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            foreach (array_keys($columns) as $key) {
                echo '<td>' . $this->excelCell($row[$key] ?? '') . '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table></body></html>';
        exit;
    }

    private function excelCell(mixed $value): string
    {
        $text = (string) $value;
        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) {
            $text = "'" . $text;
        }

        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
