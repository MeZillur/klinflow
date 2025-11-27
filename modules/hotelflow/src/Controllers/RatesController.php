<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;
use Throwable;

final class RatesController extends BaseController
{
    /* ---------------------------------------------------------------------
     * Small helpers
     * ------------------------------------------------------------------- */

    /** Safe ctx passthrough (delegates to BaseController if present). */
    private function normCtx(array $ctx): array
    {
        return \method_exists(parent::class, 'ctx')
            ? parent::ctx($ctx)
            : $ctx;
    }

    /** Check if a table exists in the current database. */
    private function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $sql = "
                SELECT 1
                  FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = :t
                 LIMIT 1
            ";
            $st = $pdo->prepare($sql);
            $st->execute([':t' => $table]);
            return (bool) $st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Run a SELECT safely, always returning an array. */
    private function fetchAllSafe(PDO $pdo, string $sql, array $params = []): array
    {
        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Central place for DDL snippets (for UI “help” blocks). */
    private function ddlMap(): array
    {
        return [

            'hms_rate_availability' => <<<SQL
CREATE TABLE hms_rate_availability (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_id        INT UNSIGNED      NOT NULL,
  room_type_id  BIGINT UNSIGNED   NOT NULL,
  `date`        DATE              NOT NULL,
  allotment     INT               NOT NULL DEFAULT 0,
  sold          INT               NOT NULL DEFAULT 0,
  closed        TINYINT(1)        NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_rt_date (org_id, room_type_id, `date`),
  KEY idx_org (org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,

            'hms_rate_plans' => <<<SQL
CREATE TABLE hms_rate_plans (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_id      INT UNSIGNED      NOT NULL,
  name        VARCHAR(120)      NOT NULL,
  code        VARCHAR(30)       NULL,
  currency    VARCHAR(8)        NULL,
  created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,

            'hms_rate_overrides' => <<<SQL
CREATE TABLE hms_rate_overrides (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_id       INT UNSIGNED    NOT NULL,
  room_type_id BIGINT UNSIGNED NULL,
  rate_plan_id BIGINT UNSIGNED NULL,
  start_date   DATE            NOT NULL,
  end_date     DATE            NOT NULL,
  price        DECIMAL(12,2)   NOT NULL DEFAULT 0,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_org (org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,

            'hms_rate_restrictions' => <<<SQL
CREATE TABLE hms_rate_restrictions (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_id       INT UNSIGNED    NOT NULL,
  room_type_id BIGINT UNSIGNED NULL,
  start_date   DATE            NOT NULL,
  end_date     DATE            NOT NULL,
  cta          TINYINT(1)      NOT NULL DEFAULT 0,
  ctd          TINYINT(1)      NOT NULL DEFAULT 0,
  min_los      INT             NULL,
  max_los      INT             NULL,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_org (org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,

            'hms_rate_allotments' => <<<SQL
CREATE TABLE hms_rate_allotments (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_id       INT UNSIGNED    NOT NULL,
  room_type_id BIGINT UNSIGNED NULL,
  partner      VARCHAR(160)    NULL,
  from_date    DATE            NOT NULL,
  to_date      DATE            NOT NULL,
  allotment    INT             NOT NULL DEFAULT 0,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_org (org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,

            'hms_yield_rules' => <<<SQL
CREATE TABLE hms_yield_rules (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_id      INT UNSIGNED   NOT NULL,
  name        VARCHAR(160)   NOT NULL,
  rule_type   VARCHAR(60)    NOT NULL, -- e.g. occupancy, pickup
  threshold   DECIMAL(6,2)   NOT NULL DEFAULT 0, -- e.g. 85.00 (percent)
  action_json JSON           NULL,
  created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_org (org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
        ];
    }

    /* ---------------------------------------------------------------------
     * /rates – ARI home (cards for Availability / Rate Plans / etc.)
     * ------------------------------------------------------------------- */
    public function index(array $ctx): void
    {
        $c   = $this->normCtx($ctx);
        $pdo = $this->pdo();

        $schema = [
            'room_types'        => $this->tableExists($pdo, 'hms_room_types'),
            'rooms'             => $this->tableExists($pdo, 'hms_rooms'),
            'rate_plans'        => $this->tableExists($pdo, 'hms_rate_plans'),
            'rate_availability' => $this->tableExists($pdo, 'hms_rate_availability'),
            'rate_overrides'    => $this->tableExists($pdo, 'hms_rate_overrides'),
            'rate_restrictions' => $this->tableExists($pdo, 'hms_rate_restrictions'),
            'rate_allotments'   => $this->tableExists($pdo, 'hms_rate_allotments'),
            'yield_rules'       => $this->tableExists($pdo, 'hms_yield_rules'),
        ];

        // Simple config for UI cards (the view will render tiles / links).
        $sections = [
            [
                'key'   => 'availability',
                'href'  => 'rates/availability',
                'label' => 'Availability Calendar',
                'desc'  => 'Day-by-day allotment, sold and close-out per room type.',
            ],
            [
                'key'   => 'plans',
                'href'  => 'rates/rate-plans',
                'label' => 'Rate Plans',
                'desc'  => 'BAR, corporate, OTA and promo plans with currencies.',
            ],
            [
                'key'   => 'overrides',
                'href'  => 'rates/overrides',
                'label' => 'Overrides & Seasons',
                'desc'  => 'Seasonal prices and promotional overrides.',
            ],
            [
                'key'   => 'restrictions',
                'href'  => 'rates/restrictions',
                'label' => 'Restrictions (CTA/CTD/LOS)',
                'desc'  => 'Control arrival, departure and length-of-stay rules.',
            ],
            [
                'key'   => 'allotments',
                'href'  => 'rates/allotments',
                'label' => 'Allotments',
                'desc'  => 'Block inventory for partners and group contracts.',
            ],
            [
                'key'   => 'yield',
                'href'  => 'rates/yield-rules',
                'label' => 'Yield Rules',
                'desc'  => 'Future: dynamic pricing based on pickup / occupancy.',
            ],
        ];

        $this->view('rates/index', [
            'title'    => 'Rates & Availability',
            'sections' => $sections,
            'schema'   => $schema,
        ], $c);
    }

    /* ---------------------------------------------------------------------
     * Availability Calendar
     * GET /rates/availability
     * ------------------------------------------------------------------- */
    public function availability(array $ctx): void
    {
        $c   = $this->normCtx($ctx);
        $pdo = $this->pdo();
        $orgId = (int) ($c['org_id'] ?? 0);

        // Month selector (YYYY-MM)
        $ym = \preg_replace('~[^0-9\-]~', '', (string) ($_GET['ym'] ?? ''));
        if (!\preg_match('~^\d{4}\-\d{2}$~', $ym)) {
            $ym = date('Y-m');
        }

        $first = $ym . '-01';
        $days  = (int) date('t', strtotime($first));
        $last  = date('Y-m-d', strtotime($first . ' +' . $days . ' day'));

        // Availability data (if table exists)
        $data = [];
        if ($this->tableExists($pdo, 'hms_rate_availability')) {
            $sql = "
                SELECT room_type_id, `date`, allotment, sold, closed
                  FROM hms_rate_availability
                 WHERE org_id = :o
                   AND `date` BETWEEN :s AND :e
                 ORDER BY room_type_id, `date`
            ";
            $data = $this->fetchAllSafe($pdo, $sql, [
                ':o' => $orgId,
                ':s' => $first,
                ':e' => $last,
            ]);
        }

        // Room types (optional)
        $roomTypes = [];
        if ($this->tableExists($pdo, 'hms_room_types')) {
            $roomTypes = $this->fetchAllSafe(
                $pdo,
                "SELECT id, name FROM hms_room_types WHERE org_id = :o ORDER BY name",
                [':o' => $orgId]
            );
        }

        $ddl = $this->ddlMap();

        $this->view('rates/availability', [
            'title'     => 'Availability Calendar',
            'ym'        => $ym,
            'days'      => $days,
            'first'     => $first,
            'roomTypes' => $roomTypes,
            'data'      => $data,
            'ddl'       => [
                'hms_rate_availability' => $ddl['hms_rate_availability'] ?? null,
            ],
        ], $c);
    }

    /* ---------------------------------------------------------------------
     * Rate Plans
     * GET /rates/rate-plans
     * ------------------------------------------------------------------- */
    public function ratePlans(array $ctx): void
    {
        $c   = $this->normCtx($ctx);
        $pdo = $this->pdo();
        $orgId = (int) ($c['org_id'] ?? 0);

        $rows = [];
        if ($this->tableExists($pdo, 'hms_rate_plans')) {
            $rows = $this->fetchAllSafe(
                $pdo,
                "SELECT id, name, code, currency
                   FROM hms_rate_plans
                  WHERE org_id = :o
                  ORDER BY name",
                [':o' => $orgId]
            );
        }

        $ddl = $this->ddlMap();

        $this->view('rates/rate-plans', [
            'title' => 'Rate Plans',
            'rows'  => $rows,
            'ddl'   => [
                'hms_rate_plans' => $ddl['hms_rate_plans'] ?? null,
            ],
        ], $c);
    }

    /* ---------------------------------------------------------------------
     * Overrides (Seasons / Special Prices)
     * GET /rates/overrides
     * ------------------------------------------------------------------- */
    public function overrides(array $ctx): void
    {
        $c   = $this->normCtx($ctx);
        $pdo = $this->pdo();
        $orgId = (int) ($c['org_id'] ?? 0);

        $rows = [];
        if ($this->tableExists($pdo, 'hms_rate_overrides')) {
            $rows = $this->fetchAllSafe(
                $pdo,
                "SELECT id, room_type_id, rate_plan_id, start_date, end_date, price
                   FROM hms_rate_overrides
                  WHERE org_id = :o
                  ORDER BY id DESC",
                [':o' => $orgId]
            );
        }

        $ddl = $this->ddlMap();

        $this->view('rates/overrides', [
            'title' => 'Overrides / Seasons',
            'rows'  => $rows,
            'ddl'   => [
                'hms_rate_overrides' => $ddl['hms_rate_overrides'] ?? null,
            ],
        ], $c);
    }

    /* ---------------------------------------------------------------------
     * Restrictions (CTA / CTD / LOS)
     * GET /rates/restrictions
     * ------------------------------------------------------------------- */
    public function restrictions(array $ctx): void
    {
        $c   = $this->normCtx($ctx);
        $pdo = $this->pdo();
        $orgId = (int) ($c['org_id'] ?? 0);

        $rows = [];
        if ($this->tableExists($pdo, 'hms_rate_restrictions')) {
            $rows = $this->fetchAllSafe(
                $pdo,
                "SELECT id, room_type_id, start_date, end_date, cta, ctd, min_los, max_los
                   FROM hms_rate_restrictions
                  WHERE org_id = :o
                  ORDER BY id DESC",
                [':o' => $orgId]
            );
        }

        $ddl = $this->ddlMap();

        $this->view('rates/restrictions', [
            'title' => 'Restrictions (CTA / CTD / LOS)',
            'rows'  => $rows,
            'ddl'   => [
                'hms_rate_restrictions' => $ddl['hms_rate_restrictions'] ?? null,
            ],
        ], $c);
    }

    /* ---------------------------------------------------------------------
     * Allotments (Partners / Contracts)
     * GET /rates/allotments
     * ------------------------------------------------------------------- */
    public function allotments(array $ctx): void
    {
        $c   = $this->normCtx($ctx);
        $pdo = $this->pdo();
        $orgId = (int) ($c['org_id'] ?? 0);

        $rows = [];
        if ($this->tableExists($pdo, 'hms_rate_allotments')) {
            $rows = $this->fetchAllSafe(
                $pdo,
                "SELECT id, room_type_id, partner, from_date, to_date, allotment
                   FROM hms_rate_allotments
                  WHERE org_id = :o
                  ORDER BY id DESC",
                [':o' => $orgId]
            );
        }

        $ddl = $this->ddlMap();

        $this->view('rates/allotments', [
            'title' => 'Allotments',
            'rows'  => $rows,
            'ddl'   => [
                'hms_rate_allotments' => $ddl['hms_rate_allotments'] ?? null,
            ],
        ], $c);
    }

    /* ---------------------------------------------------------------------
     * Yield Rules (future dynamic pricing)
     * GET /rates/yield-rules
     * ------------------------------------------------------------------- */
    public function yieldRules(array $ctx): void
    {
        $c   = $this->normCtx($ctx);
        $pdo = $this->pdo();
        $orgId = (int) ($c['org_id'] ?? 0);

        $rows = [];
        if ($this->tableExists($pdo, 'hms_yield_rules')) {
            $rows = $this->fetchAllSafe(
                $pdo,
                "SELECT id, name, rule_type, threshold, action_json
                   FROM hms_yield_rules
                  WHERE org_id = :o
                  ORDER BY id DESC",
                [':o' => $orgId]
            );
        }

        $ddl = $this->ddlMap();

        $this->view('rates/yield-rules', [
            'title' => 'Yield Rules',
            'rows'  => $rows,
            'ddl'   => [
                'hms_yield_rules' => $ddl['hms_yield_rules'] ?? null,
            ],
        ], $c);
    }
}