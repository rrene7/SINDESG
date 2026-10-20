<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\P4IssuerSignature;
use App\Services\P4AssignedWarehouses;
use App\Support\Database;
use InvalidArgumentException;
use Throwable;

final class ProfileController
{
    public function __construct(private Database $database) {}

    public function handle(string $action, string $method, array $post, array $files): array
    {
        $user=(array)sindesg_user();
        $roles=(array)($user['role_codes']??[]);
        if (!in_array('P4',$roles,true) || in_array('ADMIN',$roles,true)
            || in_array('CENTRAL',$roles,true) || (int)($user['id']??0)<=0) {
            throw new InvalidArgumentException('Mi firma está disponible solo para el responsable P4.');
        }
        if ($action!=='signature') throw new InvalidArgumentException('Acción no disponible.');
        $pdo=$this->database->connection('sindesg');
        $scopes=(new P4AssignedWarehouses($pdo))->listForUser((int)$user['id']);
        if (!$scopes) throw new InvalidArgumentException('Requiere un almacén P4 autorizado.');
        $service=new P4IssuerSignature($pdo);
        if (strtoupper($method)==='POST') {
            try {
                if (!csrf_valid(isset($post['_token'])?(string)$post['_token']:null))
                    throw new InvalidArgumentException('Formulario vencido.');
                if (!password_verify((string)($post['password']??''),(string)($user['password_hash']??'')))
                    throw new InvalidArgumentException('Contraseña incorrecta.');
                if (!empty($post['signature_png'])) {
                    $png=P4IssuerSignature::decodePng((string)$post['signature_png']);
                } elseif (isset($files['signature_file'])
                    && (int)($files['signature_file']['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK) {
                    if ((int)($files['signature_file']['size']??0)>160000)
                        throw new InvalidArgumentException('La imagen no puede exceder 160 KB.');
                    $tmp=(string)($files['signature_file']['tmp_name']??'');
                    if (!is_uploaded_file($tmp))
                        throw new InvalidArgumentException('Carga inválida.');
                    $png=P4IssuerSignature::validatePng(file_get_contents($tmp));
                } else {
                    throw new InvalidArgumentException('Dibuje o cargue una firma PNG.');
                }
                $service->save((int)$user['id'],$png);
                flash('success','Firma personal registrada. Se aplicará a comprobantes futuros, previa autorización.');
            } catch(Throwable $e) {
                flash('error',$e instanceof InvalidArgumentException ? $e->getMessage()
                    : 'No fue posible guardar la firma. Revise la migración 022 y vuelva a intentar.');
            }
            header('Location: '.sindesg_url('?module=profile&action=signature'),true,303);
            exit;
        }
        return [
            'title'=>'Mi firma institucional',
            'view'=>SINDESG_ROOT.'/app/Views/profile/signature.php',
            'data'=>['currentSignature'=>$service->current((int)$user['id'])]
        ];
    }
}
