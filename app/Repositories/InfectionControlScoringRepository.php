<?php

namespace App\Repositories;

use App\InfectionControlScoring;
use App\InfectionControlScoringDetail;
use App\InfectionControlScoringSubSectionDetail;
use App\Libs\Helper;
use App\Repositories\Abstracts\EloquentRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InfectionControlScoringRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return InfectionControlScoring::class;
   }

   public function list($conditions = [])
   {
      $limit = $conditions['limit'] ?? 20;
      $lmstart = $conditions['lmstart'] ?? 0;

      //Limit by branch
      $configBranchIds = Helper::getListOrgBranch();

      $branchIds = [];

      if ($configBranchIds && is_array($configBranchIds)) {
         foreach ($configBranchIds as $itemBranch) {
            if (!isset($itemBranch->BranchId) || empty($itemBranch->BranchId)) {
               continue;
            }
            $branchIds[] = $itemBranch->BranchId ?? 0;
         }
      }
      
      if (empty($branchIds) || count($branchIds) < 1) {
         return [];
      }

      $query = $this->_model->newQuery();

      if ($branchIds && count($branchIds) > 0) {
         $query->whereIn('BranchId', $branchIds);
      }

      if (isset($conditions['BranchId']) && $conditions['BranchId'] > 0) {
         $query->where('BranchId', $conditions['BranchId']);
      }

      if (isset($conditions['Type']) && $conditions['Type'] > 0) {
         $query->where('Type', $conditions['Type']);
      }

      if (isset($conditions['FromDate']) && !empty($conditions['FromDate'])) {
         $query->where('CreatedDate', '>=', $conditions['FromDate'] . '00:00:00');
      }

      if (isset($conditions['ToDate']) && !empty($conditions['ToDate'])) {
         $query->where('CreatedDate', '<=', $conditions['ToDate'] . ' 23:59:59');
      }

      $query->orderByDesc('CreatedDate');

      $query->with(['createdByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);

      $query->with(['branch' => function ($query) {
         $query->select('BranchId', 'Name', 'BranchCode');
      }]);

      $query->with(['details' => function ($query) {
         $query->with(['subSectionDetails']);
      }]);

      $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);

      return $result;
   }

   public function detail($id)
   {
      $query = $this->_model->newQuery();

      $query->where('Id', $id);

      $query->with(['createdByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);

      $query->with(['branch' => function ($query) {
         $query->select('BranchId', 'Name', 'BranchCode');
      }]);

      $query->with(['details' => function ($query) {
         $query->with(['subSectionDetails']);
      }]);

      return $query->first();
   }

   public function create($data = [])
   {
      $staffId = Auth::user()['StaffId'] ?? 0;

      $dataInfection = [
         'Type' => $data['Type'] ?? 0,
         'BranchId' => $data['BranchId'] ?? 0,
         'TotalScore' => $data['TotalScore'] ?? 0,
         'TotalTargetAchieved' => $data['TotalTargetAchieved'] ?? 0,
         'CreatedDate' => date('Y-m-d H:i:s'),
         'CreatedBy' => $staffId
      ];

      $dataInfectionDetails = [];

      if (isset($data['Detail']) && is_array($data['Detail'])) {
         foreach ($data['Detail'] as $val) {
            $dataInfectionDetails[] = [
               'Section' => $val['Section'] ?? '',
               'SubSection' => $val['SubSection'] ?? '',
               'CheckContent' => $val['CheckContent'] ?? '',
               'IsPassed' => $val['IsPassed'] ?? 0,
               'Note' => $val['Note'] ?? '',
               'Score' => $val['Score'] ?? 0,
               'AttachFiles' => $val['AttachFiles'] ?? [],
               'InfectionControlScoringId' => 0, //Bổ sung sau
            ];
         }
      }
      
      DB::beginTransaction();
      try {

         $infection = $this->_model->create($dataInfection);
         if (!$infection || empty($infection)) {
            DB::rollBack();
            return false;
         }
   
         if (count($dataInfectionDetails) > 0) {
            foreach ($dataInfectionDetails as $key => $val) {
               $dataInfectionDetails[$key]['InfectionControlScoringId'] = $infection->Id ?? 0;
               $val['InfectionControlScoringId'] = $infection->Id ?? 0;

               $infectionDetail = InfectionControlScoringDetail::create($val);
               if (!$infectionDetail || empty($infectionDetail)) {
                  DB::rollBack();
                  return false;
               }
               $dataInfectionControlScoringSubSectionDetails = [];
               if (isset($val['AttachFiles']) && is_array($val['AttachFiles'])) {
                  foreach ($val['AttachFiles'] as $attachFile) {
                     if (!isset($attachFile['URL']) || empty($attachFile['URL'])) {
                        continue;
                     }
                     $dataInfectionControlScoringSubSectionDetails[] = [
                        'InfectionControlScoringDetailId' => $infectionDetail->Id ?? 0,
                        'URL' => $attachFile['URL'] ?? '',
                        'FileName' => $attachFile['FileName'] ?? ''
                     ];
                  }
               }
               if (count($dataInfectionControlScoringSubSectionDetails) > 0) {
                  if (!InfectionControlScoringSubSectionDetail::insert($dataInfectionControlScoringSubSectionDetails)) {
                     DB::rollBack();
                     return false;
                  }
               }
            }
         }
         DB::commit();
         return true;
      } catch (\Exception $ex) {
         DB::rollBack();
         Log::error("create infection control fail: ".$ex->getMessage());
         return false;
      }
      DB::rollBack();
      return false;
   }
}
