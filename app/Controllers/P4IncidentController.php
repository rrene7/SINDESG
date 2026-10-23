<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Repositories\P4IncidentRepository;
use App\Support\Database;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class P4IncidentController
{
    private P4IncidentRepository $cases;
    public function __construct(Database $db) {
        $this->cases=new P4IncidentRepository($db->connection('sindesg'));
    }
    public function handle(string $action,array $query,array $post,string $method):array
    {
        $user=(array)sindesg_user();
        $uid=(int)($user['id']??0);
        $roles=(array)($user['role_codes']??[]);
        $admin=in_array('ADMIN',$roles,true);
        $institutional=$admin||in_array('CENTRAL',$roles,true);
        $p4=in_array('P4',$roles,true)&&!$institutional;
        try {
            if ($uid<=0||(!$institutional&&!$p4))throw new InvalidArgumentException('Rol sin autorización.');
            if (strtoupper($method)==='POST') {
                if (!csrf_valid(isset($post['_token'])?(string)$post['_token']:null))throw new InvalidArgumentException('Formulario vencido.');
                if ($action==='save'&&$p4) {
                    $id=$this->cases->open($uid,(int)($post['delivery_item_id']??0),$post);
                    flash('success','Incidencia recibida. Central revisará el expediente; inventario sin cambios.');
                    $this->redirect('?module=incidents&action=show&id='.$id);
                }
                if ($action==='review'&&$institutional) {
                    $id=(int)($post['id']??0);
                    $this->cases->transition($id,$uid,'review');
                    flash('success','Caso en revisión, sin movimientos de kardex.');
                    $this->redirect('?module=incidents&action=show&id='.$id);
                }
                if (in_array($action,['resolve','reject'],true)&&$admin) {
                    $id=(int)($post['id']??0);
                    $this->cases->transition($id,$uid,$action,(string)($post['resolution_notes']??''),(int)($post['adjustment_id']??0));
                    flash('success','Resolución registrada con historial.');
                    $this->redirect('?module=incidents&action=show&id='.$id);
                }
                throw new InvalidArgumentException('Operación no autorizada.');
            }
            if ($action==='new'&&$p4) {
                $source=$this->cases->sourceForP4($uid,(int)($query['delivery_item_id']??0));
                if (!$source)throw new InvalidArgumentException('Entrega ajena al P4 o no confirmada.');
                return $this->view('new','Recepción de incidencia',['incidentSource'=>$source,'incidentTypes'=>P4IncidentRepository::TYPES]);
            }
            if ($action==='show') {
                $id=(int)($query['id']??0);$case=$this->cases->find($uid,$id,$institutional);
                if (!$case)throw new InvalidArgumentException('Expediente no autorizado.');
                return $this->view('show','Incidencia '.$case['case_code'],[
                    'incident'=>$case,'incidentHistory'=>$this->cases->history($id),
                    'incidentIsAdmin'=>$admin,'incidentInstitutional'=>$institutional,
                    'incidentTypes'=>P4IncidentRepository::TYPES,'incidentStatuses'=>P4IncidentRepository::STATUS
                ]);
            }
            if ($action==='index') {
                $filter=trim((string)($query['status']??''));
                return $this->view('index','Incidencias P4',[
                    'incidentList'=>$this->cases->listing($uid,$institutional,max(1,(int)($query['page']??1)),$filter),
                    'incidentTypes'=>P4IncidentRepository::TYPES,
                    'incidentStatuses'=>P4IncidentRepository::STATUS,
                    'incidentInstitutional'=>$institutional
                ]);
            }
            throw new InvalidArgumentException('Acción no disponible.');
        } catch(InvalidArgumentException|RuntimeException $error) {
            flash('error',$error->getMessage());
            if($action==='save')$this->redirect('?module=incidents&action=new&delivery_item_id='.(int)($post['delivery_item_id']??0));
            $this->redirect('?module=incidents');
        } catch(Throwable $error) {
            return $this->view('unavailable','Incidencias no disponibles',[
                'incidentError'=>'Este módulo requiere la migración aditiva 023 en SINDESG. No se alteró el inventario.'
            ]);
        }
    }
    private function view(string $name,string $title,array $data=[]):array {
        return ['view'=>SINDESG_ROOT.'/app/Views/incidents/'.$name.'.php','title'=>$title,'data'=>$data];
    }
    private function redirect(string $path):never {
        header('Location: '.sindesg_url($path),true,303);exit;
    }
}
