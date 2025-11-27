<?php
declare(strict_types=1);

namespace Shared\Modules;

use Shared\DB;
use Throwable;

final class ModuleUtil
{
    /**
     * Synchronize (idempotently) a module's sidebar entries for an org.
     *
     * Each item must be: ['label'=>string,'href'=>string,'parent'=>string|null,'sort'=>int]
     * - Upserts rows by UNIQUE(org_id, module_key, href).
     * - Marks missing rows as is_active=0 instead of deleting (safe rollback).
     * - Ensures href is org-scoped (replaces {org} placeholder if present).
     */
    public static function upsertOrgNav(int $orgId, string $moduleKey, array $items): void
    {
        $pdo = DB::pdo();
        $now = date('Y-m-d H:i:s');

        // Normalize & validate input
        $normalized = [];
        foreach ($items as $i => $it) {
            $label  = trim((string)($it['label'] ?? ''));
            $href   = trim((string)($it['href'] ?? ''));
            $parent = $it['parent'] ?? null;
            $sort   = (int)($it['sort'] ?? 0);

            if ($label === '' || $href === '') {
                // Skip invalid row rather than throwing for the whole batch.
                continue;
            }

            // Ensure org placeholder pathing stays consistent. If your router expects /t/{org}/..., allow
            // either {org} (placeholder) or hard-coded value and leave as-is.
            // Here we just keep it literal; your shell will replace {org} on render.
            // If you instead need to inject $orgId: uncomment the next line.
            // $href = str_replace('{org}', (string)$orgId, $href);

            $normalized[] = [
                'label'  => $label,
                'href'   => $href,
                'parent' => ($parent !== null && $parent !== '') ? (string)$parent : null,
                'sort'   => $sort,
            ];
        }

        if (!$normalized) {
            // Nothing to do; don't nuke existing rows.
            return;
        }

        $pdo->beginTransaction();
        try {
            // Track which hrefs we touched this run
            $touched = [];

            // Upsert each item (requires UNIQUE (org_id, module_key, href))
            $sql = "INSERT INTO tenant_module_nav
                        (org_id, module_key, label, href, parent_key, sort_order, is_active, created_at, updated_at)
                    VALUES
                        (:org_id, :module_key, :label, :href, :parent_key, :sort_order, 1, :now, :now)
                    ON DUPLICATE KEY UPDATE
                        label = VALUES(label),
                        parent_key = VALUES(parent_key),
                        sort_order = VALUES(sort_order),
                        is_active = 1,
                        updated_at = VALUES(updated_at)";
            $ins = $pdo->prepare($sql);

            foreach ($normalized as $it) {
                $ins->execute([
                    ':org_id'     => $orgId,
                    ':module_key' => $moduleKey,
                    ':label'      => $it['label'],
                    ':href'       => $it['href'],
                    ':parent_key' => $it['parent'],
                    ':sort_order' => $it['sort'],
                    ':now'        => $now,
                ]);
                $touched[] = $it['href'];
            }

            // Soft-deactivate any rows for this (org,module) that were NOT touched in this sync.
            // This avoids delete windows and preserves history.
            // If your PDO driver supports array params, great; otherwise build a safe IN list.
            if ($touched) {
                // Build placeholder list (:h0, :h1, ...)
                $ph = [];
                $params = [
                    ':org_id'     => $orgId,
                    ':module_key' => $moduleKey,
                ];
                foreach ($touched as $k => $href) {
                    $ph[] = ":h{$k}";
                    $params[":h{$k}"] = $href;
                }
                $in = implode(',', $ph);

                $deactivate = $pdo->prepare("
                    UPDATE tenant_module_nav
                       SET is_active = 0,
                           updated_at = :now
                     WHERE org_id = :org_id
                       AND module_key = :module_key
                       AND href NOT IN ($in)
                ");
                $params[':now'] = $now;
                $deactivate->execute($params);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            // Log to your central logger; keep user-facing silence if desired.
            // Example:
            error_log('[ModuleUtil.upsertOrgNav] '.$e->getMessage().' org='.$orgId.' module='.$moduleKey);
            // Do NOT delete anything on failure.
        }
    }
}