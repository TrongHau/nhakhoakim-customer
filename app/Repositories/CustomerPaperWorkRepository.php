<?php

namespace App\Repositories;

use App\CustomerPaperWork;
use App\Repositories\Abstracts\EloquentRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Libs\Factory;
use Illuminate\Support\Facades\DB;

class CustomerPaperWorkRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return CustomerPaperWork::class;
   }

   public function getPaperWorkByCustomer($customerId)
   {
      //Check customer id
      if (!$customerId || empty($customerId)) {
         return [];
      }

      $query = $this->_model->newQuery();

      $query->select([
         'CustomerPaperWorkId',
         'CustomerId',
         'CustomerPaperTypeId',
         'PaperName',
         'PaperURL',
         'State',
         'CreatedDate',
         'CreatedBy',
         'UpdatedDate',
         'UpdatedBy'
      ]);

      //Get paper work by customer id
      $query->where('CustomerId', $customerId);

      $query->whereIn('State', [
         config('constants.customer.paper_work.state.new'),
         config('constants.customer.paper_work.state.processing'),
         config('constants.customer.paper_work.state.done')
      ]);

      //Relationships
      $query->with(['customerPaperType' => function ($query) {
         $query->select('CustomerPaperTypeId', 'Name');
      }]);
      $query->with(['createdByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);
      $query->with(['updatedByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);

      //return
      return $query->get();
   }

   public function saveCustomerPaperWork($data = [])
   {
      if (!$data || empty($data) || count($data) < 1) {
         return false;
      }
      $staffId = Auth::user()['StaffId'] ?? 0;
      $dataSave = [
         'CustomerId' => $data['CustomerId'] ?? 0,
         'CustomerPaperTypeId' => $data['CustomerPaperTypeId'] ?? 0,
         'PaperName' => $data['PaperName'] ?? '',
         'PaperContent' => $data['PaperContent'] ?? '',
         'State' => config('constants.customer.paper_work.state.new'),
         'Provider' => $data['Provider'] ?? 'DomPDF',
         'Landscape' => $data['Landscape'] ?? 0,
         'CreatedBy' => $staffId,
         'UpdatedBy' => $staffId,
         'CreatedDate' => date('Y-m-d H:i:s'),
         'UpdatedDate' => date('Y-m-d H:i:s')
      ];
      try {
         $paper = $this->_model->create($dataSave);
         if ($paper) {
            $this->sendExportPDF($paper->CustomerPaperWorkId ?? 0);
            return $paper;
         }
      } catch (\Exception $ex) {
         Log::error("Error saveCustomerPaperWork", [$ex->getMessage()]);
      }
      return false;
      
   }

   protected function sendExportPDF($customerPaperWorkId)
   {
      if (!$customerPaperWorkId || empty($customerPaperWorkId)) {
         return false;
      }
      try {
         Log::info("==== START Send Export Customer Paper Work: ".($customerPaperWorkId ?? 0)." ===");
         $header = ['Authorization'=>'Bearer ' . JWT_APP_TOKEN];
         $remote = Factory::getRemote();
         $remote->request('module')
            ->from(API_QUEUE_EXPORT_CUSTOMER_PAPER_WORK)
            ->where([
               'CustomerPaperWorkId' => $customerPaperWorkId ?? 0
            ])
            ->execute(true, $header);
         
         $response = $remote->loadVar(false);
         Log::info("Remote Send Export Customer Paper Work url:", [API_QUEUE_EXPORT_CUSTOMER_PAPER_WORK]);
         // Log::info("Remote Send Export Customer Paper Work header:", $header);
         // Log::info("Remote Send Export Customer Paper Work data:", $customerPaperWorkId);
         Log::info("Remote Send Export Customer Paper Work response", [$response]);
         if ((isset($response->code) && $response->code == false) || !$response) {
            return false;
         }
         return true;
         Log::info("==== END Send Export Customer Paper Work ===");
      } catch (\Exception $e) {
         Log::error("Error sendExportPDF", [$e->getMessage()]);
         return false;
      }
   }
}
