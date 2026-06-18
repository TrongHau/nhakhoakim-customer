<?php

namespace App\Repositories;

use App\ExportMaterialTreatment;
use App\ExportMaterialTreatmentDetail;
use App\MaterialTreatment;
use App\Repositories\Abstracts\EloquentRepository;
use App\Treatment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaterialTreatmentRepository extends EloquentRepository
{
   const BEGIN_GET_DATA = '2025-08-20 00:00:00'; //Theo yêu cầu của anh Phú, lấy từ ngày này.
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return ExportMaterialTreatment::class;
   }

   public function findById($id)
   {
      if (!$id || empty($id)) {
         return null;
      }
      $query = $this->_model->where('ExportMaterialTreatmentId', $id);

      //Relationship
      $query->with(['branch' => function ($query) {
         $query->select('BranchId', 'Name', 'BranchCode');
      }]);

      $query->with(['createdByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);

      $query->with(['updatedByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);

      $query->with(['detail' => function ($query) {
         $query->with(['material', 'unit']);
      }]);

      $query->with(['treatment' => function ($query) {
         $query->select('TreatmentId', 'TreatmentCode', 'TreatmentNumber', 'PersonId');
         $query->with(['customer' => function ($query) {
            $query->select('CustomerId', 'FullName', 'CustomerCode', 'Address');
         }]);
      }]);

      return $query->first();
   }

   public function findByIdAndUpdatedDate($id, $currentUpdatedDate)
   {
      if (!$id || empty($id) || !$currentUpdatedDate || empty($currentUpdatedDate) ) {
         return null;
      }
      $query = $this->_model->where('ExportMaterialTreatmentId', $id);

      $query->where('EditAt', strtotime($currentUpdatedDate));

      return $query->first();
   }

   public function getMaterialTreatmentList($conditions)
   {
      $limit = $conditions['limit'] ?? 20;
      $lmstart = $conditions['lmstart'] ?? 0;

      $treatmentIds = [];

      if (isset($conditions['Keyword']) && strlen($conditions['Keyword']) > 0) {
         $keyword = $conditions['Keyword'] ?? '';
         $treatments = Treatment::whereHas('customer', function ($subQuery) use ($keyword) {
               return $subQuery->where('FullName', 'like', '%' . $keyword . '%')
                  ->orWhere('CustomerCode', 'like', '%' . $keyword . '%');
         })
         ->select('TreatmentId')
         ->get();
         if (!$treatments || empty($treatments) || $treatments->isEmpty()) {
            return [];
         }
         $treatmentIds = $treatments->pluck('TreatmentId')->toArray();
      }

      $query = $this->_model->newQuery();

      $query->where('AddAt', '>=', strtotime(self::BEGIN_GET_DATA));

      if ($treatmentIds && count($treatmentIds) > 0) {
         $query->whereIn('TreatmentId', $treatmentIds);
      }

      //Conditions
      if (isset($conditions['BranchId']) && $conditions['BranchId'] > 0) {
         $query->where('BranchId', $conditions['BranchId']);
      }

      if (isset($conditions['State']) && is_numeric($conditions['State'])) {
         $query->where('State', $conditions['State']);
      }

      if (isset($conditions['FromDate'])) {
         $conditions['FromDate'] = $conditions['FromDate'] . ' 00:00:00';
         $query->where('AddAt', '>=', strtotime($conditions['FromDate']));
      }

      if (isset($conditions['ToDate'])) {
         $conditions['ToDate'] = $conditions['ToDate'] . ' 23:59:59';
         $query->where('AddAt', '<=', strtotime($conditions['ToDate']));
      }

      //Order
      $query->orderByDesc('ExportMaterialTreatmentId');

      //Relationship
      $query->with(['branch' => function ($query) {
         $query->select('BranchId', 'Name', 'BranchCode');
      }]);

      $query->with(['createdByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);

      $query->with(['updatedByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);

      $query->with(['treatment' => function ($query) {
         $query->select('TreatmentId', 'TreatmentCode', 'TreatmentNumber', 'PersonId');
         $query->with(['customer' => function ($query) {
            $query->select('CustomerId', 'FullName', 'CustomerCode', 'Address');
         }]);
      }]);
      
      $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);

      return $result;
   }

   public function getListByTreatment($treatmentId, $conditions)
   {
      $limit = $conditions['limit'] ?? 20;
      $lmstart = $conditions['lmstart'] ?? 0;

      $query = $this->_model->where('TreatmentId', $treatmentId);

      $query->where('AddAt', '>=', strtotime(self::BEGIN_GET_DATA));

      $query->where('State','>', 0); //Get marterial active
      
      //Conditions
      if (isset($conditions['State']) && $conditions['State'] > 0) {
         $query->where('State', $conditions['State']);
      }

      if (isset($conditions['FromDate'])) {
         $conditions['FromDate'] = $conditions['FromDate'] . ' 00:00:00';
         $query->where('AddAt', '>=', strtotime($conditions['FromDate']));
      }

      if (isset($conditions['ToDate'])) {
         $conditions['ToDate'] = $conditions['ToDate'] . ' 23:59:59';
         $query->where('AddAt', '<=', strtotime($conditions['ToDate']));
      }

      //Order
      $query->orderByDesc('ExportMaterialTreatmentId');

      //Relationship
      $query->with(['branch' => function ($query) {
         $query->select('BranchId', 'Name', 'BranchCode');
      }]);

      $query->with(['createdByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);

      $query->with(['updatedByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);
      
      $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);

      return $result;
   }

   public function updateMaterialTreatment($id, $data)
   {
      if (!$id || empty($id) || !$data || empty($data)) {
         return null;
      }
      $staffId = Auth::user()['StaffId'] ?? 0;
      $dataUpdate = [
         'EditAt' => time(),
         'EditBy' => $staffId
      ];

      DB::beginTransaction();
      try {
         //Update state
         $updateState = $this->_model->where('ExportMaterialTreatmentId', $id)->update($dataUpdate);

         //Sync detail
         $this->syncDetail($id, $data['ExportMaterialTreatmentDetail'] ?? []);

         if ($updateState) {
            DB::commit();
            return true;
         }


      } catch (\Exception $e) {
         DB::rollBack();
         Log::error("updateMaterialTreatment errors", [$e->getMessage()]);
         return false;
      }
      DB::rollBack();
      return false;
      
   }

   public function confirmMaterialTreatment($id, $data)
   {
      if (!$id || empty($id) || !$data || empty($data)) {
         return null;
      }
      $staffId = Auth::user()['StaffId'] ?? 0;
      $dataUpdate = [
         'State' => 20, //Confirmed
         'EditAt' => time(),
         'EditBy' => $staffId
      ];

      DB::beginTransaction();
      try {
         //Update state
         $updateState = $this->_model->where('ExportMaterialTreatmentId', $id)->update($dataUpdate);

         //Sync detail
         $this->syncDetail($id, $data['ExportMaterialTreatmentDetail'] ?? []);

         if ($updateState) {
            DB::commit();
            return true;
         }


      } catch (\Exception $e) {
         DB::rollBack();
         Log::error("confirmMaterialTreatment errors", [$e->getMessage()]);
         return false;
      }
      DB::rollBack();
      return false;
      
   }

   public function confirmNotUsingMaterialTreatment($id, $data)
   {
      if (!$id || empty($id) || !$data || empty($data)) {
         return null;
      }
      $staffId = Auth::user()['StaffId'] ?? 0;
      $dataUpdate = [
         'State' => 50, //Not use
         'EditAt' => time(),
         'EditBy' => $staffId
      ];
      DB::beginTransaction();
      try {
         //Update state
         $updateState = $this->_model->where('ExportMaterialTreatmentId', $id)->update($dataUpdate);

         //Delete detail
         $this->syncDetail($id, []);

         if ($updateState) {
            DB::commit();
            return true;
         }

      } catch (\Exception $e) {
         DB::rollBack();
         Log::error("confirmNotUsingMaterialTreatment errors", [$e->getMessage()]);
         return false;
      }
      DB::rollBack();
      return false;
      
   }

   protected function syncDetail($id, $data = [])
   {
      if (!$id || empty($id) || !$data || empty($data)) {
         return null;
      }
      $updateDetail = ExportMaterialTreatmentDetail::where('ExportMaterialTreatmentId', $id)->delete();

      if (!$data || empty($data) || !is_array($data) || count($data) < 1) {
         return true;
      }

      $dataDetail = [];
      foreach ($data as $item) {
         if (!isset($item['MaterialId']) || empty($item['MaterialId'])) {
            continue;
         }
         $dataDetail[] = [
            'ExportMaterialTreatmentId' => $id,
            'MaterialId' => $item['MaterialId'] ?? 0,
            'Quantity' => $item['Quantity'] ?? 0,
            'RealQuantity' => $item['RealQuantity'] ?? 0,
            'UnitId' => $item['UnitId'] ?? 0
         ];
      }
      if (!empty($dataDetail) && count($dataDetail) > 0) {
         return ExportMaterialTreatmentDetail::insert($dataDetail);
      }
      return false;
   }

   public function findByTreatmentHistory($treatmentHistoryId)
   {
      if (!$treatmentHistoryId || empty($treatmentHistoryId)) {
         return null;
      }
      $query = $this->_model->where('TreatmentHistoryId', $treatmentHistoryId);

      //Relationship
      $query->with(['branch' => function ($query) {
         $query->select('BranchId', 'Name', 'BranchCode');
      }]);

      $query->with(['createdByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);

      $query->with(['updatedByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);

      $query->with(['detail' => function ($query) {
         $query->with(['material', 'unit']);
      }]);

      $query->with(['treatment' => function ($query) {
         $query->select('TreatmentId', 'TreatmentCode', 'TreatmentNumber', 'PersonId');
         $query->with(['customer' => function ($query) {
            $query->select('CustomerId', 'FullName', 'CustomerCode', 'Address');
         }]);
      }]);
      $query->orderByDesc('ExportMaterialTreatmentId');

      return $query->first();
   }
}
