<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;
use Throwable;

final class InventoryController extends BaseController
{
    /* --------- helpers (safe) --------- */
    private function t(PDO $pdo, string $table): bool {
        try {
            $q=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t LIMIT 1");
            $q->execute([':t'=>$table]); return (bool)$q->fetchColumn();
        } catch (Throwable $e) { return false; }
    }
    private function c(PDO $pdo,string $table,string $col):bool{
        try{
            $q=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c LIMIT 1");
            $q->execute([':t'=>$table,':c'=>$col]); return (bool)$q->fetchColumn();
        }catch(Throwable $e){return false;}
    }
    private function safeFetchAll(PDO $pdo,string $sql,array $b=[]):array{
        try{$st=$pdo->prepare($sql);$st->execute($b);return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){return [];}
    }

    /* --------- UI entry --------- */
    public function dashboard(array $ctx): void {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $o=(int)$c['org_id'];
        $products = $this->t($pdo,'hms_products')
          ? $this->safeFetchAll($pdo,"SELECT id,sku,name,unit,category_id FROM hms_products WHERE org_id=:o ORDER BY id DESC LIMIT 10",[':o'=>$o]) : [];
        $purchases = $this->t($pdo,'hms_purchases')
          ? $this->safeFetchAll($pdo,"SELECT id,reference,supplier,doc_date,total FROM hms_purchases WHERE org_id=:o ORDER BY id DESC LIMIT 10",[':o'=>$o]) : [];

        $this->view('inventory/dashboard',[
          'title'=>'Inventory',
          'products'=>$products,
          'purchases'=>$purchases,
        ],$c);
    }

    /* --------- Products --------- */
    public function products(array $ctx): void {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $o=(int)$c['org_id'];
        $rows = $this->t($pdo,'hms_products')
          ? $this->safeFetchAll($pdo,"SELECT id,sku,name,unit,category_id FROM hms_products WHERE org_id=:o ORDER BY id DESC",[':o'=>$o]) : [];
        $cats = $this->t($pdo,'hms_categories')
          ? $this->safeFetchAll($pdo,"SELECT id,name FROM hms_categories WHERE org_id=:o ORDER BY name",[':o'=>$o]) : [];
        $this->view('inventory/products',[
          'title'=>'Products','rows'=>$rows,'cats'=>$cats,
          'ddl'=>$this->ddl('products')
        ],$c);
    }
    public function productStore(array $ctx): void {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $o=(int)$c['org_id'];
        if(!$this->t($pdo,'hms_products')) { $this->redirect($c['module_base'].'/inventory/products',$c); return; }
        $sku=trim((string)($_POST['sku']??'')); $name=trim((string)($_POST['name']??'')); $unit=trim((string)($_POST['unit']??''));
        $cat=(int)($_POST['category_id']??0);
        try{
            $st=$pdo->prepare("INSERT INTO hms_products (org_id,sku,name,unit,category_id,created_at) VALUES (:o,:s,:n,:u,:c,CURRENT_TIMESTAMP)");
            $st->execute([':o'=>$o,':s'=>$sku?:null,':n'=>$name?:null,':u'=>$unit?:null,':c'=>$cat?:null]);
        }catch(Throwable $e){}
        $this->redirect($c['module_base'].'/inventory/products',$c);
    }

    /* --------- Categories --------- */
    public function categories(array $ctx): void {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $o=(int)$c['org_id'];
        $rows = $this->t($pdo,'hms_categories')
          ? $this->safeFetchAll($pdo,"SELECT id,name,parent_id FROM hms_categories WHERE org_id=:o ORDER BY name",[':o'=>$o]) : [];
        $this->view('inventory/categories',[
          'title'=>'Categories','rows'=>$rows,
          'ddl'=>$this->ddl('categories')
        ],$c);
    }
    public function categoryStore(array $ctx): void {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $o=(int)$c['org_id'];
        if(!$this->t($pdo,'hms_categories')) { $this->redirect($c['module_base'].'/inventory/categories',$c); return; }
        $name=trim((string)($_POST['name']??'')); $pid=(int)($_POST['parent_id']??0);
        try{
            $st=$pdo->prepare("INSERT INTO hms_categories (org_id,name,parent_id,created_at) VALUES (:o,:n,:p,CURRENT_TIMESTAMP)");
            $st->execute([':o'=>$o,':n'=>$name?:null,':p'=>$pid?:null]);
        }catch(Throwable $e){}
        $this->redirect($c['module_base'].'/inventory/categories',$c);
    }

    /* --------- Purchases --------- */
    public function purchases(array $ctx): void {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $o=(int)$c['org_id'];
        $rows = $this->t($pdo,'hms_purchases')
          ? $this->safeFetchAll($pdo,"SELECT id,reference,supplier,doc_date,total,currency FROM hms_purchases WHERE org_id=:o ORDER BY id DESC",[':o'=>$o]) : [];
        $this->view('inventory/purchases',[
          'title'=>'Purchases','rows'=>$rows,
          'ddl'=>$this->ddl('purchases')
        ],$c);
    }
    public function purchaseCreate(array $ctx): void {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $o=(int)$c['org_id'];
        $products = $this->t($pdo,'hms_products')
          ? $this->safeFetchAll($pdo,"SELECT id,sku,name,unit FROM hms_products WHERE org_id=:o ORDER BY name",[':o'=>$o]) : [];
        $this->view('inventory/purchase-create',[
          'title'=>'New Purchase','products'=>$products
        ],$c);
    }
    public function purchaseStore(array $ctx): void {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $o=(int)$c['org_id'];
        if(!$this->t($pdo,'hms_purchases')) { $this->redirect($c['module_base'].'/inventory/purchases',$c); return; }
        $ref=trim((string)($_POST['reference']??'')); $supp=trim((string)($_POST['supplier']??'')); $date=(string)($_POST['doc_date']??'');
        $currency=trim((string)($_POST['currency']??'USD'));

        try{
            $pdo->beginTransaction();
            $st=$pdo->prepare("INSERT INTO hms_purchases (org_id,reference,supplier,doc_date,currency,total,created_at) VALUES (:o,:r,:s,:d,:c,0,CURRENT_TIMESTAMP)");
            $st->execute([':o'=>$o,':r'=>$ref?:null,':s'=>$supp?:null,':d'=>$date?:null,':c'=>$currency?:'USD']);
            $pid=(int)$pdo->lastInsertId();

            // items (arrays)
            $pidArr = $_POST['product_id']??[]; $qtyArr=$_POST['qty']??[]; $priceArr=$_POST['price']??[];
            $sum=0;
            if ($this->t($pdo,'hms_purchase_items')) {
                $sti=$pdo->prepare("INSERT INTO hms_purchase_items (org_id,purchase_id,product_id,qty,price,total) VALUES (:o,:p,:pr,:q,:u,:t)");
                for($i=0;$i<count($pidArr);$i++){
                    $pr=(int)$pidArr[$i]; $q=(float)$qtyArr[$i]; $u=(float)$priceArr[$i]; $t=$q*$u; $sum+=$t;
                    $sti->execute([':o'=>$o,':p'=>$pid,':pr'=>$pr?:null,':q'=>$q,':u'=>$u,':t'=>$t]);
                }
            }
            $upd=$pdo->prepare("UPDATE hms_purchases SET total=:t WHERE id=:id AND org_id=:o");
            $upd->execute([':t'=>$sum,':id'=>$pid,':o'=>$o]);

            // optional stock-in
            if ($this->t($pdo,'hms_stock_movements')) {
                $stm=$pdo->prepare("INSERT INTO hms_stock_movements (org_id,product_id,move_type,qty,ref_table,ref_id,created_at) VALUES (:o,:p,'in',:q,'hms_purchases',:r,CURRENT_TIMESTAMP)");
                for($i=0;$i<count($pidArr);$i++){
                    $pr=(int)$pidArr[$i]; $q=(float)$qtyArr[$i];
                    $stm->execute([':o'=>$o,':p'=>$pr?:null,':q'=>$q,':r'=>$pid]);
                }
            }

            $pdo->commit();
        }catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); }
        $this->redirect($c['module_base'].'/inventory/purchases',$c);
    }

    /* --------- Stock --------- */
    public function stock(array $ctx): void {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $o=(int)$c['org_id'];
        // live balance if movements+products exist
        $rows=[];
        if ($this->t($pdo,'hms_products') && $this->t($pdo,'hms_stock_movements')) {
            $rows=$this->safeFetchAll($pdo,"
                SELECT p.id, p.sku, p.name, p.unit,
                       COALESCE(SUM(CASE WHEN m.move_type='in'  THEN m.qty ELSE 0 END),0)
                       -COALESCE(SUM(CASE WHEN m.move_type='out' THEN m.qty ELSE 0 END),0) as on_hand
                FROM hms_products p
                LEFT JOIN hms_stock_movements m ON m.product_id=p.id AND m.org_id=p.org_id
                WHERE p.org_id=:o
                GROUP BY p.id,p.sku,p.name,p.unit
                ORDER BY p.name
            ",[':o'=>$o]);
        }
        $this->view('inventory/stock',[
          'title'=>'Inventory','rows'=>$rows,
          'ddl'=>$this->ddl('stock')
        ],$c);
    }

    /* --------- inline DDL helpers (render when table missing) --------- */
    private function ddl(string $which): array {
        switch ($which) {
            case 'products':
                return ['hms_products'=>
"CREATE TABLE hms_products(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_id INT UNSIGNED NOT NULL,
  sku VARCHAR(60) NULL,
  name VARCHAR(160) NOT NULL,
  unit VARCHAR(30) NULL,
  category_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  KEY idx_org(org_id),
  KEY idx_cat(category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"];
            case 'categories':
                return ['hms_categories'=>
"CREATE TABLE hms_categories(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  KEY idx_org(org_id),
  KEY idx_parent(parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"];
            case 'purchases':
                return [
'hms_purchases'=>
"CREATE TABLE hms_purchases(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_id INT UNSIGNED NOT NULL,
  reference VARCHAR(80) NULL,
  supplier VARCHAR(160) NULL,
  doc_date DATE NULL,
  currency VARCHAR(8) NULL,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
'hms_purchase_items'=>
"CREATE TABLE hms_purchase_items(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_id INT UNSIGNED NOT NULL,
  purchase_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  qty DECIMAL(12,3) NOT NULL DEFAULT 0,
  price DECIMAL(12,3) NOT NULL DEFAULT 0,
  total DECIMAL(12,3) NOT NULL DEFAULT 0,
  KEY idx_org(org_id),
  KEY idx_po(purchase_id),
  KEY idx_prod(product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
'hms_stock_movements'=>
"CREATE TABLE hms_stock_movements(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_id INT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  move_type ENUM('in','out') NOT NULL,
  qty DECIMAL(12,3) NOT NULL DEFAULT 0,
  ref_table VARCHAR(64) NULL,
  ref_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_org(org_id),
  KEY idx_prod(product_id),
  KEY idx_type(move_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
                ];
            case 'stock':
                return ['hms_stock_movements'=>'-- see purchases DDL above (hms_stock_movements)'];
        }
        return [];
    }
}