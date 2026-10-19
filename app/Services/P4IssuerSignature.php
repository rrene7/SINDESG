<?php
declare(strict_types=1);
namespace App\Services;

use PDO;
use InvalidArgumentException;
use RuntimeException;

final class P4IssuerSignature
{
    public function __construct(private PDO $pdo) {}

    public static function decodePng(string $data): string
    {
        if (!str_starts_with($data, 'data:image/png;base64,') || strlen($data) > 220000) {
            throw new InvalidArgumentException('La firma debe ser una imagen PNG de hasta 160 KB.');
        }
        $png = base64_decode(substr($data, 22), true);
        if ($png === false || strlen($png) < 100 || strlen($png) > 160000
            || !str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
            throw new InvalidArgumentException('Imagen de firma inválida.');
        }
        return $png;
    }

    public function save(int $userId, string $png): void
    {
        if ($userId <= 0) throw new RuntimeException('Usuario no identificado.');
        $sha = hash('sha256', $png);
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO p4_user_signatures(user_id,signature_png,signature_sha256)
                 VALUES (:user,:png,:sha)
                 ON DUPLICATE KEY UPDATE signature_png=VALUES(signature_png),
                     signature_sha256=VALUES(signature_sha256),updated_at=CURRENT_TIMESTAMP'
            );
            $stmt->execute(['user'=>$userId,'png'=>$png,'sha'=>$sha]);
            $this->pdo->prepare(
                "INSERT INTO p4_user_signature_history(user_id,signature_sha256,action_code)
                 VALUES (:user,:sha,'registered')"
            )->execute(['user'=>$userId,'sha'=>$sha]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function current(int $userId): ?array
    {
        if ($userId <= 0) return null;
        $stmt = $this->pdo->prepare(
            'SELECT signature_png,signature_sha256,updated_at
             FROM p4_user_signatures WHERE user_id=:user LIMIT 1'
        );
        $stmt->execute(['user'=>$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
