<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

final class BrandsController extends BaseController
{
    /* ---------- context helper ---------- */
    private function ctxOk(array $ctx): array
    {
        $c   = $this->ctx($ctx);
        $org = $c['org'] ?? ($_SESSION['tenant_org'] ?? []);

        if (!isset($c['org']) && $org) {
            $c['org'] = $org;
        }
        if (empty($c['org_id']) && isset($org['id'])) {
            $c['org_id'] = (int)$org['id'];
        }
        if (empty($c['slug']) && isset($org['slug'])) {
            $c['slug'] = (string)$org['slug'];
        }
        if (empty($c['module_base'])) {
            $slug = (string)($c['slug'] ?? '');
            $c['module_base'] = $slug !== ''
                ? '/t/' . rawurlencode($slug) . '/apps/pos'
                : '/apps/pos';
        }
        if (empty($c['module_dir'])) {
            $c['module_dir'] = dirname(__DIR__, 2); // modules/POS
        }
        return $c;
    }

    /* ---------- INDEX: /brands ---------- */
    /** GET /brands */
    public function index(array $ctx = []): void
    {
        try {
            $c     = $this->ctxOk($ctx);
            $orgId = (int)($c['org_id'] ?? 0);

            $rows = $this->rows(
                "SELECT id, name, slug, is_active, created_at
                 FROM pos_brands
                 WHERE org_id = :o
                 ORDER BY name ASC",
                [':o' => $orgId]
            );

            $this->view($c['module_dir'].'/Views/brands/index.php', [
                'title'  => 'Brands',
                'base'   => $c['module_base'],
                'brands' => $rows,
            ], 'shell');
        } catch (\Throwable $e) {
            $this->oops('Brands index failed', $e);
        }
    }

    /* ---------- quick-create API (unchanged) ---------- */
    public function apiQuickCreate(array $ctx = []): void
    {
        try {
            $c   = $this->ctx($ctx);
            $org = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);

            $name = trim((string)($_POST['name'] ?? $_GET['name'] ?? ''));
            if ($name === '') {
                $this->json(['ok' => false, 'error' => 'Name is required'], 422);
                return;
            }

            $slug = strtolower(trim(preg_replace('~[^a-z0-9]+~i', '-', $name), '-'));
            if ($slug === '') {
                $slug = 'brand-'.time();
            }

            $exists = $this->row(
                "SELECT id FROM pos_brands WHERE org_id = :o AND name = :n LIMIT 1",
                [':o' => $org, ':n' => $name]
            );
            if ($exists) {
                $this->json([
                    'ok'       => true,
                    'id'       => (int)$exists['id'],
                    'name'     => $name,
                    'slug'     => $slug,
                    'existing' => true,
                ], 200);
                return;
            }

            $this->exec(
                "INSERT INTO pos_brands (org_id, name, slug, is_active, created_at, updated_at)
                 VALUES (:o, :n, :s, 1, NOW(), NOW())",
                [':o' => $org, ':n' => $name, ':s' => $slug]
            );
            $id = (int)$this->pdo()->lastInsertId();

            $this->json([
                'ok'   => true,
                'id'   => $id,
                'name' => $name,
                'slug' => $slug,
            ], 201);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Brand create failed'], 500);
        }
    }

    /* ---------- CREATE FORM (unchanged, but used below) ---------- */
    public function create(array $ctx = []): void
    {
        try {
            $c = $this->ctxOk($ctx);

            $this->view($c['module_dir'].'/Views/brands/create.php', [
                'title' => 'New Brand',
                'base'  => $c['module_base'],
            ], 'shell');
        } catch (\Throwable $e) {
            $this->oops('Brands create failed', $e);
        }
    }

    /* ---------- STORE: redirect to index ---------- */
    /** POST /brands */
    public function store(array $ctx = []): void
    {
        try {
            $this->postOnly();
            $c     = $this->ctxOk($ctx);
            $orgId = (int)($c['org_id'] ?? 0);

            $name     = trim((string)($_POST['name'] ?? ''));
            $isActive = isset($_POST['is_active']) ? 1 : 1; // default active

            $errors = [];
            if ($name === '') {
                $errors['name'] = 'Brand name is required';
            }

            $slug = strtolower(trim(preg_replace('~[^a-z0-9]+~i', '-', $name), '-'));
            if ($slug === '') {
                $slug = 'brand-'.time();
            }

            if ($name !== '') {
                $exists = $this->row(
                    "SELECT id FROM pos_brands WHERE org_id=:o AND name=:n LIMIT 1",
                    [':o'=>$orgId, ':n'=>$name]
                );
                if ($exists) {
                    $errors['name'] = 'This brand already exists';
                }
            }

            if ($errors) {
                $_SESSION['pos_errors'] = $errors;
                $_SESSION['pos_old']    = $_POST;
                $this->redirect($c['module_base'].'/brands/create');
            }

            $this->exec(
                "INSERT INTO pos_brands (org_id, name, slug, is_active, created_at, updated_at)
                 VALUES (:o, :n, :s, :a, NOW(), NOW())",
                [
                    ':o' => $orgId,
                    ':n' => $name,
                    ':s' => $slug,
                    ':a' => $isActive,
                ]
            );

            // 👈 after create → go to Brand index
            $this->redirect($c['module_base'].'/brands');
        } catch (\Throwable $e) {
            $this->oops('Brands store failed', $e);
        }
    }

    /* ----------------- SHOW ----------------- */
    /** GET /brands/{id} */
    public function showOne(array $ctx = [], int $id = 0): void
    {
        try {
            $c     = $this->ctxOk($ctx);
            $orgId = (int)($c['org_id'] ?? 0);
            $id    = (int)$id;

            $brand = $this->row(
                "SELECT * FROM pos_brands WHERE org_id=:o AND id=:id",
                [':o'=>$orgId, ':id'=>$id]
            );
            if (!$brand) {
                http_response_code(404);
                echo 'Not found';
                return;
            }

            $this->view($c['module_dir'].'/Views/brands/show.php', [
                'title' => 'Brand Details',
                'base'  => $c['module_base'],
                'brand' => $brand,
            ], 'shell');
        } catch (\Throwable $e) {
            $this->oops('Brands show failed', $e);
        }
    }
}