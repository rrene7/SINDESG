<?php
declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    /** @var array<string, array<string, mixed>> */
    private array $connections;

    /** @var array<string, PDO> */
    private array $pdo = [];

    /**
     * @param array<string, array<string, mixed>> $connections
     */
    public function __construct(array $connections)
    {
        $this->connections = $connections;
    }

    public function connection(string $name): PDO
    {
        if (isset($this->pdo[$name])) {
            return $this->pdo[$name];
        }

        if (!isset($this->connections[$name])) {
            throw new RuntimeException("Conexión no configurada: {$name}");
        }

        $config = $this->connections[$name];
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 3306);
        $database = (string) ($config['database'] ?? '');
        $charset = (string) ($config['charset'] ?? 'utf8mb4');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        $this->pdo[$name] = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $this->pdo[$name];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function health(string $name): array
    {
        try {
            $this->connection($name)->query('SELECT 1');
            return ['ok' => true, 'message' => 'Conectado'];
        } catch (PDOException|RuntimeException $exception) {
            return ['ok' => false, 'message' => 'Sin conexión'];
        }
    }

    /**
     * Ejecuta una consulta estrictamente de lectura contra RRHH.
     *
     * @param array<string|int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function rrhhRead(string $sql, array $params = []): array
    {
        $normalized = ltrim($sql);
        if (!preg_match('/^(SELECT|WITH|SHOW|DESCRIBE|EXPLAIN)\b/i', $normalized)) {
            throw new RuntimeException('SINDESG solo permite consultas de lectura hacia RRHH2029.');
        }

        $statement = $this->connection('rrhh')->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }
}
