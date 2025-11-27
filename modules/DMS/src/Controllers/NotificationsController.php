<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class NotificationsController extends BaseController
{
    private function actor(array $ctx): array {
        // adapt to your auth; minimally return ['org_id'=>..., 'user_id'=>...]
        $orgId  = $this->orgId($ctx);
        $userId = (int)($_SESSION['auth_user']['id'] ?? 0);
        return [$orgId, $userId];
    }

    private function json($data, int $code=200): void {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE); exit;
    }

    /** GET /api/notifications?limit=10 */
    public function index(array $ctx): void
    {
        [$orgId, $userId] = $this->actor($ctx);
        $pdo   = $this->pdo();
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));

        $sql = "
          SELECT id, level, title, body, link_url, is_read, created_at
          FROM dms_notifications
          WHERE org_id=?
            AND (user_id IS NULL OR user_id = ?)
          ORDER BY is_read ASC, id DESC
          LIMIT {$limit}";
        $st = $pdo->prepare($sql);
        $st->execute([$orgId, $userId ?: 0]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->json(['items'=>$rows]);
    }

    /** GET /api/notifications/count */
    public function count(array $ctx): void
    {
        [$orgId, $userId] = $this->actor($ctx);
        $pdo = $this->pdo();

        $st = $pdo->prepare("
          SELECT COUNT(*) FROM dms_notifications
           WHERE org_id=? AND is_read=0 AND (user_id IS NULL OR user_id=?)
        ");
        $st->execute([$orgId, $userId ?: 0]);
        $this->json(['unread'=>(int)$st->fetchColumn()]);
    }

    /** POST /api/notifications/{id}/read */
    public function markRead(array $ctx, int $id): void
    {
        [$orgId, $userId] = $this->actor($ctx);
        $pdo = $this->pdo();

        $st = $pdo->prepare("
          UPDATE dms_notifications
             SET is_read=1
           WHERE id=? AND org_id=? AND (user_id IS NULL OR user_id=?)
        ");
        $st->execute([$id, $orgId, $userId ?: 0]);
        $this->json(['ok'=>true, 'id'=>$id]);
    }

    /** POST /api/notifications/read-all */
    public function markAllRead(array $ctx): void
    {
        [$orgId, $userId] = $this->actor($ctx);
        $pdo = $this->pdo();

        $st = $pdo->prepare("
          UPDATE dms_notifications
             SET is_read=1
           WHERE org_id=? AND is_read=0 AND (user_id IS NULL OR user_id=?)
        ");
        $st->execute([$orgId, $userId ?: 0]);
        $this->json(['ok'=>true]);
    }
}