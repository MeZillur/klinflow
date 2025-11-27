<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Support\SignedLink;
use Shared\DB;
use Shared\View;

final class PublicShareController
{
    /** GET /share/dms/{tenant}/{type}/{id}?exp&sig */
    public function show(): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $parts = array_values(array_filter(explode('/', $uri), 'strlen'));
        // expect: ['share','dms','{tenant}','{type}','{id}']
        if (count($parts) < 5 || $parts[0] !== 'share' || $parts[1] !== 'dms') {
            http_response_code(404); echo 'Not found.'; return;
        }
        [$share, $dms, $tenant, $type, $idStr] = $parts;
        $id = ctype_digit($idStr) ? (int)$idStr : 0;
        if ($id <= 0) { http_response_code(404); echo 'Invalid document.'; return; }

        // Verify signature
        if (!SignedLink::verify($_SERVER['REQUEST_METHOD'] ?? 'GET', "/share/dms/{$tenant}/{$type}/{$id}", $_GET)) {
            http_response_code(403); echo 'Link expired or invalid.'; return;
        }

        // Find org by slug to get org_id (for scoping) + pass tenant_slug to view
        $pdo = DB::pdo();
        $org = $pdo->prepare("SELECT id, slug, name, logo_url FROM cp_organizations WHERE slug=? LIMIT 1");
        $org->execute([$tenant]);
        $orgRow = $org->fetch(\PDO::FETCH_ASSOC);
        if (!$orgRow) { http_response_code(404); echo 'Organization not found.'; return; }
        $orgId = (int)$orgRow['id'];
        $tenant_slug = (string)$orgRow['slug'];

        // Route by type → load data and reuse module print views
        switch ($type) {
            case 'invoice':
                $h = $pdo->prepare("SELECT * FROM dms_sales WHERE org_id=? AND id=?");
                $i = $pdo->prepare("SELECT * FROM dms_sale_items WHERE org_id=? AND sale_id=? ORDER BY id");
                $view = 'modules/DMS/Views/sales/print.php';
                $header = 'Invoice';
                break;

            case 'receipt':
                $h = $pdo->prepare("SELECT * FROM dms_receipts WHERE org_id=? AND id=?");
                $i = $pdo->prepare("SELECT * FROM dms_receipt_items WHERE org_id=? AND receipt_id=? ORDER BY id");
                $view = 'modules/DMS/Views/receipts/print.php';
                $header = 'Receipt';
                break;

            case 'return':
            case 'sales-return':
                $h = $pdo->prepare("SELECT * FROM dms_sales_returns WHERE org_id=? AND id=?");
                $i = $pdo->prepare("SELECT * FROM dms_sales_return_items WHERE org_id=? AND return_id=? ORDER BY id");
                $view = 'modules/DMS/Views/returns/print.php';
                $header = 'Sales Return';
                break;

            default:
                http_response_code(404); echo 'Unknown document type.'; return;
        }

        $h->execute([$orgId, $id]);
        $doc = $h->fetch(\PDO::FETCH_ASSOC);
        if (!$doc) { http_response_code(404); echo 'Document not found.'; return; }

        $i->execute([$orgId, $id]);
        $items = $i->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Render the module print view in a minimal wrapper shell.
        // Your View::render already supports absolute view paths,
        // otherwise require the file directly.
        $vars = [
            'title'       => $header.' — Public Share',
            'tenant_slug' => $tenant_slug,
        ];

        // Map data var names expected by your print views:
        if ($type === 'invoice')       { $vars['sale']  = $doc;  $vars['items'] = $items; }
        elseif ($type === 'receipt')   { $vars['rcpt']  = $doc;  $vars['items'] = $items; }
        else                           { $vars['ret']   = $doc;  $vars['items'] = $items; }

        // If your View system can’t resolve module absolute path, include directly:
        if (is_file($view)) {
            extract($vars);
            require $view;
        } else {
            // fallback if your templating needs it
            View::render($view, $vars, ['scope'=>'public','layout'=>null]);
        }
    }
}