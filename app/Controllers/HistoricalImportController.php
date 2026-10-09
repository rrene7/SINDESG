<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\HistoricalImportRepository;
use App\Services\HistoricalImportService;
use App\Services\SpreadsheetReader;
use App\Support\Database;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class HistoricalImportController
{
    private HistoricalImportRepository $repository;
    private HistoricalImportService $service;

    public function __construct(private Database $database)
    {
        $this->repository = new HistoricalImportRepository(
            $database->connection('sindesg')
        );

        $auth = $GLOBALS['authService'];

        $this->service = new HistoricalImportService(
            $this->repository,
            new SpreadsheetReader(),
            $auth->auditRepository()
        );
    }

    public function handle(
        string $action,
        array $query,
        array $post,
        array $files,
        string $method
    ): array {
        try {
            if (strtoupper($method) === 'POST') {
                if (!csrf_valid(isset($post['_token']) ? (string) $post['_token'] : null)) {
                    throw new InvalidArgumentException(
                        'La sesión del formulario venció. Intente nuevamente.'
                    );
                }

                return $this->handlePost($action, $post, $files);
            }

            return $this->handleGet($action, $query);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            flash('error', $exception->getMessage());

            $batchId = (int) ($post['batch_id'] ?? $query['id'] ?? 0);
            if ($batchId > 0) {
                $this->redirect('?module=migration&action=show&id=' . $batchId);
            }

            $this->redirect('?module=migration');
        } catch (Throwable $exception) {
            return [
                'view' => SINDESG_ROOT . '/app/Views/migration/unavailable.php',
                'title' => 'Migración histórica',
                'data' => [
                    'migrationError' => 'El centro de históricos no está disponible en esta instalación. Aplique 016_historical_imports.sql y vuelva a intentarlo.',
                ],
            ];
        }
    }

    private function handleGet(string $action, array $query): array
    {
        if ($action === 'show') {
            $batchId = (int) ($query['id'] ?? 0);
            $batch = $this->repository->batch($batchId);

            if ($batch === null) {
                throw new InvalidArgumentException('El lote histórico solicitado no existe.');
            }

            $sheetId = (int) ($query['sheet_id'] ?? 0);
            $status = trim((string) ($query['status'] ?? ''));

            return [
                'view' => SINDESG_ROOT . '/app/Views/migration/show.php',
                'title' => (string) $batch['batch_code'],
                'data' => [
                    'historicalBatch' => $batch,
                    'historicalSheets' => $this->repository->sheets($batchId),
                    'historicalRows' => $this->repository->rows(
                        $batchId,
                        $sheetId > 0 ? $sheetId : null,
                        $status,
                        500
                    ),
                    'selectedSheetId' => $sheetId,
                    'selectedRowStatus' => $status,
                    'snapshotCount' => $this->repository->countSnapshotsForBatch($batchId),
                ],
            ];
        }

        return [
            'view' => SINDESG_ROOT . '/app/Views/migration/index.php',
            'title' => 'Migración histórica',
            'data' => [
                'historicalBatches' => $this->repository->listBatches(100),
            ],
        ];
    }

    private function handlePost(
        string $action,
        array $post,
        array $files
    ): array {
        $actor = sindesg_user();
        if ($actor === null) {
            throw new RuntimeException('La sesión de usuario ya no está disponible.');
        }

        if ($action === 'upload') {
            $batchId = $this->service->upload(
                $files['historical_file'] ?? [],
                $post['notes'] ?? null,
                $actor
            );

            flash(
                'success',
                'Archivo cargado a staging. El inventario operativo no fue modificado.'
            );
            $this->redirect('?module=migration&action=show&id=' . $batchId);
        }

        if ($action === 'reprocess') {
            $batchId = (int) ($post['batch_id'] ?? 0);
            $result = $this->service->reprocess($batchId, $actor);

            flash(
                'success',
                sprintf(
                    'Lote reprocesado desde el archivo original: %d hojas reclasificadas. El inventario operativo no fue modificado.',
                    (int) $result['sheet_count']
                )
            );
            $this->redirect('?module=migration&action=show&id=' . $batchId);
        }

        if ($action === 'archive-ready') {
            $batchId = (int) ($post['batch_id'] ?? 0);
            $result = $this->service->archiveReady($batchId, $actor);

            flash(
                'success',
                sprintf(
                    'Se archivaron %d filas listas. No se generaron movimientos de inventario.',
                    (int) $result['imported']
                )
            );
            $this->redirect('?module=migration&action=show&id=' . $batchId);
        }

        if ($action === 'cancel') {
            $batchId = (int) ($post['batch_id'] ?? 0);
            $this->service->cancel($batchId, $actor);
            flash('success', 'Lote cancelado sin modificar inventario.');
            $this->redirect('?module=migration&action=show&id=' . $batchId);
        }

        throw new InvalidArgumentException('La operación histórica solicitada no existe.');
    }

    private function redirect(string $path): never
    {
        header('Location: ' . sindesg_url($path), true, 303);
        exit;
    }
}
