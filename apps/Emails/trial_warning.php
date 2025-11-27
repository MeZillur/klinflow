<?php /** @var string $org_name @var string $trial_end @var string $manage_url */ ?>
<!doctype html><html><body style="font-family:Inter,system-ui,Segoe UI,Arial,sans-serif;background:#f6f7fb;padding:24px">
  <table width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden">
    <tr><td style="padding:24px 24px 8px">
      <h2 style="margin:0;color:#111">Your trial is ending soon</h2>
      <p style="margin:6px 0 0;color:#6b7280">Organization: <strong><?= htmlspecialchars($org_name) ?></strong></p>
    </td></tr>
    <tr><td style="padding:8px 24px 16px">
      <p style="color:#111;margin:0 0 8px">Your trial will end on <strong><?= htmlspecialchars($trial_end) ?></strong>.</p>
      <p style="color:#6b7280;margin:0 0 16px">To keep using KlinFlow without interruption, choose a plan before your trial ends.</p>
      <a href="<?= htmlspecialchars($manage_url) ?>"
         style="display:inline-block;background:#228B22;color:#fff;padding:10px 16px;border-radius:10px;text-decoration:none">
        Select a Plan
      </a>
    </td></tr>
    <tr><td style="padding:8px 24px 24px;color:#9ca3af;font-size:12px">Sent from account@klinflow.com</td></tr>
  </table>
</body></html>