<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AuditRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function log(array $event): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO audit_logs
                (
                    user_id,
                    user_label_snapshot,
                    module_code,
                    action_code,
                    record_type,
                    record_id,
                    details_json,
                    before_json,
                    after_json,
                    ip_address,
                    user_agent
                )
            VALUES
                (
                    :user_id,
                    :user_label_snapshot,
                    :module_code,
                    :action_code,
                    :record_type,
                    :record_id,
                    :details_json,
                    :before_json,
                    :after_json,
                    :ip_address,
                    :user_agent
                )
        SQL);

        $statement->execute([
            'user_id' => $event['user_id'] ?? null,
            'user_label_snapshot' => $event['user_label_snapshot'] ?? null,
            'module_code' => $event['module_code'] ?? 'system',
            'action_code' => $event['action_code'] ?? 'event',
            'record_type' => $event['record_type'] ?? null,
            'record_id' => $event['record_id'] ?? null,
            'details_json' => $this->encode($event['details'] ?? null),
            'before_json' => $this->encode($event['before'] ?? null),
            'after_json' => $this->encode($event['after'] ?? null),
            'ip_address' => $event['ip_address'] ?? null,
            'user_agent' => mb_substr((string) ($event['user_agent'] ?? ''), 0, 255),
        ]);
    }

    public function list(array $filters, int $limit = 300): array
    {
        $limit = max(1, min($limit, 1000));
        $where = ['1=1'];
        $params = [];

        $from = trim((string) ($filters['date_from'] ?? ''));
        if ($from !== '') {
            $where[] = 'a.created_at >= :date_from';
            $params['date_from'] = $from . ' 00:00:00';
        }

        $to = trim((string) ($filters['date_to'] ?? ''));
        if ($to !== '') {
            $where[] = 'a.created_at <= :date_to';
            $params['date_to'] = $to . ' 23:59:59';
        }

        $userId = (int) ($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $where[] = 'a.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $module = trim((string) ($filters['module'] ?? ''));
        if ($module !== '') {
            $where[] = 'a.module_code = :module';
            $params['module'] = $module;
        }

        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            $where[] = 'a.action_code LIKE :action';
            $params['action'] = '%' . $action . '%';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $term = '%' . $q . '%';
            $where[] = '(COALESCE(a.user_label_snapshot, "") LIKE :q1 OR COALESCE(a.record_id, "") LIKE :q2 OR COALESCE(a.details_json, "") LIKE :q3)';
            $params['q1'] = $term;
            $params['q2'] = $term;
            $params['q3'] = $term;
        }

        $sql = '
            SELECT
                a.*,
                u.username
            FROM audit_logs a
            LEFT JOIN security_users u ON u.id = a.user_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY a.created_at DESC, a.id DESC
            LIMIT ' . $limit;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function find(int $auditId): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT a.*, u.username
            FROM audit_logs a
            LEFT JOIN security_users u ON u.id = a.user_id
            WHERE a.id = :id
            LIMIT 1
        SQL);
        $statement->execute(['id' => $auditId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function users(): array
    {
        return $this->pdo->query(
            'SELECT id, username, display_name_snapshot
             FROM security_users
             ORDER BY display_name_snapshot'
        )->fetchAll();
    }

    public function modules(): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['module_code'],
            $this->pdo->query(
                'SELECT DISTINCT module_code FROM audit_logs ORDER BY module_code'
            )->fetchAll()
        );
    }

    private function encode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        ) ?: null;
    }
}
