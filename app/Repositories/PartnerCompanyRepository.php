<?php

namespace App\Repositories;

use App\Branch;
use App\BranchPartnerCompany;
use App\PartnerCompany;
use App\Repositories\Abstracts\EloquentRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PartnerCompanyRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return PartnerCompany::class;
   }

   public function getAllInsuranceCompany()
   {
      $query = $this->_model->newQuery();
      $query->where('State', 1);
      $query->where('PartnerCompanyTypeId', 1);
      $query->orderBy('Ordering');
      return $query->get();
   }

   public function getInsuranceCompanyByBranch($conditions = [])
   {
      $lmstart = $conditions['lmstart'] ?? 0;
      $limit = $conditions['limit'] ?? 10;

      $query = Branch::where('State', 1);
      $query->whereNotIn('BranchCode', ['BO', 'PK']);
      $query->whereNotNull('BranchCode');
      $query->where('BranchCode','!=', '');
      $query->whereIn('CompanyId', [1,2,3]);
      if (isset($conditions['BranchId']) && !empty($conditions['BranchId'])) {
         $query->where('BranchId', $conditions['BranchId']);
      }
      if (isset($conditions['PartnerCompanyId']) && !empty($conditions['PartnerCompanyId'])) {
         $query->whereRaw('BranchId IN (SELECT BranchId FROM pos.BranchPartnerCompany bpc JOIN pos.PartnerCompany pc ON pc.PartnerCompanyId = bpc.PartnerCompanyId  WHERE bpc.PartnerCompanyId = ? AND pc.State = ?)', [$conditions['PartnerCompanyId'], 1]);
      }
      $query->orderBy('Priority');

      $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);

      if (!$result || empty($result)) {
         return $result;
      }

      foreach ($result as $item) {
         $partnerCompanies = [];
         if (isset($conditions['PartnerCompanyId']) && !empty($conditions['PartnerCompanyId'])) {
            $partnerCompanies = PartnerCompany::whereRaw('PartnerCompanyId IN (SELECT PartnerCompanyId FROM BranchPartnerCompany WHERE BranchId = ? AND  PartnerCompanyId = ? )', [$item->BranchId, $conditions['PartnerCompanyId']])->where('State', 1)->get();
         } else {
            $partnerCompanies = PartnerCompany::whereRaw('PartnerCompanyId IN (SELECT PartnerCompanyId FROM BranchPartnerCompany WHERE BranchId = ?)', [$item->BranchId])->where('State', 1)->get();
         }
         $item->PartnerCompanies = $partnerCompanies;
      }
      return $result;
   }

   public function syncBranchPartnerCompany($data)
   {
      $branchId = $data['BranchId'] ?? 0;
      $partnerCompanyIds = $data['PartnerCompanyId'] ?? [];
      $staffId = Auth::user()['StaffId'] ?? 0;

      if (!is_array($partnerCompanyIds)) {
         $partnerCompanyIds = [];
      }
      $currentPartnerCompany = BranchPartnerCompany::where('BranchId', $branchId)->get();
      $currentIds = array_column($currentPartnerCompany->toArray(), 'PartnerCompanyId');

      if (!empty($currentPartnerCompany)) {
         $newData = array_diff($partnerCompanyIds, $currentIds);
         $deleteData = array_diff($currentIds, $partnerCompanyIds);

         $addData = [];

         if (!empty($newData)) {
            foreach ($newData as $partnerCompanyId) {
               $addData[] = [
                  'BranchId' => $branchId ?? 0,
                  'PartnerCompanyId' => $partnerCompanyId,
                  'CreatedDate' => Carbon::now()->toDateTimeString(),
                  'CreatedBy' => $staffId ?? 0,
                  'UpdatedDate' => Carbon::now()->toDateTimeString(),
                  'UpdatedBy' => $staffId ?? 0,
               ];
            }
         }
         DB::beginTransaction();
         try {
            if (!empty($deleteData)) {
               BranchPartnerCompany::where('BranchId', $branchId)
                  ->whereIn('PartnerCompanyId', $deleteData)
                  ->delete();
            }

            if (!empty($addData)) {
               BranchPartnerCompany::insert($addData);
            }
         } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Sync Branch Partner Company at: " . [$e->getMessage()]);
            return false;
         }

         DB::commit();
         return true;
      }
      
   }
}
