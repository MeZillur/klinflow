<?php
declare(strict_types=1);

namespace Modules\HotelFlow\Controllers;

use PDO;

final class CustomersController extends BaseController
{
    public function index(array $ctx): void
    {
        $pdo=$this->pdo(); $orgId=$this->orgId($ctx);
        $rows = $pdo->query("SELECT id, name, email, phone, total_stays FROM hms_customers WHERE org_id={$orgId} ORDER BY id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $this->view('customers/index', ['title'=>'Customers','rows'=>$rows,'active'=>'customers'], $ctx);
    }

    public function create(array $ctx): void
    {
        $this->view('customers/create', ['title'=>'Add Customer','active'=>'customers'], $ctx);
    }

    public function store(array $ctx): void
    {
        $pdo=$this->pdo(); $orgId=$this->orgId($ctx);
        $name=trim((string)($_POST['name'] ?? '')); if ($name==='') $this->abort400('Name required.');
        $email=trim((string)($_POST['email'] ?? '')); $phone=trim((string)($_POST['phone'] ?? ''));
        $addr=trim((string)($_POST['address'] ?? ''));
        $pdo->prepare("INSERT INTO hms_customers (org_id, name, email, phone, address, created_at) VALUES (?,?,?,?,?,NOW())")->execute([$orgId,$name,$email ?: null,$phone ?: null,$addr ?: null]);
        $this->redirect($this->moduleBase($ctx).'/customers');
    }

    public function show(array $ctx, int $id): void
    {
        $pdo=$this->pdo(); $orgId=$this->orgId($ctx);
        $st=$pdo->prepare("SELECT * FROM hms_customers WHERE org_id=? AND id=?"); $st->execute([$orgId,$id]);
        $c=$st->fetch(PDO::FETCH_ASSOC); if(!$c) $this->abort404('Customer not found.');
        $this->view('customers/show', ['title'=>'Customer: '.($c['name'] ?? '#'.$id),'customer'=>$c,'active'=>'customers'], $ctx);
    }

    public function edit(array $ctx, int $id): void
    {
        $pdo=$this->pdo(); $orgId=$this->orgId($ctx);
        $st=$pdo->prepare("SELECT * FROM hms_customers WHERE org_id=? AND id=?"); $st->execute([$orgId,$id]);
        $c=$st->fetch(PDO::FETCH_ASSOC); if(!$c) $this->abort404('Customer not found.');
        $this->view('customers/edit', ['title'=>'Edit Customer','customer'=>$c,'active'=>'customers'], $ctx);
    }

    public function update(array $ctx, int $id): void
    {
        $pdo=$this->pdo(); $orgId=$this->orgId($ctx);
        $name=trim((string)($_POST['name'] ?? '')); if ($name==='') $this->abort400('Name required.');
        $email=trim((string)($_POST['email'] ?? '')); $phone=trim((string)($_POST['phone'] ?? ''));
        $addr=trim((string)($_POST['address'] ?? ''));
        $pdo->prepare("UPDATE hms_customers SET name=?, email=?, phone=?, address=?, updated_at=NOW() WHERE org_id=? AND id=?")->execute([$name,$email ?: null,$phone ?: null,$addr ?: null,$orgId,$id]);
        $this->redirect($this->moduleBase($ctx).'/customers/'.$id);
    }

    public function destroy(array $ctx, int $id): void
    {
        $pdo=$this->pdo(); $orgId=$this->orgId($ctx);
        $pdo->prepare("DELETE FROM hms_customers WHERE org_id=? AND id=?")->execute([$orgId,$id]);
        $this->redirect($this->moduleBase($ctx).'/customers');
    }

    /** GET /customers/search.json?q=... */
    public function searchJson(array $ctx): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $pdo=$this->pdo(); $orgId=$this->orgId($ctx);
        $q=trim((string)($_GET['q'] ?? '')); $like='%'.$q.'%';
        $st=$pdo->prepare("SELECT id, name, phone, email FROM hms_customers WHERE org_id=? AND (name LIKE ? OR phone LIKE ? OR email LIKE ?) ORDER BY name LIMIT 50");
        $st->execute([$orgId,$like,$like,$like]);
        echo json_encode($st->fetchAll(PDO::FETCH_ASSOC) ?: []); exit;
    }
}