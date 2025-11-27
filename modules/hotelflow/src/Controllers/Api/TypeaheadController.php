<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers\Api;

use Modules\hotelflow\Controllers\BaseController;
use PDO;

final class TypeaheadController extends BaseController
{
    // GET /api/typeahead/guests?q=ali&limit=10
    public function guests(array $ctx): void
    {
        $c = $this->ctx($ctx); $orgId = (int)$c['org_id']; $pdo = $this->pdo();

        $q = trim((string)($_GET['q'] ?? ''));
        $limit = max(1, min(25, (int)($_GET['limit'] ?? 10)));
        if ($q === '') { $this->json(['ok'=>true,'data'=>[]]); return; }

        $sql = "SELECT id, name, email, mobile
                FROM hms_guests
                WHERE org_id=:o AND (name LIKE :q OR email LIKE :q OR mobile LIKE :q)
                ORDER BY name ASC LIMIT :lim";
        try {
            $st = $pdo->prepare($sql);
            $like = '%'.$q.'%';
            $st->bindValue(':o', $orgId, PDO::PARAM_INT);
            $st->bindValue(':q', $like, PDO::PARAM_STR);
            $st->bindValue(':lim', $limit, PDO::PARAM_INT);
            $st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $this->json(['ok'=>true,'data'=>$rows]);
        } catch (\Throwable $e) {
            $this->json(['ok'=>true,'data'=>[]]);
        }
    }
}