<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;
use Throwable;

final class BillingInvoicesController extends BaseController
{
    /* ---------- helpers ---------- */
    private function tableExists(PDO $pdo, string $t): bool {
        try { $s=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t LIMIT 1");
              $s->execute([':t'=>$t]); return (bool)$s->fetchColumn(); } catch(Throwable $e){ return false; } }
    private function colExists(PDO $pdo, string $t, string $c): bool {
        try { $s=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c LIMIT 1");
              $s->execute([':t'=>$t, ':c'=>$c]); return (bool)$s->fetchColumn(); } catch(Throwable $e){ return false; } }
    private function abortSoft(string $msg): void {
        http_response_code(500);
        echo '<div style="padding:16px;font:14px/1.45 system-ui"><b>Error:</b> '
           . htmlspecialchars($msg,ENT_QUOTES,'UTF-8').'</div>'; exit;
    }

    /* ---------- GET /billing/invoices/create ---------- */
    public function create(array $ctx): void
    {
        $c   = $this->ctx($ctx);
        $pdo = $this->pdo(); $orgId=(int)$c['org_id'];

        // Currency options (fallback)
        $curr = ['USD','EUR','GBP','BDT','INR','PKR','SAR','AED'];

        $this->view('billing/invoices/create', [
            'title'     => 'Create Invoice',
            'currencies'=> $curr,
        ], $c);
    }

    /* ---------- POST /billing/invoices ---------- */
    public function store(array $ctx): void
    {
        $c   = $this->ctx($ctx);
        $pdo = $this->pdo(); $orgId=(int)$c['org_id'];

        // Basic validation
        $issuedAt = (string)($_POST['issued_at'] ?? date('Y-m-d'));
        $currency = trim((string)($_POST['currency'] ?? 'USD'));
        $status   = trim((string)($_POST['status'] ?? 'issued'));
        $notes    = trim((string)($_POST['notes'] ?? ''));
        $billTo   = trim((string)($_POST['bill_to_type'] ?? 'walkin')); // guest|company|walkin
        $guestId  = (int)($_POST['guest_id'] ?? 0);
        $companyId= (int)($_POST['company_id'] ?? 0);
        $billName = trim((string)($_POST['bill_to_name'] ?? ''));

        // Lines (arrays aligned)
        $desc = $_POST['li_desc'] ?? [];
        $qty  = $_POST['li_qty']  ?? [];
        $rate = $_POST['li_rate'] ?? [];
        $taxr = $_POST['li_tax']  ?? []; // percent

        // quick normalize
        $lines = [];
        $n = max(count($desc), count($qty), count($rate), count($taxr));
        for ($i=0;$i<$n;$i++) {
            $d = trim((string)($desc[$i] ?? ''));
            $q = (float)($qty[$i]  ?? 0);
            $r = (float)($rate[$i] ?? 0);
            $t = (float)($taxr[$i] ?? 0);
            if ($d==='' && $q<=0 && $r<=0) continue;
            $net = $q * $r;
            $tax = $net * ($t/100);
            $tot = $net + $tax;
            $lines[] = ['desc'=>$d,'qty'=>$q,'rate'=>$r,'tax_rate'=>$t,'net'=>$net,'tax'=>$tax,'total'=>$tot];
        }

        $sumNet=0; $sumTax=0; $sumTot=0;
        foreach ($lines as $ln){ $sumNet+=$ln['net']; $sumTax+=$ln['tax']; $sumTot+=$ln['total']; }

        // Ensure tables exist
        $hasInv  = $this->tableExists($pdo,'hms_invoices');
        $hasLine = $this->tableExists($pdo,'hms_invoice_lines');
        if (!$hasInv) { $this->abortSoft("Missing table hms_invoices."); }

        // Build flexible insert for header
        $cols=['org_id']; $vals=[':o']; $bind=[':o'=>$orgId];

        // invoice_no (if exists)
        $invNo = 'INV-'.date('Ymd').'-'.substr((string)mt_rand(10000,99999),-5);
        if ($this->colExists($pdo,'hms_invoices','invoice_no')) { $cols[]='invoice_no'; $vals[]=':no'; $bind[':no']=$invNo; }

        // issued_at / issue_date
        if     ($this->colExists($pdo,'hms_invoices','issued_at'))   { $cols[]='issued_at';   $vals[]=':dt'; $bind[':dt']=$issuedAt; }
        elseif ($this->colExists($pdo,'hms_invoices','issue_date'))  { $cols[]='issue_date';  $vals[]=':dt'; $bind[':dt']=$issuedAt; }

        // currency/total/status/notes
        if ($this->colExists($pdo,'hms_invoices','currency'))  { $cols[]='currency';  $vals[]=':ccy'; $bind[':ccy']=$currency; }
        if ($this->colExists($pdo,'hms_invoices','total'))     { $cols[]='total';     $vals[]=':tot'; $bind[':tot']=$sumTot; }
        if ($this->colExists($pdo,'hms_invoices','tax_total')) { $cols[]='tax_total'; $vals[]=':tt';  $bind[':tt']=$sumTax; }
        if ($this->colExists($pdo,'hms_invoices','sub_total')) { $cols[]='sub_total'; $vals[]=':st';  $bind[':st']=$sumNet; }
        if ($this->colExists($pdo,'hms_invoices','status'))    { $cols[]='status';    $vals[]=':stt'; $bind[':stt']=$status ?: 'issued'; }
        if ($this->colExists($pdo,'hms_invoices','notes'))     { $cols[]='notes';     $vals[]=':nt';  $bind[':nt']=$notes ?: null; }

        // bill-to
        if ($this->colExists($pdo,'hms_invoices','guest_id'))    { $cols[]='guest_id';    $vals[]=':gid';  $bind[':gid']=($billTo==='guest' && $guestId>0)?$guestId:null; }
        if ($this->colExists($pdo,'hms_invoices','company_id'))  { $cols[]='company_id';  $vals[]=':cid';  $bind[':cid']=($billTo==='company' && $companyId>0)?$companyId:null; }
        if ($this->colExists($pdo,'hms_invoices','bill_to_name')){ $cols[]='bill_to_name';$vals[]=':bname';$bind[':bname']=$billName?:null; }

        // timestamps
        if ($this->colExists($pdo,'hms_invoices','created_at'))  { $cols[]='created_at';  $vals[]='CURRENT_TIMESTAMP'; }

        $sql = "INSERT INTO hms_invoices (".implode(',',$cols).") VALUES (".implode(',',$vals).")";

        try {
            $pdo->beginTransaction();
            $st=$pdo->prepare($sql); $st->execute($bind);
            $invId = (int)$pdo->lastInsertId();

            // Insert lines if table present
            if ($hasLine && $lines) {
                // probe columns
                $cDesc = $this->colExists($pdo,'hms_invoice_lines','description');
                $cQty  = $this->colExists($pdo,'hms_invoice_lines','qty') || $this->colExists($pdo,'hms_invoice_lines','quantity');
                $qtyCol= $this->colExists($pdo,'hms_invoice_lines','qty') ? 'qty' : ($this->colExists($pdo,'hms_invoice_lines','quantity') ? 'quantity' : null);
                $cRate = $this->colExists($pdo,'hms_invoice_lines','unit_price');
                $cTaxR = $this->colExists($pdo,'hms_invoice_lines','tax_rate');
                $cTaxA = $this->colExists($pdo,'hms_invoice_lines','tax_amount');
                $cAmt  = $this->colExists($pdo,'hms_invoice_lines','amount') || $this->colExists($pdo,'hms_invoice_lines','line_total');
                $amtCol= $this->colExists($pdo,'hms_invoice_lines','amount') ? 'amount' : ($this->colExists($pdo,'hms_invoice_lines','line_total') ? 'line_total' : null);

                foreach ($lines as $ln) {
                    $f=['org_id','invoice_id']; $v=[':o',':i']; $b=[':o'=>$orgId, ':i'=>$invId];
                    if ($cDesc){ $f[]='description'; $v[]=':d'; $b[':d']=$ln['desc']; }
                    if ($qtyCol){ $f[]=$qtyCol;      $v[]=':q'; $b[':q']=$ln['qty']; }
                    if ($cRate){ $f[]='unit_price';  $v[]=':r'; $b[':r']=$ln['rate']; }
                    if ($cTaxR){ $f[]='tax_rate';    $v[]=':tr';$b[':tr']=$ln['tax_rate']; }
                    if ($cTaxA){ $f[]='tax_amount';  $v[]=':ta';$b[':ta']=$ln['tax']; }
                    if ($amtCol){$f[]=$amtCol;       $v[]=':am';$b[':am']=$ln['total']; }
                    if ($this->colExists($pdo,'hms_invoice_lines','created_at')) { $f[]='created_at'; $v[]='CURRENT_TIMESTAMP'; }

                    $sqlL="INSERT INTO hms_invoice_lines (".implode(',',$f).") VALUES (".implode(',',$v).")";
                    $stL=$pdo->prepare($sqlL); $stL->execute($b);
                }
            }

            $pdo->commit();
            $this->redirect($c['module_base'].'/billing/invoices/'.$invId, $c);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->abort500('Invoice save failed: '.$e->getMessage());
        }
    }
}