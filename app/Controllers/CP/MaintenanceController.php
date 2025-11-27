<?php
declare(strict_types=1);

namespace App\Controllers\CP;

use Shared\DB;
use App\Services\Mailer;

final class MaintenanceController
{
    // Hit this daily via cron: curl -s -X POST "https://yourhost/cp/maintenance/trial-reminders?key=YOUR_SECRET"
    public function trialReminders(): void
    {
        if (($_GET['key'] ?? '') !== (getenv('KF_CRON_KEY') ?: 'CHANGE_ME')) { http_response_code(403); echo 'forbidden'; return; }

        $pdo = DB::pdo();
        $rows = $pdo->query("
            SELECT id, name, owner_email, trial_end
              FROM cp_organizations
             WHERE plan='trial'
               AND status IN ('trial','active')
               AND trial_end = CURRENT_DATE + INTERVAL 10 DAY
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $mailer = new Mailer();
        foreach ($rows as $r) {
            try {
                $html = $this->renderEmail('apps/Public/emails/trial_10day_notice.php', [
                    'org_name'=>$r['name'],
                    'trial_end'=>$r['trial_end'],
                    'manage_url'=>url('/cp/organizations/'.$r['id'].'/edit'),
                ]);
                $mailer->send([
                    'to'      => [$r['owner_email']],
                    'from'    => 'account@klinflow.com',
                    'subject' => 'Your KlinFlow trial ends soon',
                    'html'    => $html,
                ]);
            } catch (\Throwable $e) { /* log */ }
        }

        echo 'ok';
    }

    private function renderEmail(string $tpl, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start(); require $tpl; return (string)ob_get_clean();
    }
}