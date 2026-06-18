<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\OrthodonticRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OrthodonticCommand extends Command
{
    protected $signature = 'cron:buildSnapshot {--date= : Y-m-d, mặc định hôm nay}';
    protected $description = 'Refresh orthodontic dashboard snapshots';

    protected $orthodonticRepo;

    public function __construct(OrthodonticRepository $orthodonticRepo)
    {
        parent::__construct();
        $this->orthodonticRepo = $orthodonticRepo;
    }

    public function handle()
    {
        $date      = $this->option('date') ?? Carbon::now()->format('Y-m-d');
        $startedAt = Carbon::now();

        // Insert log record — status = running
        $logId = DB::table('pos.OrthoSnapshotLog')->insertGetId([
            'SnapshotDate' => $date,
            'Status'       => 'running',
            'TotalRows'    => 0,
            'StartedAt'    => $startedAt->format('Y-m-d H:i:s'),
            'CreatedAt'    => $startedAt->format('Y-m-d H:i:s'),
        ]);

        $this->info("[OrthoSnapshot] Starting for {$date} (LogId: {$logId})");
        Log::info("[OrthoSnapshot] Starting for {$date}", ['logId' => $logId]);

        $steps = [
            'KPI'            => 'buildKpiSnapshot',
            'Stage'          => 'buildStageSnapshot',
            'Priority'       => 'buildPrioritySnapshot',
            'Doctor'         => 'buildDoctorSnapshot',
            'NearCompletion' => 'buildNearCompletionSnapshot',
        ];

        $totalRows    = 0;
        $errorMessage = null;
        $hasError     = false;

        foreach ($steps as $label => $method) {
            try {
                $start  = microtime(true);
                $result = $this->orthodonticRepo->{$method}($date);
                $ms     = round((microtime(true) - $start) * 1000);

                $inserted   = $result['inserted'] ?? 0;
                $totalRows += $inserted;

                $msg = "[OrthoSnapshot] {$label}: {$inserted} rows ({$ms}ms)";
                $this->info($msg);
                Log::info($msg);
            } catch (\Throwable $e) {
                $msg = "[OrthoSnapshot] {$label} FAILED: " . $e->getMessage();
                $this->error($msg);
                Log::error($msg, ['exception' => $e]);

                $errorMessage = ($errorMessage ? $errorMessage . ' | ' : '') . "{$label}: " . $e->getMessage();
                $hasError     = true;
            }
        }

        $finishedAt  = Carbon::now();
        $durationSec = $finishedAt->diffInSeconds($startedAt);
        $status      = $hasError ? 'failed' : 'success';

        // Update log record
        DB::table('pos.OrthoSnapshotLog')
            ->where('Id', $logId)
            ->update([
                'Status'       => $status,
                'TotalRows'    => $totalRows,
                'FinishedAt'   => $finishedAt->format('Y-m-d H:i:s'),
                'DurationSec'  => $durationSec,
                'ErrorMessage' => $errorMessage,
            ]);

        $this->info("[OrthoSnapshot] {$status} — {$totalRows} rows, {$durationSec}s (LogId: {$logId})");
        Log::info("[OrthoSnapshot] {$status}", [
            'logId'       => $logId,
            'totalRows'   => $totalRows,
            'durationSec' => $durationSec,
            'error'       => $errorMessage,
        ]);

        return $hasError ? 1 : 0;
    }
}