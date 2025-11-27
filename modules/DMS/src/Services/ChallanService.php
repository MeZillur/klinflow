<?php
declare(strict_types=1);

namespace Modules\dms\Services;

use PDO;
use PDOException;

/**
 * ChallanService
 *
 * Creates a master challan from an invoice (idempotent per invoice) and
 * updates delivery (full/partial/none) at item level, deriving master status.
 *
 * Tables used (org-scoped):
 *  - dms_challan
 *  - dms_challan_items
 *  - dms_challan_events
 *  - dms_challan_templates
 *
 * Requires (read-only):
 *  - ar_invoices (id, org_id, invoice_no, customer_id, ship_to_name, ship_to_addr)
 *  - ar_invoice_items (id, org_id, invoice_id, product_id, uom, pack_size, qty)
 *  - cp_customers (id, name)
 *  - cp_products (id, sku, name)
 */
final class ChallanService
{
    /**
     * Generate a dispatch challan from an invoice.
     * Idempotent per (org_id, invoice_id).
     *
     * @throws \RuntimeException on missing invoice / items or DB errors
     */
    public static function generateForInvoice(PDO $pdo, int $orgId, int $invoiceId, int $actorId): int
    {
        // Return existing challan if one is already linked to this invoice (idempotent)
        $chk = $pdo->prepare("SELECT id FROM dms_challan WHERE org_id=? AND invoice_id=? LIMIT 1");
        $chk->execute([$orgId, $invoiceId]);
        $existingId = $chk->fetchColumn();
        if ($existingId) return (int)$existingId;

        // Fetch invoice + customer snapshot
        $inv = $pdo->prepare("
            SELECT i.id, i.invoice_no, i.customer_id,
                   c.name AS customer_name,
                   COALESCE(i.ship_to_name,'') AS ship_to_name,
                   COALESCE(i.ship_to_addr,'') AS ship_to_addr
            FROM ar_invoices i
            JOIN cp_customers c ON c.id = i.customer_id
            WHERE i.org_id = ? AND i.id = ?
            LIMIT 1
        ");
        $inv->execute([$orgId, $invoiceId]);
        $invoice = $inv->fetch(PDO::FETCH_ASSOC);
        if (!$invoice) {
            throw new \RuntimeException('Invoice not found for challan generation.');
        }

        // Fetch invoice items
        $it = $pdo->prepare("
            SELECT ii.id AS invoice_item_id,
                   ii.product_id,
                   p.sku,
                   p.name AS product_name,
                   COALESCE(ii.uom,'EA') AS uom,
                   COALESCE(ii.pack_size,1) AS pack_size,
                   ii.qty AS qty_ordered
            FROM ar_invoice_items ii
            JOIN cp_products p ON p.id = ii.product_id
            WHERE ii.org_id = ? AND ii.invoice_id = ?
            ORDER BY ii.id
        ");
        $it->execute([$orgId, $invoiceId]);
        $items = $it->fetchAll(PDO::FETCH_ASSOC);
        if (!$items) {
            throw new \RuntimeException('Invoice has no items to generate challan.');
        }

        // Transaction: create master + items + audit; ensure template exists
        $pdo->beginTransaction();
        try {
            $challanNo = self::nextChallanNo($pdo, $orgId);

            $totalItems = count($items);
            $totalQty   = 0.0;
            foreach ($items as $r) $totalQty += (float)$r['qty_ordered'];

            $ins = $pdo->prepare("
                INSERT INTO dms_challan
                  (org_id, challan_no, challan_date, invoice_id, invoice_no,
                   customer_id, customer_name, ship_to_name, ship_to_addr,
                   status, total_items, total_qty, created_by)
                VALUES (?,?,?,?,?,?,?,?,?, 'pending', ?, ?, ?)
            ");
            $ins->execute([
                $orgId,
                $challanNo,
                date('Y-m-d H:i:s'),
                (int)$invoice['id'],
                (string)$invoice['invoice_no'],
                (int)$invoice['customer_id'],
                (string)$invoice['customer_name'],
                (string)$invoice['ship_to_name'],
                (string)$invoice['ship_to_addr'],
                $totalItems,
                $totalQty,
                $actorId
            ]);
            $challanId = (int)$pdo->lastInsertId();

            $insItem = $pdo->prepare("
                INSERT INTO dms_challan_items
                  (org_id, challan_id, invoice_item_id, product_id, sku, product_name,
                   uom, pack_size, qty_ordered, qty_dispatched, qty_delivered, qty_returned, status)
                VALUES (?,?,?,?,?,?,?,?, ?, 0, 0, 0, 'pending')
            ");
            foreach ($items as $r) {
                $insItem->execute([
                    $orgId,
                    $challanId,
                    (int)$r['invoice_item_id'],
                    (int)$r['product_id'],
                    (string)$r['sku'],
                    (string)$r['product_name'],
                    (string)$r['uom'],
                    (float)$r['pack_size'],
                    (float)$r['qty_ordered'],
                ]);
            }

            self::logEvent(
                $pdo,
                $orgId,
                $challanId,
                $actorId,
                'created_from_invoice',
                ['invoice_id' => $invoiceId, 'invoice_no' => $invoice['invoice_no']]
            );

            self::ensureDefaultTemplate($pdo, $orgId);

            $pdo->commit();
            return $challanId;
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw new \RuntimeException('Failed to create challan: '.$e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Update delivery on a challan.
     *
     * @param array $lines shape:
     *   [
     *     item_id => ['delivered' => float, 'returned' => float, 'reason' => string],
     *     ...
     *   ]
     * @return string New master challan status
     */
    public static function updateDelivery(PDO $pdo, int $orgId, int $challanId, int $actorId, array $lines): string
    {
        if (!$lines) return self::getMasterStatus($pdo, $orgId, $challanId);

        $pdo->beginTransaction();
        try {
            // Ensure challan exists
            $c = $pdo->prepare("SELECT id FROM dms_challan WHERE org_id=? AND id=? LIMIT 1");
            $c->execute([$orgId, $challanId]);
            if (!$c->fetchColumn()) throw new \RuntimeException('Challan not found');

            $get = $pdo->prepare("
                SELECT id, qty_ordered, qty_delivered, qty_returned
                FROM dms_challan_items
                WHERE org_id=? AND challan_id=? AND id=? LIMIT 1
            ");
            $upd = $pdo->prepare("
                UPDATE dms_challan_items
                SET qty_delivered=?, qty_returned=?, status=?, reason_code=?, updated_at=NOW()
                WHERE org_id=? AND challan_id=? AND id=? LIMIT 1
            ");

            foreach ($lines as $itemId => $vals) {
                $itemId = (int)$itemId;
                if ($itemId <= 0 || !is_array($vals)) continue;

                $get->execute([$orgId, $challanId, $itemId]);
                $row = $get->fetch(PDO::FETCH_ASSOC);
                if (!$row) continue;

                $ordered   = (float)$row['qty_ordered'];
                $delivered = max(0.0, (float)($vals['delivered'] ?? 0));
                $returned  = max(0.0, (float)($vals['returned']  ?? 0));
                $reason    = substr((string)($vals['reason'] ?? ''), 0, 60);

                // Clamp to sensible bounds
                if ($delivered > $ordered) $delivered = $ordered;

                // Derive item status
                $status = 'pending';
                if ($delivered <= 0 && $returned <= 0) {
                    $status = 'not_received';
                } elseif ($delivered >= $ordered && $returned <= 0) {
                    $status = 'delivered';
                } elseif ($returned > 0 && $delivered <= 0) {
                    $status = 'returned';
                } elseif ($delivered > 0 || $returned > 0) {
                    $status = 'partial';
                }

                $upd->execute([$delivered, $returned, $status, $reason, $orgId, $challanId, $itemId]);
            }

            // Aggregate → master status & delivered qty
            $aggQ = $pdo->prepare("
                SELECT 
                  SUM(qty_ordered)   AS s_ord,
                  SUM(qty_delivered) AS s_del,
                  SUM(qty_returned)  AS s_ret
                FROM dms_challan_items
                WHERE org_id=? AND challan_id=?
            ");
            $aggQ->execute([$orgId, $challanId]);
            $agg = $aggQ->fetch(PDO::FETCH_ASSOC) ?: ['s_ord'=>0,'s_del'=>0,'s_ret'=>0];

            $master = 'pending';
            $sOrd = (float)$agg['s_ord'];
            $sDel = (float)$agg['s_del'];
            $sRet = (float)$agg['s_ret'];

            if ($sDel >= $sOrd && $sRet <= 0) {
                $master = 'delivered';
            } elseif ($sDel > 0 || $sRet > 0) {
                $master = 'partial';
            }

            $pdo->prepare("UPDATE dms_challan SET status=?, total_qty=?, updated_at=NOW() WHERE org_id=? AND id=?")
                ->execute([$master, $sDel, $orgId, $challanId]);

            self::logEvent($pdo, $orgId, $challanId, $actorId, 'delivery_updated', [
                'lines' => $lines,
                'status'=> $master
            ]);

            $pdo->commit();
            return $master;
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw new \RuntimeException('Failed to update delivery: '.$e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Next challan number per org: DCH-YYYYMM-##### (increment numeric tail)
     */
    public static function nextChallanNo(PDO $pdo, int $orgId): string
    {
        $prefix = 'DCH-'.date('Ym').'-';
        $q = $pdo->prepare("
            SELECT challan_no
            FROM dms_challan
            WHERE org_id=? AND challan_no LIKE ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $q->execute([$orgId, $prefix.'%']);
        $last = $q->fetchColumn();

        $n = 1;
        if ($last && preg_match('~-(\d+)$~', (string)$last, $m)) {
            $n = (int)$m[1] + 1;
        }
        return $prefix . str_pad((string)$n, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Ensure a default printable template exists for the org (idempotent).
     */
    public static function ensureDefaultTemplate(PDO $pdo, int $orgId): void
    {
        $q = $pdo->prepare("SELECT id FROM dms_challan_templates WHERE org_id=? AND name='Default' LIMIT 1");
        $q->execute([$orgId]);
        if ($q->fetchColumn()) return;

        $ins = $pdo->prepare("
            INSERT INTO dms_challan_templates (org_id, name, header_html, footer_html, options_json)
            VALUES (?,?,?,?,?)
        ");
        $header = '<div style="display:flex;justify-content:space-between;align-items:center">
                     <div><h2 style="margin:0">Dispatch Challan</h2><small>{{org_name}}</small></div>
                     <div style="text-align:right"><b>{{challan_no}}</b><br><small>{{challan_date}}</small></div>
                   </div><hr>';
        $footer = '<hr><small>Generated by KlinFlow DMS</small>';
        $opts   = json_encode(['columns'=>['sku','product_name','uom','qty_ordered','qty_delivered']], JSON_UNESCAPED_SLASHES);
        $ins->execute([$orgId, 'Default', $header, $footer, $opts]);
    }

    /**
     * Append an audit event (best-effort; never throws out).
     */
    public static function logEvent(PDO $pdo, int $orgId, int $challanId, int $actorId, string $event, array $payload = []): void
    {
        try {
            $st = $pdo->prepare("
                INSERT INTO dms_challan_events (org_id, challan_id, actor_id, event, payload, ip)
                VALUES (?,?,?,?,?, INET6_ATON(?))
            ");
            $st->execute([
                $orgId,
                $challanId,
                $actorId,
                $event,
                json_encode($payload, JSON_UNESCAPED_SLASHES),
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ]);
        } catch (\Throwable) {
            // swallow on purpose
        }
    }

    /**
     * Compute and return current master status (no write).
     */
    public static function getMasterStatus(PDO $pdo, int $orgId, int $challanId): string
    {
        $aggQ = $pdo->prepare("
            SELECT SUM(qty_ordered) s_ord, SUM(qty_delivered) s_del, SUM(qty_returned) s_ret
            FROM dms_challan_items
            WHERE org_id=? AND challan_id=?
        ");
        $aggQ->execute([$orgId, $challanId]);
        $agg = $aggQ->fetch(PDO::FETCH_ASSOC) ?: ['s_ord'=>0,'s_del'=>0,'s_ret'=>0];

        $sOrd = (float)$agg['s_ord'];
        $sDel = (float)$agg['s_del'];
        $sRet = (float)$agg['s_ret'];

        if ($sDel >= $sOrd && $sRet <= 0) return 'delivered';
        if ($sDel > 0 || $sRet > 0)      return 'partial';
        return 'pending';
    }
}