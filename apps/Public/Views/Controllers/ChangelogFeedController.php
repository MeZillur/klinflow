<?php
declare(strict_types=1);

namespace Apps\Public\Controllers;

final class ChangelogFeedController
{
    /** Load updates from /assets/changelog.json (web root path) */
    private function loadUpdates(): array
    {
        $file = $_SERVER['DOCUMENT_ROOT'] . '/assets/changelog.json';
        $items = [];
        if (is_file($file)) {
            $raw = file_get_contents($file);
            $json = json_decode((string)$raw, true);
            if (is_array($json)) $items = $json;
        }
        // Fallback if file missing/empty
        if (!$items) {
            $items = [
                ['version'=>'v2.4','date'=>'2025-11-01','note'=>'Added multi-organization purchase order workflow.'],
                ['version'=>'v2.3','date'=>'2025-10-12','note'=>'Improved POS performance and supplier lookup speed.'],
                ['version'=>'v2.2','date'=>'2025-09-25','note'=>'Security hardening and improved API authentication.'],
            ];
        }
        // Sort by date desc if date present
        usort($items, function($a,$b){
            $da = strtotime($a['date'] ?? '') ?: 0;
            $db = strtotime($b['date'] ?? '') ?: 0;
            return $db <=> $da;
        });
        return $items;
    }

    private function site(): array
    {
        $host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'www.klinflow.com');
        return [
            'title'       => 'KlinFlow Changelog',
            'desc'        => 'Latest product updates, fixes, and improvements across POS, HotelFlow, Bhata, School, MedFlow, and DMS.',
            'link'        => $host . '/changelog',
            'self_rss'    => $host . '/changelog.xml',
            'self_atom'   => $host . '/changelog.atom',
            'image'       => $host . '/assets/brand/klinflow-social.jpg',
        ];
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    /** GET /changelog.xml */
    public function rss(): void
    {
        // Prevent BOM/whitespace & PHP notices
        if (ob_get_level()) @ob_clean();
        header('Content-Type: application/rss+xml; charset=utf-8');

        $site = $this->site();
        $items = $this->loadUpdates();

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        ?>
<rss version="2.0">
  <channel>
    <title><?= $this->esc($site['title']) ?></title>
    <link><?= $this->esc($site['link']) ?></link>
    <description><?= $this->esc($site['desc']) ?></description>
    <language>en</language>
    <image>
      <url><?= $this->esc($site['image']) ?></url>
      <title><?= $this->esc($site['title']) ?></title>
      <link><?= $this->esc($site['link']) ?></link>
    </image>
    <atom:link xmlns:atom="http://www.w3.org/2005/Atom" href="<?= $this->esc($site['self_rss']) ?>" rel="self" type="application/rss+xml" />
<?php foreach ($items as $it):
  $ver  = (string)($it['version'] ?? 'vNext');
  $date = (string)($it['date'] ?? '');
  $note = (string)($it['note'] ?? '');
  $ts   = strtotime($date) ?: time();
  $slug = strtolower(trim(preg_replace('~[^a-z0-9]+~i', '-', $ver), '-'));
  $link = $site['link'] . '#'. $slug;
?>
    <item>
      <title><?= $this->esc($ver) ?></title>
      <link><?= $this->esc($link) ?></link>
      <guid><?= $this->esc($link) ?></guid>
      <pubDate><?= gmdate('r', $ts) ?></pubDate>
      <description><![CDATA[<?= $note ?>]]></description>
    </item>
<?php endforeach; ?>
  </channel>
</rss>
<?php
        exit;
    }

    /** GET /changelog.atom */
    public function atom(): void
    {
        if (ob_get_level()) @ob_clean();
        header('Content-Type: application/atom+xml; charset=utf-8');

        $site = $this->site();
        $items = $this->loadUpdates();
        $updated = gmdate('c', strtotime($items[0]['date'] ?? 'now'));
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        ?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title><?= $this->esc($site['title']) ?></title>
  <id><?= $this->esc($site['self_atom']) ?></id>
  <updated><?= $this->esc($updated) ?></updated>
  <link rel="self" href="<?= $this->esc($site['self_atom']) ?>" />
  <link rel="alternate" href="<?= $this->esc($site['link']) ?>" />
  <subtitle><?= $this->esc($site['desc']) ?></subtitle>
<?php foreach ($items as $it):
  $ver  = (string)($it['version'] ?? 'vNext');
  $date = (string)($it['date'] ?? '');
  $note = (string)($it['note'] ?? '');
  $ts   = strtotime($date) ?: time();
  $slug = strtolower(trim(preg_replace('~[^a-z0-9]+~i', '-', $ver), '-'));
  $link = $site['link'] . '#'. $slug;
?>
  <entry>
    <title><?= $this->esc($ver) ?></title>
    <id><?= $this->esc($link) ?></id>
    <link rel="alternate" href="<?= $this->esc($link) ?>" />
    <updated><?= gmdate('c', $ts) ?></updated>
    <content type="html"><![CDATA[<?= $note ?>]]></content>
  </entry>
<?php endforeach; ?>
</feed>
<?php
        exit;
    }
}