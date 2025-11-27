<?php
namespace Modules\POS\Services;

interface ReportService
{
    public function salesSummary(int $org_id, string $from, string $to, array $filters = []): array;
    public function exportSalesCsv(int $org_id, string $from, string $to, array $filters = []): string; // returns file path or CSV string
}