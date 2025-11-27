<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use Throwable;

final class SettingsController extends BaseController
{
    /* -------------------------------------------------------------
     * Low-level filesystem helpers
     * ----------------------------------------------------------- */

    /** Base dir where all logos live: modules/bizflow/Assets/brand/logo */
    private function logoBaseDir(): string
    {
        return dirname(__DIR__, 2) . '/Assets/brand/logo';
    }

    /** Base dir where all statements/docs live: modules/bizflow/Assets/documents/statements */
    private function docsBaseDir(): string
    {
        return dirname(__DIR__, 2) . '/Assets/documents/statements';
    }

    /** Base dir where per-tenant identity JSON lives */
    private function identityBaseDir(): string
    {
        return dirname(__DIR__, 2) . '/Assets/settings';
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
    }

    /* -------------------------------------------------------------
     * Index (GET /settings)
     * ----------------------------------------------------------- */

    public function index(?array $ctx = null): void
    {
        try {
            $c          = $this->ctx($ctx ?? []);
            $org        = (array)($c['org'] ?? []);
            $orgId      = $this->requireOrg();
            $moduleBase = rtrim((string)($c['module_base'] ?? '/apps/bizflow'), '/');

            // FS info for logo / docs / identity
            $logoInfo     = $this->currentLogoInfo($orgId);
            $docsInfo     = $this->currentDocsInfo($orgId);
            $identityInfo = $this->currentIdentityInfo($orgId, $org);

            // Always prefer the canonical asset route for logo so that
            // browser + dompdf use the same URL: /.../apps/bizflow/assets/logo
            if ($logoInfo['exists']) {
                $logoInfo['url'] = $moduleBase . '/assets/logo';
            } else {
                // keep null; view will show placeholder
                $logoInfo['url'] = null;
            }

            $this->view('settings/index', [
                'title'       => 'BizFlow settings',
                'org'         => $org,
                'org_id'      => $orgId,
                'module_base' => $moduleBase,
                'logo'        => $logoInfo,
                'documents'   => $docsInfo,
                'identity'    => $identityInfo['values'],
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Settings index failed', $e);
        }
    }

    /* -------------------------------------------------------------
     * FS state builders
     * ----------------------------------------------------------- */

    /** Build info about the current logo for this org */
    private function currentLogoInfo(int $orgId): array
    {
        $baseDir = $this->logoBaseDir();
        $orgKey  = 'org_' . $orgId;
        $dir     = $baseDir . '/' . $orgKey;

        $this->ensureDir($dir);

        $candidates = ['logo.png', 'logo.jpg', 'logo.jpeg', 'logo.webp', 'logo.svg'];

        $filePath = null;

        foreach ($candidates as $file) {
            $p = $dir . '/' . $file;
            if (is_file($p)) {
                $filePath = $p;
                break;
            }
        }

        return [
            'dir'     => $dir,
            'org_key' => $orgKey,
            'path'    => $filePath,
            // url will be injected in index() via /assets/logo route
            'url'     => null,
            'exists'  => $filePath !== null,
        ];
    }

    /** Build info about documents/statements for this org */
    private function currentDocsInfo(int $orgId): array
    {
        $baseDir = $this->docsBaseDir();
        $orgKey  = 'org_' . $orgId;
        $dir     = $baseDir . '/' . $orgKey;

        $this->ensureDir($dir);

        $files = [];
        if (is_dir($dir)) {
            foreach (scandir($dir) ?: [] as $f) {
                if ($f === '.' || $f === '..') {
                    continue;
                }
                $p = $dir . '/' . $f;
                if (is_file($p)) {
                    $files[] = [
                        'name'  => $f,
                        'size'  => @filesize($p) ?: 0,
                        'mtime' => @filemtime($p) ?: null,
                    ];
                }
            }
        }

        return [
            'dir'     => $dir,
            'org_key' => $orgKey,
            'files'   => $files,
        ];
    }

    /** Build info about identity (name, address, phone, email) for this org */
    private function currentIdentityInfo(int $orgId, array $org): array
    {
        $baseDir = $this->identityBaseDir();
        $orgKey  = 'org_' . $orgId;
        $dir     = $baseDir . '/' . $orgKey;

        $this->ensureDir($dir);

        $file   = $dir . '/identity.json';
        $values = [
            'name'    => trim((string)($org['name'] ?? '')),
            'address' => trim((string)($org['address'] ?? '')),
            'phone'   => trim((string)($org['phone'] ?? '')),
            'email'   => trim((string)($org['email'] ?? '')),
        ];

        if (is_file($file)) {
            $raw  = @file_get_contents($file);
            $data = json_decode((string)$raw, true);
            if (is_array($data)) {
                foreach (['name', 'address', 'phone', 'email'] as $k) {
                    if (isset($data[$k]) && is_string($data[$k]) && $data[$k] !== '') {
                        $values[$k] = $data[$k];
                    }
                }
            }
        }

        return [
            'dir'    => $dir,
            'file'   => $file,
            'values' => $values,
        ];
    }

    /* -------------------------------------------------------------
     * Update handler (POST /settings)
     * ----------------------------------------------------------- */

    public function update(?array $ctx = null): void
    {
        $section = (string)($_POST['section'] ?? '');

        try {
            $c          = $this->ctx($ctx ?? []);
            $org        = (array)($c['org'] ?? []);
            $orgId      = $this->requireOrg();
            $moduleBase = rtrim((string)($c['module_base'] ?? '/apps/bizflow'), '/');

            if ($section === 'branding') {
                $this->handleBranding($orgId);
            } elseif ($section === 'documents') {
                $this->handleDocuments($orgId);
            } elseif ($section === 'identity') {
                $this->handleIdentity($orgId, $org);
            }

            if (!headers_sent()) {
                header('Location: ' . $moduleBase . '/settings?saved=1');
            }
            exit;

        } catch (Throwable $e) {
            $this->oops('Settings update failed', $e);
        }
    }

    /* -------------------------------------------------------------
     * Section handlers
     * ----------------------------------------------------------- */

    /** Handle logo upload for this org. */
    /** Handle logo upload for this org. */
private function handleBranding(int $orgId): void
{
    if (empty($_FILES['org_logo']) || !is_array($_FILES['org_logo'])) {
        return;
    }

    $file = $_FILES['org_logo'];
    $err  = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        return;
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return;
    }

    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'], true)) {
        return;
    }

    $baseDir = $this->logoBaseDir();
    $orgKey  = 'org_' . $orgId;
    $dir     = $baseDir . '/' . $orgKey;
    $this->ensureDir($dir);

    // Remove any previous logo.* files so we do not keep junk
    foreach (['png', 'jpg', 'jpeg', 'webp', 'svg'] as $e) {
        $old = $dir . '/logo.' . $e;
        if (is_file($old)) {
            @unlink($old);
        }
    }

    $target = $dir . '/logo.' . $ext;

    if (@move_uploaded_file($tmpName, $target)) {
        // Make sure the web server can read this file directly
        // (needed for /modules/bizflow/Assets/brand/logo/... in the PDF)
        @chmod($target, 0644);
    }
}

    /** Handle a single statement/document upload. */
    private function handleDocuments(int $orgId): void
    {
        if (empty($_FILES['statement_file']) || !is_array($_FILES['statement_file'])) {
            return;
        }

        $file = $_FILES['statement_file'];
        $err  = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            return;
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return;
        }

        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'xlsx', 'csv'], true)) {
            return;
        }

        $baseDir = $this->docsBaseDir();
        $orgKey  = 'org_' . $orgId;
        $dir     = $baseDir . '/' . $orgKey;
        $this->ensureDir($dir);

        $original = (string)($file['name'] ?? '');
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original);
        if ($safeName === '' || $safeName === null) {
            $safeName = 'statement_' . $orgId . '_' . date('Ymd_His') . '.' . $ext;
        }

        $target = $dir . '/' . $safeName;
        @move_uploaded_file($tmpName, $target);
        @chmod($target, 0660);
    }

    /** Handle organisation identity save (name, address, phone, email). */
    private function handleIdentity(int $orgId, array $org): void
    {
        $name    = $this->cleanField((string)($_POST['org_name'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $phone   = $this->cleanField((string)($_POST['phone'] ?? ''));
        $emailIn = trim((string)($_POST['email'] ?? ''));

        $email = '';
        if ($emailIn !== '') {
            $emailValid = filter_var($emailIn, FILTER_VALIDATE_EMAIL);
            if ($emailValid !== false) {
                $email = $emailValid;
            }
        }

        // Fallback to existing org values if fields left empty
        if ($name === '' && isset($org['name'])) {
            $name = (string)$org['name'];
        }
        if ($address === '' && isset($org['address'])) {
            $address = (string)$org['address'];
        }
        if ($phone === '' && isset($org['phone'])) {
            $phone = (string)$org['phone'];
        }
        if ($email === '' && isset($org['email'])) {
            $email = (string)$org['email'];
        }

        $baseDir = $this->identityBaseDir();
        $orgKey  = 'org_' . $orgId;
        $dir     = $baseDir . '/' . $orgKey;
        $this->ensureDir($dir);

        $data = [
            'name'    => $name,
            'address' => $address,
            'phone'   => $phone,
            'email'   => $email,
        ];

        $file = $dir . '/identity.json';
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        @chmod($file, 0660);
    }

    private function cleanField(string $value): string
    {
        $v = trim($value);
        $v = str_replace(["\r", "\n"], ' ', $v);
        return $v;
    }
}