<?php
declare(strict_types=1);

use App\Controllers\CP\AuthController;
use App\Controllers\CP\DashboardController;
use App\Controllers\CP\UsersController;
use App\Controllers\CP\OrganizationsController;
use App\Controllers\CP\PasswordController;
use App\Controllers\CP\MaintenanceController;
use App\Controllers\CP\OrgBranchesController;
use App\Controllers\CP\OrgBranchUsersController;
use App\Controllers\CP\MeController;

return [
    /* ---------------- Root → smart redirect ---------------- */
    ['GET', '/cp', function () {
        if (!empty($_SESSION['cp_user'])) {
            header('Location: /cp/dashboard', true, 302); exit;
        }
        header('Location: /cp/login', true, 302); exit;
    }],

    /* ---------------- Auth ---------------- */
    ['GET',  '/cp/login',  [AuthController::class, 'showLogin']],
    ['POST', '/cp/login',  [AuthController::class, 'login'],  ['csrf']],
    ['POST', '/cp/logout', [AuthController::class, 'logout'], ['auth']],

    /* -------------- Dashboard ------------- */
    ['GET',  '/cp/dashboard', [DashboardController::class, 'index'], ['auth']],

    /* -------------- Password -------------- */
    ['GET',  '/cp/forgot', [PasswordController::class, 'showForgot']],
    ['POST', '/cp/forgot', [PasswordController::class, 'sendReset'], ['csrf']],
    ['GET',  '/cp/reset',  [PasswordController::class, 'showReset']],
    ['POST', '/cp/reset',  [PasswordController::class, 'doReset'],   ['csrf']],

    /* -------------- CP Users -------------- */
    ['GET',  '/cp/users',        [UsersController::class, 'index'],      ['auth']],
    ['GET',  '/cp/users/new',    [UsersController::class, 'createForm'], ['auth']],
    ['POST', '/cp/users',        [UsersController::class, 'store'],      ['auth','csrf']],

    ['GET',  '#^/cp/users/(\d+)$#',      [UsersController::class, 'show'],   ['auth']],
    ['GET',  '#^/cp/users/(\d+)/edit$#', [UsersController::class, 'edit'],   ['auth']],
    ['POST', '#^/cp/users/(\d+)$#',      [UsersController::class, 'update'], ['auth','csrf']],

    /* ---------- Organizations (CP) -------- */
    ['GET',  '/cp/organizations',               [OrganizationsController::class, 'index'],      ['auth']],
    ['GET',  '/cp/organizations/new',           [OrganizationsController::class, 'createForm'], ['auth']],
    ['GET',  '/cp/organizations/create',        [OrganizationsController::class, 'createForm'], ['auth']],
    ['POST', '/cp/organizations',               [OrganizationsController::class, 'store'],      ['auth','csrf']],

    ['GET',  '#^/cp/organizations/(\d+)$#',      [OrganizationsController::class, 'show'],     ['auth']],
    ['GET',  '#^/cp/organizations/(\d+)/edit$#', [OrganizationsController::class, 'editForm'], ['auth']],
    ['POST', '#^/cp/organizations/(\d+)$#',      [OrganizationsController::class, 'update'],   ['auth','csrf']],

    /* ---------- Organization branches (CP-only) ---------- */
    ['GET',  '#^/cp/organizations/(\d+)/branches$#',
             [OrgBranchesController::class, 'index'],      ['auth']],

    ['GET',  '#^/cp/organizations/(\d+)/branches/create$#',
             [OrgBranchesController::class, 'createForm'], ['auth']],

    ['POST', '#^/cp/organizations/(\d+)/branches$#',
             [OrgBranchesController::class, 'store'],      ['auth','csrf']],

    ['GET',  '#^/cp/organizations/(\d+)/branches/(\d+)/edit$#',
             [OrgBranchesController::class, 'editForm'],   ['auth']],

    ['POST', '#^/cp/organizations/(\d+)/branches/(\d+)/update$#',
             [OrgBranchesController::class, 'update'],     ['auth','csrf']],

    ['POST', '#^/cp/organizations/(\d+)/branches/(\d+)/delete$#',
             [OrgBranchesController::class, 'destroy'],    ['auth','csrf']],

    /* ---------- Org branch users (CP-only) ---------- */
    ['GET',  '#^/cp/organizations/(\d+)/users$#',
             [OrgBranchUsersController::class, 'index'],      ['auth']],

    ['GET',  '#^/cp/organizations/(\d+)/users/create$#',
             [OrgBranchUsersController::class, 'createForm'], ['auth']],

    ['POST', '#^/cp/organizations/(\d+)/users$#',
             [OrgBranchUsersController::class, 'store'],      ['auth','csrf']],

    /* -------------- Maintenance (cron) -------------- */
    ['POST', '/cp/maintenance/trial-reminders',
             [MaintenanceController::class, 'trialReminders']],
];