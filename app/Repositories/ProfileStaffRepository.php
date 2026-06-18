<?php

namespace App\Repositories;

use App\DashboardBranchServiceDaily;
use App\DashboardStaffEffectiveDaily;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ProfileStaffRepository
{
    const BENCH_MONTHS    = 3;    // số tháng gần kề dùng để tính TB
    const BENCH_CACHE_TTL = 3600; // 1 giờ

    const SERVICE_MAP = [
        'T' => 'general',
        'I' => 'implant',
        'C' => 'braces',
        'P' => 'ceramic',
    ];

    const SERVICE_LABEL = [
        'general' => 'Tổng quát',
        'implant' => 'Implant',
        'braces'  => 'Niềng răng',
        'ceramic' => 'Răng sứ',
    ];

    const RATING_TOP       = 5;
    const RATING_CACHE_TTL = 900; // 15 phút

    // =========================================================
    // Public — use case
    // =========================================================

    public function getStaffProfile($staffId, $branchId, $fromDate = null, $toDate = null)
    {
        try {
            $orgProfile = $this->findActiveOrgProfile($staffId, $branchId);
            if (!$orgProfile) {
                return ['error' => 'Nhân viên không có hồ sơ làm việc tại chi nhánh này'];
            }

            $fromDate = $fromDate ?? Carbon::now()->startOfMonth()->format('Y-m-d');
            $toDate   = $toDate   ?? Carbon::now()->format('Y-m-d');

            $staffStats      = $this->fetchStaffCurrentStats($staffId, $branchId, $fromDate, $toDate);
            $branchBenchmark = $this->fetchBranchBenchmark($branchId, $fromDate);

            return $this->buildResponse($staffId, $branchId, $fromDate, $toDate, $orgProfile, $staffStats, $branchBenchmark);

        } catch (\Throwable $e) {
            Log::error('ProfileStaffRepository@getStaffProfile: ' . $e->getMessage());
            return ['error' => 'Lỗi hệ thống'];
        }
    }

    public function getServiceAnalysis($staffId, $branchId, $fromDate = null, $toDate = null)
    {
        try {
            $fromDate = $fromDate ?? Carbon::now()->startOfMonth()->format('Y-m-d');
            $toDate   = $toDate   ?? Carbon::now()->format('Y-m-d');

            $staffRows   = $this->fetchStaffServiceStats($staffId, $branchId, $fromDate, $toDate);
            $branchBench = $this->loadServiceBench((int) $branchId, $fromDate);

            return $this->buildServiceResponse($fromDate, $toDate, $staffRows, $branchBench);

        } catch (\Throwable $e) {
            Log::error('ProfileStaffRepository@getServiceAnalysis: ' . $e->getMessage());
            return ['error' => 'Lỗi hệ thống'];
        }
    }

    public function getRevenueChart(int $staffId, int $branchId): array
    {
        try {
            $fromDate     = Carbon::now()->startOfMonth()->format('Y-m-d');
            $monthlyBench = $this->loadMonthlyBench($branchId, $fromDate);

            if (empty($monthlyBench)) {
                return ['chart' => []];
            }

            $yms      = array_keys($monthlyBench);
            $dateFrom = Carbon::createFromFormat('Y-m', reset($yms))->startOfMonth()->format('Y-m-d');
            $dateTo   = Carbon::createFromFormat('Y-m', end($yms))->endOfMonth()->format('Y-m-d');

            // Doanh thu của nhân viên trong đúng 3 tháng đó
            $staffRows = DashboardStaffEffectiveDaily::selectRaw("
                    DATE_FORMAT(SummaryDate, '%Y-%m') as YearMonth,
                    SUM(Revenue) as TotalRevenue
                ")
                ->where('BranchId', $branchId)
                ->where('StaffId', $staffId)
                ->whereBetween('SummaryDate', [$dateFrom, $dateTo])
                ->groupBy(DB::raw("DATE_FORMAT(SummaryDate, '%Y-%m')"))
                ->get()
                ->keyBy('YearMonth');

            $chart = [];
            foreach ($monthlyBench as $ym => $data) {
                $staffCount = max($data['staffCount'], 1);
                $month      = Carbon::createFromFormat('Y-m', $ym);

                $chart[] = [
                    'month'            => 'T' . $month->month . '/' . $month->year,
                    'yearMonth'        => $ym,
                    'staffRevenue'     => (float) ($staffRows[$ym]->TotalRevenue ?? 0),
                    'branchAvgRevenue' => (float) round($data['totalRevenue'] / $staffCount),
                ];
            }

            return ['chart' => $chart];

        } catch (\Throwable $e) {
            Log::error('ProfileStaffRepository@getRevenueChart: ' . $e->getMessage());
            return ['chart' => []];
        }
    }

    // =========================================================
    // Public — rating
    // =========================================================

    public function refreshRatingCache(string $fromDate, string $toDate): void
    {
        try {
            $rows = DashboardStaffEffectiveDaily::selectRaw('DISTINCT BranchId, StaffId')
                ->whereBetween('SummaryDate', [$fromDate, $toDate])
                ->where('SuccessCount', '>', 0)
                ->get();

            if ($rows->isEmpty()) {
                return;
            }

            $branchStaffMap = [];
            foreach ($rows as $row) {
                $branchStaffMap[$row->BranchId][] = $row->StaffId;
            }

            $yearMonth = Carbon::parse($fromDate)->format('Y-m');

            foreach ($branchStaffMap as $branchId => $staffIds) {
                $ranking  = $this->computeRanking((int) $branchId, $staffIds, $fromDate, $toDate);
                $cacheKey = "profile_staff:rating:{$branchId}:{$yearMonth}";
                Redis::setex($cacheKey, self::RATING_CACHE_TTL, json_encode($ranking));
            }

        } catch (\Throwable $e) {
            Log::error('ProfileStaffRepository@refreshRatingCache: ' . $e->getMessage());
        }
    }

    public function getRatingFromCache(int $branchId, int $currentStaffId): array
    {
        try {
            $yearMonth = Carbon::now()->format('Y-m');
            $cacheKey  = "profile_staff:rating:{$branchId}:{$yearMonth}";

            $cached = Redis::get($cacheKey);
            if (!$cached) {
                return [];
            }

            $fullList = json_decode($cached, true) ?? [];

            // Tìm vị trí của nhân viên hiện tại trong toàn bộ danh sách
            $myRank = 'Không xác định';
            foreach ($fullList as $item) {
                if ((int) $item['staffId'] === $currentStaffId) {
                    $myRank = $item['rank'];
                    break;
                }
            }

            // Chỉ trả top 5 để hiển thị, đánh dấu nhân viên hiện tại
            $top5 = array_slice($fullList, 0, self::RATING_TOP);
            foreach ($top5 as &$item) {
                $item['isCurrentUser'] = ((int) $item['staffId'] === $currentStaffId);
            }
            unset($item);

            return [
                'myRank' => $myRank,
                'list'   => $top5,
            ];

        } catch (\Throwable $e) {
            Log::error('ProfileStaffRepository@getRatingFromCache: ' . $e->getMessage());
            return [];
        }
    }

    // =========================================================
    // Private — lazy cache benchmark
    // =========================================================

    /**
     * Lazy cache: monthly stats của PK cho 3 tháng trước $fromDate.
     * Dùng chung cho /summary (closingRate, avgSessions, avgRevenue)
     * và /revenue-chart (avg revenue từng tháng).
     * Key: profile_staff:bench:monthly:{branchId}:{YYYY-MM}
     *
     * @return array<string, array{staffCount:int, totalConsult:int, totalSuccess:int, totalRevenue:float}>
     */
    private function loadMonthlyBench(int $branchId, string $fromDate): array
    {
        $yearMonth = Carbon::parse($fromDate)->format('Y-m');
        $cacheKey  = "profile_staff:bench:monthly:{$branchId}:{$yearMonth}";

        $cached = Redis::get($cacheKey);
        if ($cached !== null) {
            return json_decode($cached, true) ?? [];
        }

        [$historyStart, $historyEnd] = $this->precedingMonthsRange($fromDate);
        $rows = $this->fetchBranchMonthlyStats($branchId, $historyStart, $historyEnd);

        $data = [];
        foreach ($rows as $row) {
            $data[$row->Month] = [
                'staffCount'   => (int)   $row->StaffCount,
                'totalConsult' => (int)   $row->TotalConsult,
                'totalSuccess' => (int)   $row->TotalSuccess,
                'totalRevenue' => (float) $row->TotalRevenue,
            ];
        }

        Redis::setex($cacheKey, self::BENCH_CACHE_TTL, json_encode($data));
        return $data;
    }

    /**
     * Lazy cache: tỉ lệ chốt từng dịch vụ của PK cho 3 tháng trước $fromDate.
     * Dùng cho /services.
     * Key: profile_staff:bench:service:{branchId}:{YYYY-MM}
     *
     * @return array<string, array{consult:int, success:int}>
     */
    private function loadServiceBench(int $branchId, string $fromDate): array
    {
        $yearMonth = Carbon::parse($fromDate)->format('Y-m');
        $cacheKey  = "profile_staff:bench:service:{$branchId}:{$yearMonth}";

        $cached = Redis::get($cacheKey);
        if ($cached !== null) {
            return json_decode($cached, true) ?? [];
        }

        [$historyStart, $historyEnd] = $this->precedingMonthsRange($fromDate);

        $rows = DashboardBranchServiceDaily::selectRaw('
                ServiceCategoryId,
                SUM(ConsultCount) as TotalConsult,
                SUM(SuccessCount) as TotalSuccess
            ')
            ->where('BranchId', $branchId)
            ->whereBetween('SummaryDate', [$historyStart, $historyEnd])
            ->groupBy('ServiceCategoryId')
            ->get();

        $data = [];
        foreach ($rows as $row) {
            $data[$row->ServiceCategoryId] = [
                'consult' => (int) $row->TotalConsult,
                'success' => (int) $row->TotalSuccess,
            ];
        }

        Redis::setex($cacheKey, self::BENCH_CACHE_TTL, json_encode($data));
        return $data;
    }

    // =========================================================
    // Private — tính toán benchmark từ cached data
    // =========================================================

    private function fetchBranchBenchmark(int $branchId, string $fromDate): array
    {
        $monthlyData = $this->loadMonthlyBench($branchId, $fromDate);

        if (empty($monthlyData)) {
            return ['avgSessions' => 0, 'closingRate' => 0, 'avgRevenue' => 0];
        }

        $sumSessions     = 0.0;
        $sumClosingRate  = 0.0;
        $sumAvgRevenue   = 0.0;
        $count           = 0;

        foreach ($monthlyData as $data) {
            $staffCount = max($data['staffCount'], 1);
            $sumSessions    += $data['totalConsult'] / $staffCount;
            $sumClosingRate += $data['totalConsult'] > 0
                ? $data['totalSuccess'] / $data['totalConsult'] * 100
                : 0;
            $sumAvgRevenue  += $data['totalRevenue'] / $staffCount;
            $count++;
        }

        return [
            'avgSessions' => (int) round($sumSessions    / $count),
            'closingRate' => (int) round($sumClosingRate / $count),
            'avgRevenue'  => (int) round($sumAvgRevenue  / $count),
        ];
    }

    // =========================================================
    // Private — DB queries
    // =========================================================

    private function precedingMonthsRange(string $fromDate, int $months = self::BENCH_MONTHS): array
    {
        $monthStart = Carbon::parse($fromDate)->startOfMonth();
        return [
            $monthStart->copy()->subMonths($months)->format('Y-m-d'),
            $monthStart->copy()->subDay()->format('Y-m-d'),
        ];
    }

    private function fetchBranchMonthlyStats(int $branchId, string $historyStart, string $historyEnd)
    {
        return DashboardStaffEffectiveDaily::selectRaw("
                DATE_FORMAT(SummaryDate, '%Y-%m') as Month,
                COUNT(DISTINCT StaffId)           as StaffCount,
                SUM(ConsultCount)                 as TotalConsult,
                SUM(SuccessCount)                 as TotalSuccess,
                SUM(Revenue)                      as TotalRevenue
            ")
            ->where('BranchId', $branchId)
            ->whereBetween('SummaryDate', [$historyStart, $historyEnd])
            ->groupBy(DB::raw("DATE_FORMAT(SummaryDate, '%Y-%m')"))
            ->get();
    }

    private function fetchStaffCurrentStats($staffId, $branchId, string $fromDate, string $toDate)
    {
        return DashboardStaffEffectiveDaily::selectRaw('
                SUM(ConsultCount) as TotalConsult,
                SUM(SuccessCount) as TotalSuccess,
                SUM(Revenue)      as TotalRevenue
            ')
            ->where('BranchId', $branchId)
            ->where('StaffId', $staffId)
            ->whereBetween('SummaryDate', [$fromDate, $toDate])
            ->first();
    }

    private function fetchStaffServiceStats($staffId, $branchId, string $fromDate, string $toDate)
    {
        return DashboardStaffEffectiveDaily::selectRaw('
                ServiceCategoryId,
                SUM(ConsultCount) as TotalConsult,
                SUM(SuccessCount) as TotalSuccess,
                SUM(Revenue)      as TotalRevenue
            ')
            ->where('BranchId', $branchId)
            ->where('StaffId', $staffId)
            ->whereBetween('SummaryDate', [$fromDate, $toDate])
            ->groupBy('ServiceCategoryId')
            ->get();
    }

    private function findActiveOrgProfile($staffId, $branchId)
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');

        return DB::connection('mysql_in')
            ->table('WorkProfile as wp')
            ->join('OrgWorkProfile as owp', 'owp.WorkProfileId', '=', 'wp.WorkProfileId')
            ->join('Org as o', 'o.OrgId', '=', 'owp.OrgId')
            ->join('Branch as b', 'b.BranchId', '=', 'o.BranchId')
            ->join('WorkProfilePosition as wpp', 'wpp.WorkProfilePositionId', '=', 'owp.WorkProfilePositionId')
            ->where('wp.StaffId', $staffId)
            ->where('o.BranchId', $branchId)
            ->where('owp.Status', 1)
            ->where('owp.FromDate', '<=', $now)
            ->where('owp.ToDate', '>=', $now)
            ->select('o.BranchId', 'b.BranchCode', 'wpp.Name as PositionName')
            ->first();
    }

    // =========================================================
    // Private — rating helpers
    // =========================================================

    private function computeRanking(int $branchId, array $staffIds, string $fromDate, string $toDate): array
    {
        $stats = DashboardStaffEffectiveDaily::selectRaw('
                StaffId,
                SUM(ConsultCount) as TotalConsult,
                SUM(SuccessCount) as TotalSuccess,
                SUM(Revenue)      as TotalRevenue
            ')
            ->where('BranchId', $branchId)
            ->whereIn('StaffId', $staffIds)
            ->whereBetween('SummaryDate', [$fromDate, $toDate])
            ->groupBy('StaffId')
            ->get()
            ->keyBy('StaffId');

        $staffNames = DB::connection('mysql_in')
            ->table('Staff')
            ->whereIn('StaffId', $staffIds)
            ->pluck('FullName', 'StaffId');

        $list = [];
        foreach ($stats as $staffId => $row) {
            $consult = (int) $row->TotalConsult;
            $success = (int) $row->TotalSuccess;
            if ($consult === 0) continue;

            $list[] = [
                'staffId'     => (int) $staffId,
                'fullName'    => $staffNames[$staffId] ?? '',
                'successRate' => (int) round($success / $consult * 100),
                'revenue'     => (float) $row->TotalRevenue,
            ];
        }

        // Sort: doanh thu cao nhất → tỉ lệ chốt cao nhất nếu bằng nhau
        usort($list, function (array $a, array $b): int {
            if ($b['revenue'] !== $a['revenue']) {
                return $b['revenue'] <=> $a['revenue'];
            }
            return $b['successRate'] <=> $a['successRate'];
        });

        // Cache toàn bộ danh sách với rank
        foreach ($list as $i => &$item) {
            $item['rank'] = $i + 1;
        }
        unset($item);

        return $list;
    }

    // =========================================================
    // Private — build response
    // =========================================================

    private function buildResponse($staffId, $branchId, $fromDate, $toDate, $orgProfile, $staffStats, $benchmark): array
    {
        $consult     = (int)   ($staffStats->TotalConsult ?? 0);
        $success     = (int)   ($staffStats->TotalSuccess ?? 0);
        $revenue     = (float) ($staffStats->TotalRevenue ?? 0);
        $closingRate = $consult > 0 ? (int) round($success / $consult * 100) : 0;

        $month = Carbon::parse($fromDate)->month;

        return [
            'staffId'      => $staffId,
            'branchId'     => $branchId,
            'branchCode'   => $orgProfile->BranchCode,
            'positionName' => $orgProfile->PositionName,
            'period'       => ['from' => $fromDate, 'to' => $toDate],
            'totalConsultSessions' => [
                'label'     => "Tổng ca tư vấn T{$month}",
                'value'     => $consult,
                'branchAvg' => (int) $benchmark['avgSessions'],
            ],
            'closingRate' => [
                'label'     => 'Tỉ lệ chốt chung',
                'value'     => $closingRate,
                'branchAvg' => $benchmark['closingRate'],
            ],
            'consultRevenue' => [
                'label'     => "Doanh thu tư vấn T{$month}",
                'value'     => $revenue,
                'branchAvg' => (float) $benchmark['avgRevenue'],
            ],
            'capabilityScore' => [
                'label'     => 'Điểm năng lực tổng',
                'score'     => 62,
                'maxScore'  => 100,
                'note'      => 'Cần cải thiện 2 mảng',
                'prototype' => true,
            ],
        ];
    }

    private function buildServiceResponse(string $fromDate, string $toDate, $staffRows, array $branchBench): array
    {
        $staffData = $this->indexByCategory($staffRows);
        $services  = [];

        foreach (self::SERVICE_MAP as $dbKey => $outputKey) {
            if ($outputKey === 'general') continue;

            $consult = (int)   ($staffData[$dbKey]['consult']  ?? 0);
            $success = (int)   ($staffData[$dbKey]['success']  ?? 0);
            $revenue = (float) ($staffData[$dbKey]['revenue']  ?? 0);
            $rate    = $consult > 0 ? (int) round($success / $consult * 100) : 0;

            $bConsult      = (int) ($branchBench[$dbKey]['consult'] ?? 0);
            $bSuccess      = (int) ($branchBench[$dbKey]['success'] ?? 0);
            $branchAvgRate = $bConsult > 0 ? (int) round($bSuccess / $bConsult * 100) : 0;

            $evaluation = $this->evaluate($rate, $branchAvgRate, $outputKey);

            $services[$outputKey] = [
                'consult'       => $consult,
                'success'       => $success,
                'successRate'   => $rate,
                'revenue'       => $revenue,
                'branchAvgRate' => $branchAvgRate,
                'level'         => $evaluation['level'],
                'title'         => $evaluation['title'],
                'desc'          => $evaluation['desc'],
            ];
        }

        // Tổng quát = tổng tất cả category (T + I + C + P)
        $totalConsult = array_sum(array_column($staffData, 'consult'));
        $totalSuccess = array_sum(array_column($staffData, 'success'));
        $totalRevenue = array_sum(array_column($staffData, 'revenue'));

        $bAllConsult      = array_sum(array_column($branchBench, 'consult'));
        $bAllSuccess      = array_sum(array_column($branchBench, 'success'));
        $generalRate      = $totalConsult > 0 ? (int) round($totalSuccess / $totalConsult * 100) : 0;
        $generalBranchAvg = $bAllConsult   > 0 ? (int) round($bAllSuccess  / $bAllConsult  * 100) : 0;

        $evaluation = $this->evaluate($generalRate, $generalBranchAvg, 'general');

        $services['general'] = [
            'consult'       => $totalConsult,
            'success'       => $totalSuccess,
            'successRate'   => $generalRate,
            'revenue'       => (float) $totalRevenue,
            'branchAvgRate' => $generalBranchAvg,
            'level'         => $evaluation['level'],
            'title'         => $evaluation['title'],
            'desc'          => $evaluation['desc'],
        ];

        return [
            'period'   => ['from' => $fromDate, 'to' => $toDate],
            'services' => $services,
        ];
    }

    private function evaluate(int $rate, int $branchAvgRate, string $serviceKey): array
    {
        $serviceName = self::SERVICE_LABEL[$serviceKey] ?? $serviceKey;

        if ($rate > $branchAvgRate) {
            return [
                'level' => 'strong',
                'title' => 'Điểm mạnh',
                'desc'  => 'Xuất sắc — duy trì và chia sẻ kinh nghiệm cho đồng nghiệp',
            ];
        }

        if ($branchAvgRate > 0 && $rate >= (int) round($branchAvgRate * 0.5)) {
            return [
                'level' => 'average',
                'title' => 'Trung bình',
                'desc'  => 'Gần đạt TB — Học thêm để cải thiện tỉ lệ chốt',
            ];
        }

        $gap = $branchAvgRate - $rate;
        return [
            'level' => 'improve',
            'title' => 'Cần cải thiện',
            'desc'  => "Yếu hơn TB {$gap}% — Xem lộ trình đào tạo {$serviceName} ngay",
        ];
    }

    private function indexByCategory($rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row->ServiceCategoryId] = [
                'consult' => (int)   ($row->TotalConsult ?? 0),
                'success' => (int)   ($row->TotalSuccess ?? 0),
                'revenue' => (float) ($row->TotalRevenue ?? 0),
            ];
        }
        return $map;
    }

}
