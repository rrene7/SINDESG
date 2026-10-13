<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

/**
 * Decisiones manuales por fila de Excel y SHA-256 del archivo ORIGINAL.
 * Cada decisión es una fila nueva. Una resolución jamás modifica RRHH
 * ni autoriza por sí sola recepción, reserva, despacho o entrega.
 */
final class P4NominalValidationRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<int,array<string,mixed>> Última decisión por fila. */
    public function byFile(int $requestId, int $productId, string $hash): array
    {
        if ($requestId <= 0 || $productId <= 0 || !preg_match('/^[a-f0-9]{64}$/', $hash)) {
            throw new RuntimeException('Referencia de conciliación no válida.');
        }
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT v.*
            FROM p4_nominal_validation_decisions v
            WHERE v.request_id = :request AND v.product_id = :product
              AND v.source_sha256 = :hash
            ORDER BY v.id DESC
        SQL);
        $statement->execute(['request' => $requestId, 'product' => $productId, 'hash' => $hash]);
        $found = [];
        foreach ($statement->fetchAll() as $row) {
            $line = (int) $row['excel_row'];
            if (!isset($found[$line])) {
                $found[$line] = $row;
            }
        }
        return $found;
    }

    /**
     * Guarda todas las decisiones verificadas como una unidad atómica.
     * Si una falla, no queda ninguna aprobación parcial ni movimientos.
     *
     * @param array<int,array<string,mixed>> $rows fila Excel => evidencia
     * @return array<int,int> fila Excel => id de evidencia
     */
    public function recordBatch(array $rows): array
    {
        if ($rows === [] || count($rows) > 100 || $this->pdo->inTransaction()) {
            throw new RuntimeException('La validación colectiva P4 es inválida.');
        }
        $this->pdo->beginTransaction();
        try {
            $ids = [];
            foreach ($rows as $excelRow => $data) {
                if ((int) $excelRow <= 0
                    || (int) ($data['excel_row'] ?? 0) !== (int) $excelRow) {
                    throw new RuntimeException('Fila de validación incongruente.');
                }
                $ids[(int) $excelRow] = $this->record($data);
            }
            $this->pdo->commit();
            return $ids;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    /** @param array<string,mixed> $data */
    public function record(array $data): int
    {
        $required = [
            'request_id','product_id','source_sha256','requisition','excel_row',
            'excel_position','excel_name','excel_size','rrhh_employee_id',
            'rrhh_document_snapshot','rrhh_position_snapshot','rrhh_name_snapshot',
            'justification','validated_by_user_id','validated_by_label_snapshot',
        ];
        foreach ($required as $key) {
            if (!isset($data[$key]) || trim((string) $data[$key]) === '') {
                throw new RuntimeException('Falta el dato auditable: ' . $key);
            }
        }
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO p4_nominal_validation_decisions
            (request_id, product_id, source_sha256, requisition, excel_row,
             excel_position, excel_name, excel_size, rrhh_employee_id,
             rrhh_document_snapshot, rrhh_position_snapshot, rrhh_name_snapshot,
             justification, validated_by_user_id, validated_by_label_snapshot)
            VALUES (:request_id, :product_id, :source_sha256, :requisition, :excel_row,
                    :excel_position, :excel_name, :excel_size, :rrhh_employee_id,
                    :rrhh_document_snapshot, :rrhh_position_snapshot, :rrhh_name_snapshot,
                    :justification, :validated_by_user_id, :validated_by_label_snapshot)
        SQL);
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }
}
