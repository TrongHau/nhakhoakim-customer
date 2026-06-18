<?php

namespace App\Repositories;

use App\Appointment;
use App\CustomerCare;
use App\CustomerCareAppointment;
use App\Libs\Helper;
use App\OrderDetail;
use App\Repositories\Abstracts\EloquentRepository;
use App\TreatmentProcedureProgress;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class CustomerCareRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return CustomerCareAppointment::class;
   }

   public function getCustomerCareConsulting($conditions = [])
   {
      $query = $this->_model->newQuery();

      $query->where('CustomerCareTypeId', 1); // 1 is Consulting

      if (isset($conditions['CustomerId']) && !empty($conditions['CustomerId'])) {
         if (!is_array($conditions['CustomerId'])) {
            $conditions['CustomerId'] = [$conditions['CustomerId']];
         }
         $query->whereIn('CustomerId', $conditions['CustomerId']);
      }

      $query->orderByDesc('CreatedDate');
      $query->orderByDesc('Id');

      $query->with(['consultingServiceGroups', 'declineReasons']);

      return $query->get();
   }

   public function getCustomerCareConsultingAll($request = [])
   {
      try {
         $branchId = isset($request['BranchId']) && trim($request['BranchId']) !== '' ? $request['BranchId'] : NULL;
         $serviceType = isset($request['ServiceType']) && trim($request['ServiceType']) !== '' ? $request['ServiceType'] : NULL;
         $keyword = isset($request['Keyword']) && trim($request['Keyword']) !== '' ? $request['Keyword'] : NULL;
         $lmstart = $request['lmstart'] ?? 0;
         $limit = $request['limit'] ?? 20;

         $data = DB::select('CALL pos.usp_CustomersNotAgreedForTreatmentGetList(?, ?, ?, ?, ?)', [
            $branchId,
            $serviceType,
            $keyword,
            $lmstart,
            $limit
         ]);

         return $data;
      } catch (\Exception $e) {
         Log::error("Error getCustomerCareConsultingAll", [$e->getMessage()]);
         return [];
      }
   }

   public function getCustomerCareConsultingByCustomer($conditions = [])
   {
      try {
         $day = Carbon::now()->format('Y-m-d');
         $time = Carbon::now()->format('H:i:s');
         // Chăm sóc sắp tới
         $data = [];
         $query = $this->_model->newQuery();
         $query->where('CustomerCareTypeId', 1); // 1 is Consulting
         if (!empty($conditions['CustomerId'])) {
            $query->where('CustomerId', $conditions['CustomerId']);
         }
         $query->where('Status', 0);
         $query->where(function ($q) use ($day, $time) {
            $q->where('AppointmentDate', '>', $day)
               ->orWhere(function ($q2) use ($day, $time) {
                  $q2->where('AppointmentDate', '=', $day)
                     ->where('AppointmentTime', '>', $time);
               });
         });
         $query->orderBy('AppointmentDate')->orderBy('AppointmentTime');
         $query->limit(1);
         $dataOne = $query->get()->toArray();

         // Chăm sóc gần nhất
         $queryNew = $this->_model->newQuery();
         $queryNew->where('CustomerCareTypeId', 1); // 1 is Consulting
         if (!empty($conditions['CustomerId'])) {
            $queryNew->where('CustomerId', $conditions['CustomerId']);
         }
         $queryNew->whereNotIn('Status', [0]);
         $queryNew->where(function ($qu) use ($day, $time) {
            $qu->where('AppointmentDate', '<', $day)
               ->orWhere(function ($qu2) use ($day, $time) {
                  $qu2->where('AppointmentDate', '=', $day)
                     ->where('AppointmentTime', '<=', $time);
               });
         });
         $queryNew->orderByDesc('AppointmentDate')->orderByDesc('AppointmentTime');

         $queryNew->limit(1);
         $dataTwo = $queryNew->get()->toArray();

         $data = array_merge($dataOne, $dataTwo);
         return $data;
      } catch (\Exception $e) {
         Log::error("Error getCustomerCareConsultingByCustomer", [$e->getMessage()]);
         return [];
      }
   }

   public function getCustomerCareTreatmentProgress($conditions = [])
   {
      // Default values
      $keyword = '';
      $branchId = '';
      $excludeBranchIds = '';
      $lmstart = $conditions['lmstart'] ?? 0;
      $limit = $conditions['limit'] ?? 20;
      $page = ceil($lmstart / $limit) + 1;

      // Check if conditions are set and assign values
      if (isset($conditions['Keyword']) && !empty($conditions['Keyword'])) {
         $keyword = base64_encode($conditions['Keyword']);
      }

      if (isset($conditions['BranchId']) && $conditions['BranchId'] > 0) {
         $branchId = (string) $conditions['BranchId'];
      }
      if (isset($conditions['ExcludeBranchIds']) && is_array($conditions['ExcludeBranchIds'])) {
         $excludeBranchIds = implode(',', $conditions['ExcludeBranchIds']);
      }

      // Call the stored procedure with the parameters
      $results = DB::select('CALL pos.usp_TreatmentProgressCustomer_List(?, ?, ?, ?, ?, ?)', [
         0,
         $branchId,
         $excludeBranchIds,
         $keyword,
         $page,
         $limit
      ]);

      // Check if results are empty
      if (!$results || empty($results)) {
         return [];
      }

      // Decode Services JSON if it exists
      foreach ($results as $item) {
         if (isset($item->Services) && !empty($item->Services) && Helper::isJSON($item->Services)) {
            $item->Services = json_decode($item->Services);
         } else {
            $item->Services = [];
         }
      }

      return $results;
      
   }

   public function getCustomerCareTreatmentProgressForDoctor($conditions = [])
   {
      // Default values
      $staffId = Auth::user()['StaffId'] ?? 0;
      $keyword = '';
      $branchId = '';
      $excludeBranchIds = '';
      $lmstart = $conditions['lmstart'] ?? 0;
      $limit = $conditions['limit'] ?? 20;
      $page = ceil($lmstart / $limit) + 1;

      // Check if conditions are set and assign values
      if (isset($conditions['Keyword']) && !empty($conditions['Keyword'])) {
         $keyword = base64_encode($conditions['Keyword']);
      }

      if (isset($conditions['BranchId']) && $conditions['BranchId'] > 0) {
         $branchId = $conditions['BranchId'];
      }
      if (isset($conditions['ExcludeBranchIds']) && is_array($conditions['ExcludeBranchIds'])) {
         $excludeBranchIds = implode(',', $conditions['ExcludeBranchIds']);
      }

      // Call the stored procedure with the parameters
      $results = DB::select('CALL pos.usp_TreatmentProgressCustomer_List(?, ?, ?, ?, ?, ?)', [
         $staffId,
         $branchId,
         $excludeBranchIds,
         $keyword,
         $page,
         $limit
      ]);

      // Check if results are empty
      if (!$results || empty($results)) {
         return [];
      }
      
      // Decode Services JSON if it exists
      foreach ($results as $item) {
         if (isset($item->Services) && !empty($item->Services) && Helper::isJSON($item->Services)) {
            $item->Services = json_decode($item->Services);
         } else {
            $item->Services = [];
         }
      }

      return $results;
      
   }
}
