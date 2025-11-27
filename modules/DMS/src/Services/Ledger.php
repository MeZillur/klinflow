<?php
namespace Modules\DMS\Services;

use PDO;

final class Ledger
{
    public static function postSale(PDO $pdo, int $orgId, int $saleId, string $jno, string $dateYmd): void {
        // Example: AR Dr, Revenue Cr (simple, VAT/tax omitted for brevity)
        // Look up AR and Revenue account IDs; you can keep them in settings or a mapping table.
        $ar  = self::accountId($pdo, $orgId, '1100'); // Accounts Receivable
        $rev = self::accountId($pdo, $orgId, '4000'); // Sales Revenue

        $tot = (float)($pdo->query("SELECT grand_total FROM dms_sales WHERE id={$saleId} AND org_id={$orgId}")->fetchColumn() ?: 0);

        if ($tot <= 0) return;

        // Create journal header
        $hdr = $pdo->prepare("INSERT INTO dms_journals (org_id, jno, jdate, memo, status, created_at, updated_at) VALUES (?,?,?,?, 'posted', NOW(), NOW())");
        $hdr->execute([$orgId, $jno, $dateYmd, "Auto-post from Sale #{$saleId}"]);
        $jid = (int)$pdo->lastInsertId();

        // Lines
        $ln  = $pdo->prepare("INSERT INTO dms_journal_items (org_id, journal_id, account_id, debit, credit, line_memo) VALUES (?,?,?,?,?,?)");
        $ln->execute([$orgId, $jid, $ar,  $tot, 0.00, 'AR']);
        $ln->execute([$orgId, $jid, $rev, 0.00, $tot, 'Revenue']);
    }

    private static function accountId(PDO $pdo, int $orgId, string $code): int {
        $q=$pdo->prepare("SELECT id FROM dms_accounts WHERE org_id=? AND code=? LIMIT 1");
        $q->execute([$orgId,$code]);
        $id=(int)$q->fetchColumn();
        if ($id>0) return $id;
        // auto-create if missing (optional)
        $ins=$pdo->prepare("INSERT INTO dms_accounts (org_id,code,name,type,is_active,created_at,updated_at) VALUES (?,?,?,?,1,NOW(),NOW())");
        $type = str_starts_with($code,'4') ? 'income' : 'asset';
        $ins->execute([$orgId,$code,$code,$type]);
        return (int)$pdo->lastInsertId();
    }
}