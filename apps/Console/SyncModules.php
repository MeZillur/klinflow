<?php
require_once __DIR__.'/../../bootstrap.php'; // whatever boots autoload/DB

use Shared\Helpers\ModuleSync;

try {
    ModuleSync::syncFromFilesystem();
    echo "[".date('c')."] ModuleSync OK\n";
} catch (\Throwable $e) {
    error_log('ModuleSync failed: '.$e->getMessage());
    echo "[".date('c')."] ModuleSync FAILED: ".$e->getMessage()."\n";
    exit(1);
}