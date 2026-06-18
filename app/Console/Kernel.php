<?php

namespace App\Console;

use App\Console\Commands\ControllerMakeCommand;
use App\Console\Commands\ApiGenerateCommand;
use App\Console\Commands\ConsoleMakeCommand;
use App\Console\Commands\KeyGenerateCommand;
use App\Console\Commands\MiddlewareMakeCommand;
use App\Console\Commands\ModelMakeCommand;
use App\Console\Commands\MakeRepositoryCommand;
use App\Console\Commands\TestMakeCommand;
use App\Libs\RedisLib;
use App\Repositories\ActivityRepository;
use App\Repositories\AppointmentRepository;
use App\Repositories\CampaignAssignRepository;
use App\Repositories\PhoneCallRepository;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Console\Factories\FactoryMakeCommand;
use App\Console\Commands\CustomerSMSQueueCommand;
use App\Console\Commands\PushCustomerToSocialCommand;
use App\Console\Commands\AppointmentExtendCommand;
use App\Console\Commands\RefreshDashboardCommand;
use App\Console\Commands\DashboardPromotionCommand;
use App\Console\Commands\OrthodonticCommand;
use App\Console\Commands\RefreshStaffRatingCommand;
class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
        ConsoleMakeCommand::class,
        FactoryMakeCommand::class,
        ModelMakeCommand::class,
        MakeRepositoryCommand::class,
        ControllerMakeCommand::class,
        MiddlewareMakeCommand::class,
        KeyGenerateCommand::class,
        ApiGenerateCommand::class,
        TestMakeCommand::class,
        CustomerSMSQueueCommand::class,
        PushCustomerToSocialCommand::class,
        AppointmentExtendCommand::class,
        RefreshDashboardCommand::class,
        DashboardPromotionCommand::class,
        OrthodonticCommand::class,
        RefreshStaffRatingCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $logPath = storage_path('logs/cron.log');

        // Test Cron Job
        $schedule->call(function () {
            Log::info('Cron Running');
        })->hourly();

        //Send request to CRM
        $schedule->command('cron:apppointment_extend')->dailyAt('08:00')->onOneServer();

        // Send request to STEL
        $schedule->command('cron:sendSMS')->dailyAt('09:00')->onOneServer();
        $schedule->command('cron:sendSMS')->dailyAt('15:00')->onOneServer();

        // Refresh dashboard data every 5 minutes
        $schedule->command('cron:refreshDashboard')->everyFiveMinutes()->onOneServer();
        $schedule->command('cron:insertDashboardPromotion')->dailyAt('22:45')->onOneServer();
        $schedule->command('cron:buildSnapshot')->dailyAt('06:45')->onOneServer();

        // Refresh staff ranking per branch every 10 minutes
        $schedule->command('cron:refreshStaffRating')->everyTenMinutes()->onOneServer();
    }

    /**
     * Get the timezone that should be used by default for scheduled events.
     *
     * @return \DateTimeZone|string|null
     */
    protected function scheduleTimezone()
    {
        return env('APP_TIMEZONE');
    }
}