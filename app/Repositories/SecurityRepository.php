<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class SecurityRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function userCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM security_users')->fetchColumn();
    }

    public function findByUsername(string $username): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM security_users WHERE username = :username LIMIT 1'
        );
        $statement->execute(['username' => mb_strtolower(trim($username))]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function findUser(int $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM security_users WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function listUsers(): array
    {
        return $this->pdo->query(<<<'SQL'
            SELECT
                u.*,
                GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ' · ') AS role_names,
                GROUP_CONCAT(r.code ORDER BY r.code SEPARATOR ',') AS role_codes
            FROM security_users u
            LEFT JOIN security_user_roles ur ON ur.user_id = u.id
            LEFT JOIN security_roles r ON r.id = ur.role_id
            GROUP BY u.id
            ORDER BY
                CASE WHEN u.status = 'active' THEN 0 ELSE 1 END,
                u.display_name_snapshot,
                u.username
        SQL)->fetchAll();
    }

    public function roles(): array
    {
        return $this->pdo->query(
            'SELECT * FROM security_roles ORDER BY CASE WHEN code = "ADMIN" THEN 0 ELSE 1 END, name'
        )->fetchAll();
    }

    public function role(int $roleId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM security_roles WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $roleId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function permissions(): array
    {
        return $this->pdo->query(
            'SELECT * FROM security_permissions ORDER BY module_code, name'
        )->fetchAll();
    }

    public function roleCodesForUser(int $userId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT r.code
            FROM security_user_roles ur
            INNER JOIN security_roles r ON r.id = ur.role_id
            WHERE ur.user_id = :user_id
            ORDER BY r.name
        SQL);
        $statement->execute(['user_id' => $userId]);

        return array_map(
            static fn (array $row): string => (string) $row['code'],
            $statement->fetchAll()
        );
    }

    public function roleNamesForUser(int $userId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT r.name
            FROM security_user_roles ur
            INNER JOIN security_roles r ON r.id = ur.role_id
            WHERE ur.user_id = :user_id
            ORDER BY r.name
        SQL);
        $statement->execute(['user_id' => $userId]);

        return array_map(
            static fn (array $row): string => (string) $row['name'],
            $statement->fetchAll()
        );
    }

    public function permissionCodesForUser(int $userId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT DISTINCT p.code
            FROM security_user_roles ur
            INNER JOIN security_role_permissions rp ON rp.role_id = ur.role_id
            INNER JOIN security_permissions p ON p.id = rp.permission_id
            WHERE ur.user_id = :user_id
            ORDER BY p.code
        SQL);
        $statement->execute(['user_id' => $userId]);

        return array_map(
            static fn (array $row): string => (string) $row['code'],
            $statement->fetchAll()
        );
    }

    public function permissionCodesForRole(int $roleId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT p.code
            FROM security_role_permissions rp
            INNER JOIN security_permissions p ON p.id = rp.permission_id
            WHERE rp.role_id = :role_id
            ORDER BY p.code
        SQL);
        $statement->execute(['role_id' => $roleId]);

        return array_map(
            static fn (array $row): string => (string) $row['code'],
            $statement->fetchAll()
        );
    }

    public function createFirstAdmin(array $employee, string $username, string $passwordHash): int
    {
        $this->pdo->beginTransaction();

        try {
            if ($this->userCount() > 0) {
                throw new RuntimeException('La configuración inicial ya fue completada.');
            }

            $userId = $this->insertUser($employee, $username, $passwordHash, 'active');

            $roleId = (int) $this->pdo->query(
                'SELECT id FROM security_roles WHERE code = "ADMIN" LIMIT 1'
            )->fetchColumn();

            if ($roleId <= 0) {
                throw new RuntimeException('No existe el rol Administrador SINDESG.');
            }

            $this->assignRoles($userId, [$roleId]);

            $this->pdo->commit();
            return $userId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function createUser(
        array $employee,
        string $username,
        string $passwordHash,
        array $roleIds
    ): int {
        $this->pdo->beginTransaction();

        try {
            $userId = $this->insertUser($employee, $username, $passwordHash, 'active');
            $this->assignRoles($userId, $roleIds);
            $this->pdo->commit();

            return $userId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function updateUser(int $userId, string $status, array $roleIds): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->pdo->prepare(
                'UPDATE security_users SET status = :status WHERE id = :id'
            )->execute(['status' => $status, 'id' => $userId]);

            $this->pdo->prepare(
                'DELETE FROM security_user_roles WHERE user_id = :user_id'
            )->execute(['user_id' => $userId]);

            $this->assignRoles($userId, $roleIds);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function resetPassword(int $userId, string $passwordHash): void
    {
        $this->pdo->prepare(
            'UPDATE security_users SET password_hash = :password_hash WHERE id = :id'
        )->execute([
            'password_hash' => $passwordHash,
            'id' => $userId,
        ]);
    }

    public function markLogin(int $userId): void
    {
        $this->pdo->prepare(
            'UPDATE security_users SET last_login_at = NOW() WHERE id = :id'
        )->execute(['id' => $userId]);
    }

    public function setting(string $key, string $default = ''): string
    {
        $statement = $this->pdo->prepare(
            'SELECT setting_value FROM system_settings WHERE setting_key = :setting_key LIMIT 1'
        );
        $statement->execute(['setting_key' => $key]);
        $value = $statement->fetchColumn();

        return $value === false || $value === null ? $default : (string) $value;
    }

    public function securitySettings(): array
    {
        return [
            'security.session_idle_minutes' => $this->setting('security.session_idle_minutes', '120'),
            'security.password_min_length' => $this->setting('security.password_min_length', '12'),
        ];
    }

    public function setSettings(array $settings): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES (:setting_key, :setting_value)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        SQL);

        foreach ($settings as $key => $value) {
            $statement->execute([
                'setting_key' => $key,
                'setting_value' => (string) $value,
            ]);
        }
    }

    public function updateRolePermissions(int $roleId, array $permissionCodes): void
    {
        $role = $this->role($roleId);
        if ($role === null) {
            throw new RuntimeException('El rol seleccionado no existe.');
        }

        if ($role['code'] === 'ADMIN') {
            throw new RuntimeException('El rol Administrador conserva todos los permisos.');
        }

        $this->pdo->beginTransaction();

        try {
            $this->pdo->prepare(
                'DELETE FROM security_role_permissions WHERE role_id = :role_id'
            )->execute(['role_id' => $roleId]);

            if ($permissionCodes !== []) {
                $select = $this->pdo->prepare(
                    'SELECT id FROM security_permissions WHERE code = :code LIMIT 1'
                );
                $insert = $this->pdo->prepare(
                    'INSERT IGNORE INTO security_role_permissions (role_id, permission_id)
                     VALUES (:role_id, :permission_id)'
                );

                foreach (array_values(array_unique($permissionCodes)) as $code) {
                    $select->execute(['code' => $code]);
                    $permissionId = (int) ($select->fetchColumn() ?: 0);

                    if ($permissionId > 0) {
                        $insert->execute([
                            'role_id' => $roleId,
                            'permission_id' => $permissionId,
                        ]);
                    }
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function insertUser(
        array $employee,
        string $username,
        string $passwordHash,
        string $status
    ): int {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO security_users
                (
                    employee_id,
                    username,
                    password_hash,
                    display_name_snapshot,
                    document_snapshot,
                    position_snapshot,
                    rank_snapshot,
                    direction_snapshot,
                    office_snapshot,
                    status
                )
            VALUES
                (
                    :employee_id,
                    :username,
                    :password_hash,
                    :display_name_snapshot,
                    :document_snapshot,
                    :position_snapshot,
                    :rank_snapshot,
                    :direction_snapshot,
                    :office_snapshot,
                    :status
                )
        SQL);

        $statement->execute([
            'employee_id' => $employee['employee_id'],
            'username' => mb_strtolower(trim($username)),
            'password_hash' => $passwordHash,
            'display_name_snapshot' => $employee['display_name'],
            'document_snapshot' => $employee['document_number'],
            'position_snapshot' => $employee['position_number'],
            'rank_snapshot' => $employee['rank_name'],
            'direction_snapshot' => $employee['direction_name'],
            'office_snapshot' => $employee['office_name'],
            'status' => $status,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function assignRoles(int $userId, array $roleIds): void
    {
        $roleIds = array_values(array_unique(array_filter(
            array_map('intval', $roleIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($roleIds === []) {
            throw new RuntimeException('El usuario debe tener al menos un rol.');
        }

        $exists = $this->pdo->prepare(
            'SELECT COUNT(*) FROM security_roles WHERE id = :id'
        );
        $insert = $this->pdo->prepare(
            'INSERT IGNORE INTO security_user_roles (user_id, role_id)
             VALUES (:user_id, :role_id)'
        );

        foreach ($roleIds as $roleId) {
            $exists->execute(['id' => $roleId]);
            if ((int) $exists->fetchColumn() !== 1) {
                throw new RuntimeException('Uno de los roles seleccionados no existe.');
            }

            $insert->execute([
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        }
    }
}
