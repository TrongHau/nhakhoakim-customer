<?php

namespace App\Repositories;

use App\Repositories\Abstracts\EloquentRepository;
use App\CustomerSMSQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CustomerSMSQueueRepository extends EloquentRepository
{
    protected function getModel()
    {
        return CustomerSMSQueue::class;
    }

    public function getPromotionCode() {
        $result = $this->_model->where('Status', 0)->select('PromotionCode')->distinct()->get()->toArray();
        return $result ?? [];
    }

    public function getCustomerSMSQueue($code){

        $result = $this->_model->where('Status', 0)->where('PromotionCode', $code)->select('CustomerSMSQueueId', 'PhoneNumber', 'PromotionCode','VoucherCode','Message','Sender','Status')->get()->toArray();

        return $result ?? [];
    }

    public function updateCustomerSMSQueue($customerSMSQueueId,$status){
        
        $result = $this->_model->where('CustomerSMSQueueId', $customerSMSQueueId)
            ->update([
                'Status' => $status,
                'UpdatedDate' => Carbon::now()->toDateTimeString(),
                'UpdatedBy' => 2267
            ]);

        return $result ?? false;
    }

    public  function updateCustomerSendSms($data)
    {
        $arr = [
            'SendAt' => time(),
            'Phone' => $data,
            'Status' => 1,
            'Ver' => 6,
            'countSMS' => 1
        ];

        DB::table('schedule.CustomerSendSms')->insert($arr);
        
        return true;
    }

}
