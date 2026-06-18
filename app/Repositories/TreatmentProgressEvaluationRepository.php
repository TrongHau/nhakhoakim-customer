<?php

namespace App\Repositories;

use App\OrderDetail;
use App\TreatmentProgressEvaluation;
use App\Repositories\Abstracts\EloquentRepository;
use App\TreatmentMedicalProcedure;
use App\TreatmentProcedureProgress;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TreatmentProgressEvaluationRepository extends EloquentRepository
{
   const PROCESS_STATE = [
      'New' => 0,
      'Pass' => 1,
      'Over' => 10,
      'Late' => 20,
   ];

   const PROCRESS_INTERVAL = 25; //default 25 days

   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return TreatmentProgressEvaluation::class;
   }

   public function processEvaluationOrthodontic($id = 0, $data = [])
   {
      if (!$id || empty($id) || !$data || !is_array($data)) {
         return false;
      }
      $staffId = Auth::user()['StaffId'] ?? 0;

      $evaluation = $this->_model::find($id);
      if (!$evaluation) {
         Log::error("TreatmentProgressEvaluation with ID {$id} not found.");
         return false;
      }
      //Prepare data for update
      $dataUpdate = [
         'ActualEvaluationDate' => Carbon::now()->toDateTimeString(),
         'SelfEvaluation' => $data['SelfEvaluation'] ?? null,
         'ProcessState' => $data['ProcessState'] ?? 0,
         'DoctorStaffId' => $staffId,
         'CreatedBy' => $staffId,
         'CreatedDate' => Carbon::now()->toDateTimeString(),
      ];

      $dataInsert = [
         'TreatmentMedicalProcedureId' => $evaluation->TreatmentMedicalProcedureId ?? 0,
         'EstimatedEvaluationDate' => Carbon::now()->addDays($this->getProcessInterval($evaluation->TreatmentMedicalProcedureId ?? 0))->toDateTimeString(),
         'CustomerId' => $evaluation->CustomerId ?? 0,
         'DoctorStaffId' => $evaluation->DoctorStaffId ?? 0,
         'ProcessState' => self::PROCESS_STATE['New'],
         'CreatedBy' => $staffId,
         'CreatedDate' => Carbon::now()->toDateTimeString(),
      ];

      //Update the record
      DB::beginTransaction();
      try {
         $updateStatus = $this->_model::where('TreatmentProgressEvaluationId', $id)
            ->update($dataUpdate);

         $insertStatus = $this->_model::create($dataInsert);

         if ($updateStatus && $insertStatus) {
            DB::commit();
            return true;
         }
      } catch (\Exception $e) {
         DB::rollBack();
         // Log the error or handle it as needed
         Log::error("Error processing evaluation for ID {$id}: " . $e->getMessage());
         return false;
      }
      DB::rollBack();
      return false;
   }

   public function detailEvaluationOrthodontic($id = 0)
   {
      try {
         if (!$id || empty($id)) {
            return null;
         }
         $query = $this->_model->newQuery();
         $query->where('TreatmentProgressEvaluationId', $id);
         $query->join('Customer', 'Customer.CustomerId', '=', 'TreatmentProgressEvaluation.CustomerId');
         $query->select('Customer.CustomerCode','Customer.CustomerId');
         $result = $query->first();

         if (!$result || empty($result)) {
            return $result;
         }

         $data = DB::select('CALL pos.usp_TreatmentProgressEvaluation_Detail(?)', [$id]);
         if ($data || !empty($data)) {
            foreach ($data as $key => $item) {
               $item->History = $this->historyEvaluationOrthodontic($item->CustomerId ?? 0, $item->TreatmentMedicalProcedureId ?? 0);
            }
         }

         return $data;
      } catch (\Exception $e) {
         Log::error("Error detailEvaluationOrthodontic", [$e->getMessage()]);
         return [];
      }
   }

   public function historyEvaluationOrthodontic($customerId = 0, $treatmentMedicalProcedureId = 0)
   {
      if (!$customerId || empty($customerId)) {
         return [];
      }
      $query = $this->_model->newQuery();
      $query->select([
         'TreatmentProgressEvaluation.*',
         'ar.Status',
         DB::raw('IF(ar.Status = 2, "Xác nhận", "Từ chối") as StatusName'),
      ]);
      $query->where('TreatmentProgressEvaluation.CustomerId', $customerId);
      $query->where('TreatmentProgressEvaluation.TreatmentMedicalProcedureId', $treatmentMedicalProcedureId);
      $query->whereNotNull('TreatmentProgressEvaluation.ActualEvaluationDate');
      $query->whereNotNull('TreatmentProgressEvaluation.SelfEvaluation');
      $query->where('TreatmentProgressEvaluation.EstimatedEvaluationDate', '<=', Carbon::now()->endOfDay()->toDateTimeString());
      $query->leftJoin('TreatmentProcedureProgressApprovedRequest as ar', 'ar.Id', '=', 'TreatmentProgressEvaluation.TreatmentProcedureProgressApprovedRequestId');
      $query->with(['doctorStaff' => function ($query) {
         $query->select('StaffId', 'StaffCode', 'FullName');
      }]);
      $query->with(['service' => function ($query) {
         $query->select('ServiceId', 'ServiceCode', 'Name', 'Description');
      }]);
      $query->with(['service' => function ($query) {
         $query->select('Service.ServiceId', 'Service.ServiceCode', 'Service.Name', 'Service.Description');
      }]);
      $query->orderBy('TreatmentProgressEvaluation.ActualEvaluationDate', 'desc');
      $result = $query->get();

      if (!$result || $result->isEmpty()) {
         return $result;
      }
      
      foreach ($result as $item) {
         $item->ProcessStateName = $this->getProcessStateName($item->ProcessState ?? 0);
         if ($item->TreatmentProcedureProgressApprovedRequestId) {
            $item->ProcessStateName = $item->StatusName;
         }
      }

      return $result;
   }

   public function getEvaluationOrthodontic($conditions = [])
   {
      $staffId = 0; // Default to 0 to get evaluations for all doctors
      $processState = -1; // Default to -1 to get all states
      $isFollowing = 0; // Default to 0 to get all evaluations
      $branchId = 0; // Default to 0 to get evaluations from all branches
      $keyword = '';

      $lmstart = $conditions['lmstart'] ?? 0;
      $limit = $conditions['limit'] ?? 20;
      $page = ceil($lmstart / $limit) + 1;
      
      if (isset($conditions['StaffId']) && $conditions['StaffId'] > 0) {
         $staffId = $conditions['StaffId'];
      }
      if (isset($conditions['ProcessState']) && $conditions['ProcessState'] > 0) {
         $processState = $conditions['ProcessState'];
      }
      if (isset($conditions['IsFollowing']) && $conditions['IsFollowing'] > 0) {
         $isFollowing = $conditions['IsFollowing'];
      }
      if (isset($conditions['BranchId']) && $conditions['BranchId'] > 0) {
         $branchId = $conditions['BranchId'];
      }
      if (isset($conditions['Keyword']) && !empty($conditions['Keyword'])) {
         $keyword = base64_encode($conditions['Keyword']);
      }
      try {
         $data = DB::select('CALL pos.usp_TreatmentProgressEvaluation_List(?, ?, ?, ?, ?, ?, ?)', [
            $staffId,
            $processState,
            $isFollowing,
            $branchId,
            $keyword,
            $page,
            $limit
         ]);

         return $data;
      } catch (\Exception $e) {
         Log::error("Error getEvaluationOrthodonticForDoctor", [$e->getMessage()]);
         return [];
      }
      return [];
   }

   public function getEvaluationOrthodonticForDoctor($conditions = [])
   {
      $staffId = Auth::user()['StaffId'] ?? 0;
      $processState = -1; // Default to -1 to get all states
      $isFollowing = 0; // Default to 0 to get all evaluations
      $branchId = 0; // Default to 0 to get evaluations from all branches
      $keyword = '';

      $lmstart = $conditions['lmstart'] ?? 0;
      $limit = $conditions['limit'] ?? 20;
      $page = ceil($lmstart / $limit) + 1;

      if (isset($conditions['ProcessState']) && $conditions['ProcessState'] > 0) {
         $processState = $conditions['ProcessState'];
      }
      if (isset($conditions['IsFollowing']) && $conditions['IsFollowing'] > 0) {
         $isFollowing = $conditions['IsFollowing'];
      }
      if (isset($conditions['BranchId']) && $conditions['BranchId'] > 0) {
         $branchId = $conditions['BranchId'];
      }
      if (isset($conditions['Keyword']) && !empty($conditions['Keyword'])) {
         $keyword = base64_encode($conditions['Keyword']);
      }
      try {
         $data = DB::select('CALL pos.usp_TreatmentProgressEvaluation_List(?, ?, ?, ?, ?, ?, ?)', [
            $staffId,
            $processState,
            $isFollowing,
            $branchId,
            $keyword,
            $page,
            $limit
         ]);

         return $data;
      } catch (\Exception $e) {
         Log::error("Error getEvaluationOrthodonticForDoctor", [$e->getMessage()]);
         return [];
      }
      return [];
   }

   protected function getProcessInterval($id)
   {
      $interval = self::PROCRESS_INTERVAL;

      if (!$id || empty($id)) {
         return $interval;
      }
      //Get TreatmentMedicalProcedure by TreatmentMedicalProcedureId
      $medicaProcedure = TreatmentMedicalProcedure::where('TreatmentMedicalProcedureId', $id)
         ->join('Service', 'Service.ServiceId', '=', 'TreatmentMedicalProcedure.ServiceId')
         ->select('TreatmentMedicalProcedure.TreatmentMedicalProcedureId', 'TreatmentMedicalProcedure.ServiceId', 'Service.ProgressEvaluationInterval')
         ->first();
      if ($medicaProcedure && isset($medicaProcedure->ProgressEvaluationInterval) && $medicaProcedure->ProgressEvaluationInterval > 0) {
         return $medicaProcedure->ProgressEvaluationInterval ?? $interval;
      }

      return $interval;
   }

   protected function getProcessStateName($processState)
   {
      $processStateName = '';
      switch ($processState) {
         case self::PROCESS_STATE['New']:
            $processStateName = '';
            break;
         case self::PROCESS_STATE['Pass']:
            $processStateName = 'Đúng tiến độ';
            break;
         case self::PROCESS_STATE['Over']:
            $processStateName = 'Vượt tiến độ';
            break;
         case self::PROCESS_STATE['Late']:
            $processStateName = 'Trễ tiến độ';
            break;
         default:
            $processStateName = 'Không xác định';
      }
      return $processStateName;
   }
}
