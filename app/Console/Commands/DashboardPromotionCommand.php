<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\DashboardRepository;
use App\JobExecutionLog;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardPromotionCommand extends Command
{
    protected $signature = 'cron:insertDashboardPromotion';
    protected $description = 'Insert dashboard promotion 22:45';

    protected $dashboardRepo;

    public function __construct(DashboardRepository $dashboardRepo)
    {
        parent::__construct();
        $this->dashboardRepo = $dashboardRepo;
    }

    public function handle()
    {
        $today = Carbon::now()->format('Y-m-d');
        $startTime = Carbon::now();

        Log::info('DashboardPromotionCommand: Starting refresh for ' . $today);

        // Tạo log record
        $jobLog = JobExecutionLog::create([
            'JobCode'          => 'DashboardPromotionCommand',
            'JobName'          => 'Insert Dashboard Promotion Data',
            'JobType'          => 'CRON',
            'RunDate'          => $today,
            'StartTime'        => $startTime,
            'Status'           => 'RUNNING',
            'RecordsProcessed' => 0,
            'RecordsFailed'    => 0,
            'RetryCount'       => 0,
            'TriggeredBy'      => 'SYSTEM',
            'CreatedDate'      => $startTime,
        ]);

        try {
            $result = $this->dashboardRepo->insertPromotionDashboard([
                'FromDate' => $today,
                'ToDate'   => $today,
            ]);

            $endTime = Carbon::now();
            $recordsProcessed = 0;

            // Tính tổng records processed từ result
            if (isset($result['details'])) {
                foreach ($result['details'] as $detail) {
                    if (isset($detail['inserted'])) {
                        $recordsProcessed += $detail['inserted'];
                    }
                    if (isset($detail['updated'])) {
                        $recordsProcessed += $detail['updated'];
                    }
                }
            }

            // Update log record với status success
            $jobLog->update([
                'EndTime'          => $endTime,
                'Status'           => $result['success'] ? 'SUCCESS' : 'FAILED',
                'RecordsProcessed' => $recordsProcessed,
                'ErrorMessage'     => $result['success'] ? null : ($result['message'] ?? 'Unknown error'),
                'ExtraData'        => json_encode($result),
            ]);

            Log::info('DashboardPromotionCommand: Done. Records processed: ' . $recordsProcessed);

        } catch (\Exception $e) {
            $endTime = Carbon::now();

            // Update log record với status failed
            $jobLog->update([
                'EndTime'      => $endTime,
                'Status'       => 'FAILED',
                'ErrorMessage' => $e->getMessage(),
                'ExtraData'    => json_encode([
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]),
            ]);

            Log::error('DashboardPromotionCommand: Failed - ' . $e->getMessage());
            throw $e;
        }
    }
}
