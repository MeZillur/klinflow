<?php
declare(strict_types=1);

/**
 * CP sidenav partial
 * Expected context: rendered inside apps/CP/Views/shared/layouts/shell.php
 */

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

function isActive(string $path, string $current): bool {
  return (strpos($current, $path) === 0);
}
?>
<nav style="padding:18px;font-size:15px">
  <div style="margin-bottom:12px;font-weight:700;font-size:12px;text-transform:uppercase;color:#94a3b8;letter-spacing:.04em">
    Control Panel
  </div>

  <a href="/cp/dashboard"
     style="display:block;padding:8px 10px;border-radius:8px;margin-bottom:4px;
            text-decoration:none;
            <?= isActive('/cp/dashboard', $currentPath)
                ? 'background:#e6f7ed;color:#228B22;font-weight:600'
                : 'color:#0f172a' ?>">
    <i class="fa-solid fa-chart-line" style="width:18px"></i>
    Dashboard
  </a>

  <a href="/cp/organizations"
     style="display:block;padding:8px 10px;border-radius:8px;margin-bottom:4px;
            text-decoration:none;
            <?= isActive('/cp/organizations', $currentPath)
                ? 'background:#e6f7ed;color:#228B22;font-weight:600'
                : 'color:#0f172a' ?>">
    <i class="fa-solid fa-building" style="width:18px"></i>
    Organizations
  </a>

  <a href="/cp/users"
     style="display:block;padding:8px 10px;border-radius:8px;margin-bottom:4px;
            text-decoration:none;
            <?= isActive('/cp/users', $currentPath)
                ? 'background:#e6f7ed;color:#228B22;font-weight:600'
                : 'color:#0f172a' ?>">
    <i class="fa-solid fa-user-group" style="width:18px"></i>
    Users
  </a>

  <a href="/cp/plans"
     style="display:block;padding:8px 10px;border-radius:8px;margin-bottom:4px;
            text-decoration:none;
            <?= isActive('/cp/plans', $currentPath)
                ? 'background:#e6f7ed;color:#228B22;font-weight:600'
                : 'color:#0f172a' ?>">
    <i class="fa-solid fa-tags" style="width:18px"></i>
    Plans
  </a>

  <a href="/cp/settings"
     style="display:block;padding:8px 10px;border-radius:8px;margin-bottom:4px;
            text-decoration:none;
            <?= isActive('/cp/settings', $currentPath)
                ? 'background:#e6f7ed;color:#228B22;font-weight:600'
                : 'color:#0f172a' ?>">
    <i class="fa-solid fa-gear" style="width:18px"></i>
    Settings
  </a>

  <hr style="border:0;border-top:1px solid #e5e7eb;margin:14px 0">

  <a href="/cp/signout"
     style="display:block;padding:8px 10px;border-radius:8px;margin-bottom:4px;
            text-decoration:none;color:#b91c1c">
    <i class="fa-solid fa-right-from-bracket" style="width:18px"></i>
    Logout
  </a>
</nav>