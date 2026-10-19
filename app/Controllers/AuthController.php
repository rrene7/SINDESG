<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\RrhhEmployeeDirectory;
use App\Support\Database;
use InvalidArgumentException;
use Throwable;

final class AuthController
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
        if ($action === 'logout') {
            if (strtoupper($method) !== 'POST' || !csrf_valid($post['_token'] ?? null)) {
                throw new InvalidArgumentException('Solicitud de cierre de sesión no válida.');
            }

            $this->auth->logout();
            header('Location: ' . sindesg_url('?module=auth&action=login'), true, 303);
            exit;
        }

        if (!$this->auth->hasUsers()) {
            return $this->setup($post, $method);
        }

        if ($action === 'p4-signature') {
            $user = $this->auth->currentUser();
            $roles = (array) ($user['role_codes'] ?? []);
            if (!$user || !in_array('P4', $roles, true)) {
                throw new InvalidArgumentException('Solo usuarios P4 autenticados.');
            }
            $service = new \App\Services\P4IssuerSignature($this->database->connection('sindesg'));
            if (strtoupper($method) === 'POST') {
                if (!csrf_valid($post['_token'] ?? null)) {
                    throw new InvalidArgumentException('Formulario vencido.');
                }
                if (!password_verify((string) ($post['password'] ?? ''), (string) ($user['password_hash'] ?? ''))) {
                    throw new InvalidArgumentException('Contraseña incorrecta.');
                }
                $service->save((int) $user['id'], \App\Services\P4IssuerSignature::decodePng(
                    (string) ($post['signature_png'] ?? '')
                ));
                flash('success', 'Firma personal P4 registrada con auditoría.');
                $this->redirect('?module=auth&action=p4-signature');
            }
            return [
                'view' => SINDESG_ROOT . '/app/Views/auth/p4-signature.php',
                'title' => 'Mi firma P4',
                'data' => ['currentSignature' => $service->current((int) $user['id'])],
            ];
        }

        if ($this->auth->currentUser() !== null) {
            header('Location: ' . sindesg_url(), true, 303);
            exit;
        }

        if (strtoupper($method) === 'POST') {
            if (!csrf_valid($post['_token'] ?? null)) {
                flash('error', 'La sesión del formulario venció. Intente nuevamente.');
                $this->redirect('?module=auth&action=login');
            }

            try {
                $this->auth->login(
                    (string) ($post['username'] ?? ''),
                    (string) ($post['password'] ?? '')
                );
                $this->redirect('');
            } catch (Throwable $exception) {
                flash('error', $exception->getMessage());
                $this->redirect('?module=auth&action=login');
            }
        }

        return [
            'view' => SINDESG_ROOT . '/app/Views/auth/login.php',
            'title' => 'Iniciar sesión',
            'data' => [],
        ];
    }

    private function setup(array $post, string $method): array
    {
        if (strtoupper($method) === 'POST') {
            if (!csrf_valid($post['_token'] ?? null)) {
                flash('error', 'La sesión del formulario venció. Intente nuevamente.');
                $this->redirect('?module=auth&action=setup');
            }

            try {
                $employee = $this->employees->findEligibleEmployee(
                    (int) ($post['employee_id'] ?? 0)
                );
                if ($employee === null) {
                    throw new InvalidArgumentException(
                        'Seleccione un funcionario vigente de RRHH2029.'
                    );
                }

                $username = (string) ($post['username'] ?? '');
                $password = (string) ($post['password'] ?? '');
                $confirmation = (string) ($post['password_confirmation'] ?? '');

                $this->auth->setupFirstAdmin(
                    $employee,
                    $username,
                    $password,
                    $confirmation
                );

                $this->auth->login($username, $password);
                flash('success', 'Administrador inicial creado correctamente.');
                $this->redirect('');
            } catch (Throwable $exception) {
                flash('error', $exception->getMessage());
                $this->redirect('?module=auth&action=setup');
            }
        }

        return [
            'view' => SINDESG_ROOT . '/app/Views/auth/setup.php',
            'title' => 'Configuración inicial',
            'data' => [
                'employeeSearchUrl' => sindesg_url('public/api/employees.php'),
            ],
        ];
    }

    private function redirect(string $path): never
    {
        header('Location: ' . sindesg_url($path), true, 303);
        exit;
    }
}
