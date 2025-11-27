<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;
use Throwable;

final class ReportsController extends BaseController
{
    /**
     * Catalog of reports grouped by area.
     * GET /reports
     */
    public function index(array $ctx): void
    {
        $c    = $this->ctx($ctx);
        $base = rtrim((string)($c['module_base'] ?? '/apps/hotelflow'), '/');
        $today = date('Y-m-d');

        // ------------- Report catalog definition (2035-ready) -------------
        // key → slug for /reports/{key}
        $groups = [
            [
                'label'   => 'Frontdesk & occupancy',
                'code'    => 'frontdesk',
                'reports' => [
                    [
                        'key'   => 'arrivals',
                        'name'  => 'Arrivals & departures',
                        'tag'   => 'Daily ops',
                        'icon'  => 'fa-solid fa-plane-arrival',
                        'desc'  => 'Today’s and upcoming arrivals / departures by time, room and status.',
                    ],
                    [
                        'key'   => 'inhouse-guests',
                        'name'  => 'In-house guest list',
                        'tag'   => 'Live',
                        'icon'  => 'fa-solid fa-bed',
                        'desc'  => 'Current in-house guests including room, nights, balance and VIP status.',
                    ],
                    [
                        'key'   => 'occupancy-forecast',
                        'name'  => 'Occupancy & ADR forecast',
                        'tag'   => 'Forecast',
                        'icon'  => 'fa-solid fa-chart-line',
                        'desc'  => 'Forward-looking occupancy, ADR and RevPAR by date and room type.',
                    ],
                ],
            ],
            [
                'label'   => 'Revenue & sales',
                'code'    => 'revenue',
                'reports' => [
                    [
                        'key'   => 'daily-revenue',
                        'name'  => 'Daily revenue summary',
                        'tag'   => 'Finance',
                        'icon'  => 'fa-solid fa-bangladeshi-taka-sign',
                        'desc'  => 'Room, F&B and other revenue by day, including taxes and discounts.',
                    ],
                    [
                        'key'   => 'monthly-revenue',
                        'name'  => 'Month-to-date performance',
                        'tag'   => 'Owner view',
                        'icon'  => 'fa-regular fa-calendar-check',
                        'desc'  => 'Month-to-date revenue, ADR and RevPAR versus last year / budget.',
                    ],
                    [
                        'key'   => 'pickup',
                        'name'  => 'Pickup & booking pace',
                        'tag'   => 'Revenue',
                        'icon'  => 'fa-solid fa-forward-fast',
                        'desc'  => 'How bookings picked up over time for any arrival date range.',
                    ],
                ],
            ],
            [
                'label'   => 'Channels & source markets',
                'code'    => 'channels',
                'reports' => [
                    [
                        'key'   => 'channel-performance',
                        'name'  => 'Channel & OTA performance',
                        'tag'   => 'Distribution',
                        'icon'  => 'fa-solid fa-share-nodes',
                        'desc'  => 'Production, commission and net revenue by channel / OTA.',
                    ],
                    [
                        'key'   => 'source-market',
                        'name'  => 'Source market mix',
                        'tag'   => 'Market',
                        'icon'  => 'fa-solid fa-earth-asia',
                        'desc'  => 'Nights and revenue by country, city and source segment.',
                    ],
                    [
                        'key'   => 'rate-code-performance',
                        'name'  => 'Rate code performance',
                        'tag'   => 'Pricing',
                        'icon'  => 'fa-solid fa-tags',
                        'desc'  => 'Production and ADR by rate plan / package.',
                    ],
                ],
            ],
            [
                'label'   => 'Accounting & credit',
                'code'    => 'finance',
                'reports' => [
                    [
                        'key'   => 'payment-methods',
                        'name'  => 'Payment method breakdown',
                        'tag'   => 'Cashier',
                        'icon'  => 'fa-solid fa-money-check-dollar',
                        'desc'  => 'Collections by payment method (Cash, Card, MFS, Bank) and shift.',
                    ],
                    [
                        'key'   => 'ar-aging',
                        'name'  => 'AR aging by company / agent',
                        'tag'   => 'Credit',
                        'icon'  => 'fa-regular fa-file-invoice-dollar',
                        'desc'  => 'Outstanding balances grouped by age bucket and company profile.',
                    ],
                    [
                        'key'   => 'tax-summary',
                        'name'  => 'Tax summary',
                        'tag'   => 'Compliance',
                        'icon'  => 'fa-solid fa-scale-balanced',
                        'desc'  => 'Tax by type / code for any period ready for filing.',
                    ],
                ],
            ],
            [
                'label'   => 'Housekeeping & operations',
                'code'    => 'hk',
                'reports' => [
                    [
                        'key'   => 'hk-status',
                        'name'  => 'Housekeeping status',
                        'tag'   => 'HK',
                        'icon'  => 'fa-solid fa-broom',
                        'desc'  => 'Clean / dirty / out-of-order / discrepant rooms with timestamps.',
                    ],
                    [
                        'key'   => 'room-usage',
                        'name'  => 'Room usage & stay patterns',
                        'tag'   => 'Ops',
                        'icon'  => 'fa-regular fa-building',
                        'desc'  => 'Length of stay, single vs double occupancy and stay patterns by room.',
                    ],
                ],
            ],
            [
                'label'   => 'Night audit & security',
                'code'    => 'audit',
                'reports' => [
                    [
                        'key'   => 'night-audit',
                        'name'  => 'Night audit recap',
                        'tag'   => 'Audit',
                        'icon'  => 'fa-regular fa-moon',
                        'desc'  => 'Status of today’s audit, variance checks and control totals.',
                    ],
                    [
                        'key'   => 'cashier-audit',
                        'name'  => 'Cashier shift audit',
                        'tag'   => 'Control',
                        'icon'  => 'fa-solid fa-cash-register',
                        'desc'  => 'Open / close balances and variances per cashier shift.',
                    ],
                    [
                        'key'   => 'activity-log',
                        'name'  => 'User activity log',
                        'tag'   => 'Security',
                        'icon'  => 'fa-solid fa-user-shield',
                        'desc'  => 'Who changed what and when across reservations and folios.',
                    ],
                ],
            ],
        ];

        // Attach href for each report
        foreach ($groups as &$g) {
            foreach ($g['reports'] as &$r) {
                $r['href'] = $base.'/reports/'.$r['key'];
            }
        }
        unset($g, $r);

        $this->view('reports/index', [
            'title'  => 'Reports',
            'groups' => $groups,
            'today'  => $today,
        ], $c);
    }

    /**
     * Stub for specific report screen.
     * GET /reports/{key}
     */
    public function show(array $ctx, string $key): void
    {
        $c    = $this->ctx($ctx);
        $base = rtrim((string)($c['module_base'] ?? '/apps/hotelflow'), '/');

        // Same catalog as index (could be refactored later)
        $catalog = $this->flatCatalog($base);

        if (!isset($catalog[$key])) {
            $this->notFound('Report not found.');
            return;
        }

        $report = $catalog[$key];

        $this->view('reports/show', [
            'title'  => $report['name'].' report',
            'report' => $report,
            'today'  => date('Y-m-d'),
        ], $c);
    }

    /**
     * Helper: flatten catalog by key for show()
     * (keeps route+controller logic in one place for now).
     */
    private function flatCatalog(string $base): array
    {
        $groupsCtx = [];
        $dummyCtx  = [];
        // Reuse index config with minimal duplication
        // Simple trick: call index-config part here:

        // same structure as in index()
        $groups = [
            'frontdesk' => ['arrivals','inhouse-guests','occupancy-forecast'],
            'revenue'   => ['daily-revenue','monthly-revenue','pickup'],
            'channels'  => ['channel-performance','source-market','rate-code-performance'],
            'finance'   => ['payment-methods','ar-aging','tax-summary'],
            'hk'        => ['hk-status','room-usage'],
            'audit'     => ['night-audit','cashier-audit','activity-log'],
        ];

        // Minimal info so show() can work
        $meta = [
            'arrivals'              => ['name' => 'Arrivals & departures',              'icon' => 'fa-solid fa-plane-arrival',          'group' => 'Frontdesk & occupancy'],
            'inhouse-guests'        => ['name' => 'In-house guest list',               'icon' => 'fa-solid fa-bed',                    'group' => 'Frontdesk & occupancy'],
            'occupancy-forecast'    => ['name' => 'Occupancy & ADR forecast',          'icon' => 'fa-solid fa-chart-line',             'group' => 'Frontdesk & occupancy'],
            'daily-revenue'         => ['name' => 'Daily revenue summary',             'icon' => 'fa-solid fa-bangladeshi-taka-sign',  'group' => 'Revenue & sales'],
            'monthly-revenue'       => ['name' => 'Month-to-date performance',         'icon' => 'fa-regular fa-calendar-check',       'group' => 'Revenue & sales'],
            'pickup'                => ['name' => 'Pickup & booking pace',             'icon' => 'fa-solid fa-forward-fast',           'group' => 'Revenue & sales'],
            'channel-performance'   => ['name' => 'Channel & OTA performance',         'icon' => 'fa-solid fa-share-nodes',            'group' => 'Channels & source markets'],
            'source-market'         => ['name' => 'Source market mix',                 'icon' => 'fa-solid fa-earth-asia',             'group' => 'Channels & source markets'],
            'rate-code-performance' => ['name' => 'Rate code performance',             'icon' => 'fa-solid fa-tags',                   'group' => 'Channels & source markets'],
            'payment-methods'       => ['name' => 'Payment method breakdown',          'icon' => 'fa-solid fa-money-check-dollar',     'group' => 'Accounting & credit'],
            'ar-aging'              => ['name' => 'AR aging by company / agent',       'icon' => 'fa-regular fa-file-invoice-dollar',  'group' => 'Accounting & credit'],
            'tax-summary'           => ['name' => 'Tax summary',                       'icon' => 'fa-solid fa-scale-balanced',         'group' => 'Accounting & credit'],
            'hk-status'             => ['name' => 'Housekeeping status',               'icon' => 'fa-solid fa-broom',                  'group' => 'Housekeeping & operations'],
            'room-usage'            => ['name' => 'Room usage & stay patterns',        'icon' => 'fa-regular fa-building',             'group' => 'Housekeeping & operations'],
            'night-audit'           => ['name' => 'Night audit recap',                 'icon' => 'fa-regular fa-moon',                 'group' => 'Night audit & security'],
            'cashier-audit'         => ['name' => 'Cashier shift audit',               'icon' => 'fa-solid fa-cash-register',          'group' => 'Night audit & security'],
            'activity-log'          => ['name' => 'User activity log',                 'icon' => 'fa-solid fa-user-shield',            'group' => 'Night audit & security'],
        ];

        $out = [];
        foreach ($meta as $key => $info) {
            $out[$key] = [
                'key'   => $key,
                'name'  => $info['name'],
                'icon'  => $info['icon'],
                'group' => $info['group'],
                'href'  => $base.'/reports/'.$key,
            ];
        }
        return $out;
    }
}