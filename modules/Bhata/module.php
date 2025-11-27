<?php
// modules/BhataFlow/module.php
namespace Modules\BhataFlow;

class Module {
  public static function boot(array $ctx = []): void
  {
    // $ctx could include org_id, user, router, view, db, etc. from your Kernel.
    // Hook points if your ModuleSync expects them:
    // - registerPolicies
    // - registerAssets
    // - registerNav
  }

  public static function shell(callable $content, array $data = [])
  {
    // simple helper to render the module-local shell
    extract($data);
    $contentPath = $content(); // returns a view path to include within layout
    include __DIR__ . '/Views/_shell/layout.php';
  }
}