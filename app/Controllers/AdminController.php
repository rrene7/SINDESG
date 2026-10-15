<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\RrhhEmployeeDirectory;
use App\Support\Database;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class AdminController
{
    private RrhhEmployeeDirectory $employees;

    public function __construct(
        private Database $database,
        private AuthService $auth
    ) {
        $this->employees = new RrhhEmployeeDirectory($database);
    }

    public function handle(
        string $action,
        array $query,
        array $post,
        string $method
    ): array {
        if (!$this->auth->can('admin.manage')) {
            throw new RuntimeException('No tiene permiso para administrar SINDESG.');
        }

        try {
            if (strtoupper($method) === 'POST') {
                if (!csrf_valid($post['_token'] ?? null)) {
                    throw new InvalidArgumentException(
                        'La sesión del formulario venció. Intente nuevamente.'
                    );
                }

                return $this->handlePost($action, $post);
            }

            return $this->handleGet($action, $query);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            flash('error', $exception->getMessage());
            $userId = (int) ($post['user_id'] ?? $query['id'] ?? 0);

            if ($userId > 0) {
                $this->redirect('?module=admin&action=edit&id=' . $userId);
            }

            $this->redirect('?module=admin');
        }
    }

    private function handleGet(string $action, array $query): array
    {
        $security = $this->auth->securityRepository();

        if ($action === 'new') {
            return [
                'view' => SINDESG_ROOT . '/app/Views/admin/user-form.php',
                'title' => 'Nuevo usuario',
                'data' => [
                    'securityUser' => null,
                    'securityRoles' => $security->roles(),
                    'selectedRoleCodes' => [],
                    'employeeSearchUrl' => sindesg_url('public/api/employees.php'),
                ],
            ];
        }

        if ($action === 'edit') {
            $user = $security->findUser((int) ($query['id'] ?? 0));
            if ($user === null) {
                throw new InvalidArgumentException('El usuario solicitado no existe.');
            }

            return [
                'view' => SINDESG_ROOT . '/app/Views/admin/user-form.php',
                'title' => 'Editar usuario',
                'data' => [
                    'securityUser' => $user,
                    'securityRoles' => $security->roles(),
                    'selectedRoleCodes' => $security->roleCodesForUser((int) $user['id']),
                    'p4WarehouseOptions' => $security->activeP4WarehouseOptions(),
                    'selectedP4WarehouseIds' => $security->p4WarehouseIdsForUser((int) $user['id']),
                    'employeeSearchUrl' => sindesg_url('public/api/employees.php'),
                ],
            ];
        }

        if ($action === 'roles') {
            $roles = $security->roles();
            foreach ($roles as &$role) {
                $role['permission_codes'] = $security->permissionCodesForRole((int) $role['id']);
            }
            unset($role);

            return [
                'view' => SINDESG_ROOT . '/app/Views/admin/roles.php',
                'title' => 'Roles y permisos',
                'data' => [
                    'securityRoles' => $roles,
                    'securityPermissions' => $security->permissions(),
                ],
            ];
        }

        return [
            'view' => SINDESG_ROOT . '/app/Views/admin/index.php',
            'title' => 'Administración',
            'data' => [
                'securityUsers' => $security->listUsers(),
                'securitySettings' => $security->securitySettings(),
            ],
        ];
    }

    private function handlePost(string $action, array $post): array
    {
        $security = $this->auth->securityRepository();
        $audit = $this->auth->auditRepository();
        $actor = $this->auth->currentUser();

        if ($action === 'save-user') {
            $employee = $this->employees->findEligibleEmployee(
                (int) ($post['employee_id'] ?? 0)
            );
            if ($employee === null) {
                throw new InvalidArgumentException('Seleccione un funcionario vigente de RRHH2029.');
            }

            $username = $this->auth->validateUsername((string) ($post['username'] ?? ''));
            $password = (string) ($post['password'] ?? '');
            $confirmation = (string) ($post['password_confirmation'] ?? '');
            $this->auth->validatePassword($password, $confirmation);

            $roleIds = $post['role_ids'] ?? [];
            $userId = $security->createUser(
                $employee,
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                is_array($roleIds) ? $roleIds : []
            );

            $audit->log([
                'user_id' => $actor['id'],
                'user_label_snapshot' => $actor['display_name'],
                'module_code' => 'admin',
                'action_code' => 'user_created',
                'record_type' => 'security_user',
                'record_id' => (string) $userId,
                'after' => [
                    'username' => $username,
                    'employee_id' => $employee['employee_id'],
                    'role_ids' => array_values((array) $roleIds),
                ],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);

            flash('success', 'Usuario creado correctamente.');
            $this->redirect('?module=admin');
        }

        if ($action === 'assign-p4-warehouses') {
            $userId = (int) ($post['user_id'] ?? 0);
            $ids = $post['p4_warehouse_ids'] ?? [];
            if (!is_array($ids)) {
                throw new InvalidArgumentException('Seleccione almacenes P4 válidos.');
            }
            $security->assignP4WarehousesWithAudit($userId, $ids, (array) $actor);
            flash('success', 'Almacenes P4 autorizados con trazabilidad.');
            $this->redirect('?module=admin&action=edit&id=' . $userId);
        }

        if ($action === 'update-user') {
            $userId = (int) ($post['user_id'] ?? 0);
            $before = $security->findUser($userId);
            if ($before === null) {
                throw new InvalidArgumentException('El usuario solicitado no existe.');
            }

            $status = (string) ($post['status'] ?? 'active');
            if (!in_array($status, ['active', 'disabled'], true)) {
                throw new InvalidArgumentException('Estado de usuario no válido.');
            }

            $roleIds = is_array($post['role_ids'] ?? null) ? $post['role_ids'] : [];

            if ($actor !== null && (int) $actor['id'] === $userId) {
                if ($status !== 'active') {
                    throw new InvalidArgumentException('No puede desactivar su propia cuenta.');
                }

                $adminRoleId = 0;
                foreach ($security->roles() as $role) {
                    if ($role['code'] === 'ADMIN') {
                        $adminRoleId = (int) $role['id'];
                        break;
                    }
                }

                if ($adminRoleId > 0 && !in_array((string) $adminRoleId, array_map('strval', $roleIds), true)) {
                    throw new InvalidArgumentException('No puede retirar su propio rol Administrador.');
                }
            }

            $beforeRoles = $security->roleCodesForUser($userId);
            $security->updateUser($userId, $status, $roleIds);
            $afterRoles = $security->roleCodesForUser($userId);

            $audit->log([
                'user_id' => $actor['id'],
                'user_label_snapshot' => $actor['display_name'],
                'module_code' => 'admin',
                'action_code' => 'user_updated',
                'record_type' => 'security_user',
                'record_id' => (string) $userId,
                'before' => ['status' => $before['status'], 'roles' => $beforeRoles],
                'after' => ['status' => $status, 'roles' => $afterRoles],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);

            flash('success', 'Usuario actualizado.');
            $this->redirect('?module=admin&action=edit&id=' . $userId);
        }

        if ($action === 'reset-password') {
            $userId = (int) ($post['user_id'] ?? 0);
            if ($security->findUser($userId) === null) {
                throw new InvalidArgumentException('El usuario solicitado no existe.');
            }

            $password = (string) ($post['new_password'] ?? '');
            $confirmation = (string) ($post['new_password_confirmation'] ?? '');
            $this->auth->validatePassword($password, $confirmation);

            $security->resetPassword(
                $userId,
                password_hash($password, PASSWORD_DEFAULT)
            );

            $audit->log([
                'user_id' => $actor['id'],
                'user_label_snapshot' => $actor['display_name'],
                'module_code' => 'admin',
                'action_code' => 'password_reset',
                'record_type' => 'security_user',
                'record_id' => (string) $userId,
                'details' => ['password_value_logged' => false],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);

            flash('success', 'Contraseña restablecida.');
            $this->redirect('?module=admin&action=edit&id=' . $userId);
        }

        if ($action === 'save-settings') {
            $before = $security->securitySettings();

            $idle = max(15, min((int) ($post['session_idle_minutes'] ?? 120), 720));
            $minimum = max(10, min((int) ($post['password_min_length'] ?? 12), 64));

            $after = [
                'security.session_idle_minutes' => (string) $idle,
                'security.password_min_length' => (string) $minimum,
            ];

            $security->setSettings($after);

            $audit->log([
                'user_id' => $actor['id'],
                'user_label_snapshot' => $actor['display_name'],
                'module_code' => 'admin',
                'action_code' => 'settings_updated',
                'record_type' => 'system_settings',
                'before' => $before,
                'after' => $after,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);

            flash('success', 'Parámetros de seguridad actualizados.');
            $this->redirect('?module=admin');
        }

        if ($action === 'save-role') {
            $roleId = (int) ($post['role_id'] ?? 0);
            $role = $security->role($roleId);
            if ($role === null) {
                throw new InvalidArgumentException('El rol seleccionado no existe.');
            }

            $before = $security->permissionCodesForRole($roleId);
            $permissionCodes = is_array($post['permission_codes'] ?? null)
                ? array_map('strval', $post['permission_codes'])
                : [];

            $security->updateRolePermissions($roleId, $permissionCodes);
            $after = $security->permissionCodesForRole($roleId);

            $audit->log([
                'user_id' => $actor['id'],
                'user_label_snapshot' => $actor['display_name'],
                'module_code' => 'admin',
                'action_code' => 'role_permissions_updated',
                'record_type' => 'security_role',
                'record_id' => (string) $roleId,
                'before' => ['permissions' => $before],
                'after' => ['permissions' => $after],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);

            flash('success', 'Permisos del rol actualizados.');
            $this->redirect('?module=admin&action=roles');
        }

        throw new InvalidArgumentException('La operación administrativa solicitada no existe.');
    }

    private function redirect(string $path): never
    {
        header('Location: ' . sindesg_url($path), true, 303);
        exit;
    }
}
