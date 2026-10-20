<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class P4IssuerSignature
{
    public function __construct(private PDO $pdo) {}

    public static function decodePng(string $raw): string
    {
        if (!str_starts_with($raw,'data:image/png;base64,') || strlen($raw)>220000)
            throw new InvalidArgumentException('La firma debe ser PNG de hasta 160 KB.');
        return self::validatePng(base64_decode(substr($raw,strlen('data:image/png;base64,')),true));
    }
    public static function validatePng(string|false $png): string
    {
        if ($png===false || strlen($png)<100 || strlen($png)>160000
            || !str_starts_with($png,"\x89PNG\r\n\x1a\n"))
            throw new InvalidArgumentException('Archivo PNG inválido o demasiado grande.');
        $size=@getimagesizefromstring($png);
        if (!$size || ($size[2]??0)!==IMAGETYPE_PNG
            || $size[0]<50 || $size[1]<30 || $size[0]>1800 || $size[1]>900)
            throw new InvalidArgumentException('La firma debe ser PNG de tamaño válido.');
        return $png;
    }
    public function current(int $userId): ?array
    {
        if ($userId<=0) return null;
        $s=$this->pdo->prepare(
          'SELECT signature_png,signature_sha256,updated_at FROM p4_user_signatures WHERE user_id=:id');
        $s->execute(['id'=>$userId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    public function save(int $userId,string $png): void
    {
        if ($userId<=0) throw new RuntimeException('Usuario inválido.');
        $png=self::validatePng($png);
        $sha=hash('sha256',$png);
        $this->pdo->beginTransaction();
        try {
            $s=$this->pdo->prepare('SELECT signature_sha256 FROM p4_user_signatures WHERE user_id=:id FOR UPDATE');
            $s->execute(['id'=>$userId]);$old=$s->fetchColumn() ?: null;
            $this->pdo->prepare(
                'INSERT INTO p4_user_signatures(user_id,signature_png,signature_sha256)
                 VALUES (:id,:png,:sha) ON DUPLICATE KEY UPDATE
                 signature_png=VALUES(signature_png), signature_sha256=VALUES(signature_sha256),
                 updated_at=CURRENT_TIMESTAMP'
            )->execute(['id'=>$userId,'png'=>$png,'sha'=>$sha]);
            $this->pdo->prepare(
                'INSERT INTO p4_user_signature_history(user_id,previous_sha256,current_sha256)
                 VALUES (:id,:previous,:current)'
            )->execute(['id'=>$userId,'previous'=>$old,'current'=>$sha]);
            $this->pdo->commit();
        } catch(Throwable $e) {
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $e;
        }
    }
}
