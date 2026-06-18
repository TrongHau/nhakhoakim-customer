<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\DashboardRepository;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RefreshDashboardCommand extends Command
{
    protected $signature = 'cron:refreshDashboard';
    protected $description = 'Refresh dashboard pre-aggregated data every 5 minutes';

    protected $dashboardRepo;

    public function __construct(DashboardRepository $dashboardRepo)
    {
        parent::__construct();
        $this->dashboardRepo = $dashboardRepo;
    }

    public function handle()
    {
        // Chỉ chạy từ 7:30 đến 22:00
        $currentHour = (int) date('H');
        $currentMinute = (int) date('i');
        $currentTimeInMinutes = ($currentHour * 60) + $currentMinute;

        $startTime = (7 * 60) + 30; // 7:30
        $endTime = 24 * 60;         // 22:00

        if ($currentTimeInMinutes < $startTime || $currentTimeInMinutes >= $endTime) {
            Log::info('RefreshDashboardCommand: Outside allowed time range (7:30-22:00), skipping.');
            return;
        }

        $today = Carbon::now()->format('Y-m-d');

        Log::info('RefreshDashboardCommand: Starting refresh for ' . $today);

        $this->dashboardRepo->refreshDashboardData([
            'FromDate' => $today,
            'ToDate'   => $today,
        ]);

        Log::info('RefreshDashboardCommand: Done.');
    }
}
