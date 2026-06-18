<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\ProfileStaffRepository;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RefreshStaffRatingCommand extends Command
{
    protected $signature   = 'cron:refreshStaffRating';
    protected $description = 'Refresh staff ranking per branch every 5 minutes (current month, top 5 by closing rate)';

    /** @var ProfileStaffRepository */
    protected $profileStaffRepo;

    public function __construct(ProfileStaffRepository $profileStaffRepo)
    {
        parent::__construct();
        $this->profileStaffRepo = $profileStaffRepo;
    }

    public function handle(): void
    {
        $currentHour          = (int) date('H');
        $currentMinute        = (int) date('i');
        $currentTimeInMinutes = $currentHour * 60 + $currentMinute;

        // Chỉ chạy từ 7:30 đến 22:00
        if ($currentTimeInMinutes < 450 || $currentTimeInMinutes >= 1320) {
            Log::info('RefreshStaffRatingCommand: Outside allowed time range (7:30–22:00), skipping.');
            return;
        }

        $fromDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $toDate   = Carbon::now()->format('Y-m-d');

        Log::info("RefreshStaffRatingCommand: Starting {$fromDate} → {$toDate}");

        $this->profileStaffRepo->refreshRatingCache($fromDate, $toDate);

        Log::info('RefreshStaffRatingCommand: Done.');
    }
}
