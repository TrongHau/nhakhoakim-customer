<?php

namespace App\Console\Commands;

use App\Libs\Factory;
use App\Repositories\AppointmentRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AppointmentExtendCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:apppointment_extend';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crontab to convert appointment expired to lead CRM';

    /**
     * @var AppointmentRepository
     */
    protected $appointmentRepo;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(AppointmentRepository $appointmentRepo)
    {
        parent::__construct();
        $this->appointmentRepo = $appointmentRepo;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $date = date('Y-m-d', strtotime('-1 day'));
        $appointments = $this->appointmentRepo->getExpiredAppointmentExtends($date);
        if (!$appointments || empty($appointments)) {
            return 0;
        }
        foreach ($appointments as $appointment) {
            if (!isset($appointment->ExtraData) || empty($appointment->ExtraData)) {
                continue;
            }
            $this->sendAppointmentToCRM($appointment->ExtraData);
        }
        return 0;
    }

    private function sendAppointmentToCRM($appointment)
    {
        if (!$appointment || empty($appointment)) {
            return false;
        }
        // Send appointment to CRM
        Log::info("==== START Send Appointment Extend to CRM ===");
        $header['Authorization']  = 'Bearer ' . CRM_APP_TOKEN;
        $remote = Factory::getRemote();

        $data = array_merge($appointment, [
            'campaign_code' => date('y').'01_AppointmentExtend'
        ]);

        $remote->request('module')
                ->from(API_CRM_GATEWAY_CREATE_HOT_DATA . '?token=' . CRM_APP_TOKEN)
                ->where($data)
                ->execute(true, $header);
        $response = $remote->loadVar(false);

        Log::info("Remote StaffCrontabDailyCommand url:", [API_CRM_GATEWAY_CREATE_HOT_DATA]);
        Log::info("Remote StaffCrontabDailyCommand header:", $header);
        Log::info("Remote StaffCrontabDailyCommand response", [$response]);
        if ((isset($response->module->code ) && $response->module->code  == false) || !$response) {
            return false;
        }
        Log::info("==== END Send Appointment Extend to CRM ===");
        return 0;
    }
}
