<?php

namespace App\Console\Commands;

use App\Libs\ApiProcess;
use App\Libs\Factory;
use App\Repositories\CustomerPushToSocialRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PushCustomerToSocialCommand extends Command
{
    const ACCESS_TOKEN = 'e05a528516';

    const TEST_CODE = 'TEST2023';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:push_customer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crontab push customer to social network';

    protected $customerPushToSocialRepo;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(CustomerPushToSocialRepository $customerPushToSocialRepo)
    {
        parent::__construct();
        $this->customerPushToSocialRepo = $customerPushToSocialRepo;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $date = Carbon::now()->subDays(1)->toDateString();
        $customers = $this->customerPushToSocialRepo->getCustomerByChannel('facebook', $date);
        if (!$customers || empty($customers)) {
            return 0;
        }
        foreach ($customers as $customer) {
            $data = $this->convertCustomerToDate($customer);
            $this->sendCustomerInfoToNKK($data);
            $this->customerPushToSocialRepo->setCustomerPushed($customer->CustomerId ?? 0);
        }

        return 1;
    }

    protected function convertCustomerToDate($customer)
    {
        $data = [
            'access_token' => self::ACCESS_TOKEN,
            'test_code' => '',
            'status' => 104,
            'name_lead' => '',
            'name_customer' => $customer->FullName ?? '',
            'phone_lead' => '',
            'phone_customer' => $customer->Phone ?? '',
            'gender' => strtolower($customer->Gender ?? ''),
            'email' => '',
            'customer_id' => $customer->CustomerId ?? '',
            'payment' => (int) $customer->Payment ?? 0,
            'birthday' => date('Ymd', strtotime($customer->Birthday ?? date('Y-m-d'))) ?? '',
            'local_treatment' => $customer->LastestTreatmentProvince ?? '',
            'services' => $customer->Service ?? '',
            'campaign_code' => 'OVER_10MIL_PAYMENT',
        ];
        if (app()->environment() !== 'production') {
            $data['test_code'] = self::TEST_CODE;
        }
        
        return $data;
    }

    protected function sendCustomerInfoToNKK($data)
    {
        
        if (empty($data)) {
            return false;
        }
        $apiUrl = config('env.NKK_URL');
        if (!$apiUrl || empty($apiUrl)) {
            $apiUrl = 'https://nhakhoakim.com';
        }
		$endPoint = $apiUrl . '/wp-json/his/v1/crm2web?'.http_build_query($data);
		$remote = new ApiProcess();
		$remote->get()->from($endPoint)->execute();
		$response = $remote->getResponse();
        
        Log::info('[PushCustomerToSocialCommand]Send Customer ',[
            'data' => $data,
        	'response' => $response
		]);
        return true;
    }
}
