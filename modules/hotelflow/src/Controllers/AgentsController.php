<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;
use Throwable;

final class AgentsController extends BaseController
{
    private function colExists(PDO $pdo, string $t, string $c): bool {
        try { $s=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c LIMIT 1");
              $s->execute([':t'=>$t, ':c'=>$c]); return (bool)$s->fetchColumn(); } catch (Throwable $e){ return false; } }
    private function tableExists(PDO $pdo, string $t): bool {
        try { $s=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t LIMIT 1");
              $s->execute([':t'=>$t]); return (bool)$s->fetchColumn(); } catch (Throwable $e){ return false; } }

    public function index(array $ctx): void {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $orgId=(int)$c['org_id'];
        if (!$this->tableExists($pdo,'hms_travel_agents')) {
            $this->view('agents/empty',['title'=>'Agents'],$c); return;
        }
        $nameCol = $this->colExists($pdo,'hms_travel_agents','name') ? 'name' : ($this->colExists($pdo,'hms_travel_agents','agent_name')?'agent_name':"CONCAT('Agent #', id)");
        $rows=[];
        try {
            $rows=$pdo->query("SELECT id, {$nameCol} AS name FROM hms_travel_agents WHERE org_id={$orgId} ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch(Throwable $e){ $rows=[]; }
        $this->view('agents/index',['title'=>'Agents','rows'=>$rows],$c);
    }

    public function create(array $ctx): void {
        $c=$this->ctx($ctx); $this->view('agents/create',['title'=>'Add Agent'],$c);
    }
    public function store(array $ctx): void {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $orgId=(int)$c['org_id'];
        $name=trim((string)($_POST['name']??''));
        if ($this->tableExists($pdo,'hms_travel_agents')) {
            $col = $this->colExists($pdo,'hms_travel_agents','name') ? 'name' : ($this->colExists($pdo,'hms_travel_agents','agent_name')?'agent_name':null);
            if ($col) {
                $st=$pdo->prepare("INSERT INTO hms_travel_agents (org_id, {$col}, created_at) VALUES (:o,:n, CURRENT_TIMESTAMP)");
                $st->execute([':o'=>$orgId, ':n'=>$name?:null]);
            }
        }
        $this->redirect($c['module_base'].'/agents',$c);
    }

    public function show(array $ctx, int $id): void {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $orgId=(int)$c['org_id'];
        $nameCol = $this->colExists($pdo,'hms_travel_agents','name') ? 'name' : ($this->colExists($pdo,'hms_travel_agents','agent_name')?'agent_name':"CONCAT('Agent #', id)");
        $st=$pdo->prepare("SELECT id, {$nameCol} AS name FROM hms_travel_agents WHERE org_id=:o AND id=:id LIMIT 1");
        $st->execute([':o'=>$orgId, ':id'=>$id]); $row=$st->fetch(PDO::FETCH_ASSOC) ?: null;
        $this->view('agents/show',['title'=>$row?('Agent '.$row['name']):'Agent','a'=>$row],$c);
    }
}