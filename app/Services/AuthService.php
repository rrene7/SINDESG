<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditRepository;
use App\Repositories\SecurityRepository;
use App\Support\Database;
use InvalidArgumentException;
use RuntimeException;

final class AuthService
{
    private ?SecurityRepository $security = null;
    private ?AuditRepository $audit = null;
    private ?array $cachedUser = null;
    private bool $userLoaded = false;

    public function __construct(private Database $database)
    {
    }

    public function hasUsers(): bool
    {
        return $this->security()->userCount() > 0;
    }

    public function currentUser(): ?array
    {
        if (PHP_SAPI === 'cli') {
            return null;
        }

        if ($this->userLoaded) {
            return $this->cachedUser;
        }

        $this->userLoaded = true;
        $userId = (int) ($_SESSION['auth_user_id'] ?? 0);

        if ($userId <= 0) {
            return null;
        }

        $idleMinutes = max(15, min(
            (int) $this->security()->setting('security.session_idle_minutes', '120'),
            720
        ));
        $lastActivity = (int) ($_SESSION['auth_last_activity'] ?? 0);

        if ($lastActivity > 0 && time() - $lastActivity > ($idleMinutes * 60)) {
            $this->clearSession();
            return null;
        }

        $user = $this->security()->findUser($userId);
        if ($user === null || $user['status'] !== 'active') {
            $this->clearSession();
            return null;
        }

        $_SESSION['auth_last_activity'] = time();

        $user['permissions'] = $this->security()->permissionCodesForUser($userId);
        $user['roles'] = $this->security()->roleNamesForUser($userId);
        $user['role_codes'] = $this->security()->roleCodesForUser($userId);
        $user['display_name'] = $user['display_name_snapshot'];

        $this->cachedUser = $user;
        return $user;
    }

    public function can(string $permission): bool
    {
        $user = $this->currentUser();
        if ($user === null) {
            return false;
        }

        return in_array($permission, $user['permissions'] ?? [], true);
    }

    public function login(string $username, string $password): void
    {
        $user = $this->security()->findByUsername($username);

        if (
            $user === null
            || $user['status'] !== 'active'
            || !password_verify($password, (string) $user['password_hash'])
        ) {
            $this->audit()->log([
                'user_id' => $user['id'] ?? null,
                'user_label_snapshot' => $user['display_name_snapshot'] ?? $username,
                'module_code' => 'auth',
                'action_code' => 'login_failed',
                'details' => ['username' => mb_strtolower(trim($username))],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);

            throw new InvalidArgumentException('Usuario o contraseña incorrectos.');
        }

        session_regenerate_id(true);
        $_SESSION['auth_user_id'] = (int) $user['id'];
        $_SESSION['auth_last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $this->security()->markLogin((int) $user['id']);
        $this->userLoaded = false;
        $this->cachedUser = null;

        $this->audit()->log([
            'user_id' => (int) $user['id'],
            'user_label_snapshot' => $user['display_name_snapshot'],
            'module_code' => 'auth',
            'action_code' => 'login',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }

    public function logout(): void
    {
        $user = $this->currentUser();

        if ($user !== null) {
            $this->audit()->log([
                'user_id' => (int) $user['id'],
                'user_label_snapshot' => $user['display_name'],
                'module_code' => 'auth',
                'action_code' => 'logout',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        }

        $this->clearSession();
    }

    public function setupFirstAdmin(
        array $employee,
        string $username,
        string $password,
        string $confirmation
    ): int {
        if ($this->hasUsers()) {
            throw new RuntimeException('La configuración inicial ya fue completada.');
        }

        $username = $this->validateUsername($username);
        $this->validatePassword($password, $confirmation);

        $userId = $this->security()->createFirstAdmin(
            $employee,
            $username,
            password_hash($password, PASSWORD_DEFAULT)
        );

        $this->audit()->log([
            'user_id' => $userId,
            'user_label_snapshot' => $employee['display_name'],
            'module_code' => 'admin',
            'action_code' => 'first_admin_created',
            'record_type' => 'security_user',
            'record_id' => (string) $userId,
            'after' => [
                'username' => $username,
                'employee_id' => $employee['employee_id'],
                'roles' => ['ADMIN'],
            ],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        return $userId;
    }

    public function validatePassword(string $password, string $confirmation): void
    {
        $minimum = max(10, min(
            (int) $this->security()->setting('security.password_min_length', '12'),
            64
        ));

        if (strlen($password) < $minimum) {
            throw new InvalidArgumentException(
                'La contraseña debe tener al menos ' . $minimum . ' caracteres.'
            );
        }

        if (!hash_equals($password, $confirmation)) {
            throw new InvalidArgumentException('La confirmación de contraseña no coincide.');
        }
    }

    public function validateUsername(string $username): string
    {
        $username = mb_strtolower(trim($username));

        if (!preg_match('/^[a-z0-9._-]{4,80}$/', $username)) {
            throw new InvalidArgumentException(
                'El usuario debe tener entre 4 y 80 caracteres y solo puede usar letras minúsculas, números, punto, guion o guion bajo.'
            );
        }

        return $username;
    }

    public function securityRepository(): SecurityRepository
    {
        return $this->security();
    }

    public function auditRepository(): AuditRepository
    {
        return $this->audit();
    }

    public function registerRequestAudit(string $module, string $action, array $payload): void
    {
        $user = $this->currentUser();
        if ($user === null) {
            return;
        }

        $sanitized = $this->sanitizePayload($payload);
        $recordId = $this->guessRecordId($payload);

        register_shutdown_function(function () use ($user, $module, $action, $sanitized, $recordId): void {
            try {
                $this->audit()->log([
                    'user_id' => (int) $user['id'],
                    'user_label_snapshot' => $user['display_name'],
                    'module_code' => $module,
                    'action_code' => 'POST:' . $action,
                    'record_type' => $module,
                    'record_id' => $recordId,
                    'details' => [
                        'payload' => $sanitized,
                        'http_status' => http_response_code(),
                    ],
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ]);
            } catch (\Throwable $exception) {
                // La auditoría nunca debe romper la operación principal.
            }
        });
    }

    private function sanitizePayload(array $payload): array
    {
        $sensitive = [
            '_token',
            'password',
            'password_confirmation',
            'new_password',
            'new_password_confirmation',
            'current_password',
        ];

        $clean = [];
        foreach ($payload as $key => $value) {
            if (in_array((string) $key, $sensitive, true)) {
                continue;
            }

            if (is_array($value)) {
                $clean[(string) $key] = array_slice(
                    array_values(array_map(
                        static fn ($item): string => mb_substr((string) $item, 0, 160),
                        $value
                    )),
                    0,
                    50
                );
                continue;
            }

            $clean[(string) $key] = mb_substr((string) $value, 0, 500);
        }

        return $clean;
    }

    private function guessRecordId(array $payload): ?string
    {
        foreach ([
            'user_id',
            'adjustment_id',
            'delivery_id',
            'distribution_id',
            'request_id',
            'warehouse_id',
            'production_id',
            'receipt_id',
            'product_id',
            'category_id',
            'id',
        ] as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key]) && trim((string) $payload[$key]) !== '') {
                return mb_substr((string) $payload[$key], 0, 80);
            }
        }

        return null;
    }

    private function security(): SecurityRepository
    {
        if ($this->security === null) {
            $this->security = new SecurityRepository(
                $this->database->connection('sindesg')
            );
        }

        return $this->security;
    }

    private function audit(): AuditRepository
    {
        if ($this->audit === null) {
            $this->audit = new AuditRepository(
                $this->database->connection('sindesg')
            );
        }

        return $this->audit;
    }

    private function clearSession(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $this->userLoaded = true;
        $this->cachedUser = null;
    }
}
