<?php

namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Repositories\CustomerSMSQueueRepository;
use Illuminate\Support\Facades\Log;
use App\Libs\Helper;

class CustomerSMSQueueCommand extends Command
{
	protected $name = 'cron:sendSMS';
    protected $sender = null;
	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a new api cron send sms document';

	public function __construct()
	{
		parent::__construct();
	}
	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle()
	{
        $customerSMSQueueRepo = new CustomerSMSQueueRepository;
        $getPromotionCode = $customerSMSQueueRepo->getPromotionCode();
        $data = (object)[];
        $campaignName = '';
        $sender = '';
        $msg = '';
        $toDay = date('d-m-Y H:i:s');
        $dateCron = date('d-m-Y 11:00:00');
        if($toDay > date('d-m-Y 11:00:00')) {
            $dateCron = date('d-m-Y 18:00:00');
        }
        
        if($getPromotionCode){
            foreach($getPromotionCode as $key => $code) {
                $results = $customerSMSQueueRepo->getCustomerSMSQueue($code['PromotionCode']);
                $listData = [];
                if(count($results) > 0) {
                    foreach ($results as $result) {
                        $listData[] = [$result['PhoneNumber'],$result['VoucherCode']];
                        $campaignName = $result['PromotionCode'];
                        $sender = $result['Sender'];
                        $msg = $result['Message'];
                    }
                    $data->CampaignName = $campaignName;
                    // $data->ClientRequestId = $result['PromotionCode'].$result['PhoneNumber'];
                    $data->SentDate = $dateCron;
                    $data->Sender = $sender;
                    $data->Msg = $msg;
                    $data->ListData = $listData;
                    
                    $response = Helper::sendDataSMSToSouthTelecom($data);

                    Log::info("Data cron:sendSMS");
                    if($response) {
                        $response = json_decode($response);
                        if(isset($response->Status) && $response->Status == 1){
                            foreach($results as $v){
                                $r = $customerSMSQueueRepo->updateCustomerSMSQueue($v['CustomerSMSQueueId'],1);
                                $i = $customerSMSQueueRepo->updateCustomerSendSms($v['PhoneNumber']);
                            }
                        }
                    }
                }
            }
        }
        return true;
	}
}
