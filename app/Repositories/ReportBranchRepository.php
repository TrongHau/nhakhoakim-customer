<?php

namespace App\Repositories;

use App\Libs\Factory;
use App\Libs\Helper;
use App\ParamConfig;
use App\ReportBranch;
use App\ReportBranchDaily;
use App\ReportBranchDailyComment;
use App\Repositories\Abstracts\EloquentRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportBranchRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return ReportBranchDaily::class;
   }

   public function getDailyActive($conditions)
   {
      $lmstart = $conditions['lmstart'] ?? 0;
      $limit = $conditions['limit'] ?? 10;
      $currentWorkProfilePositionId = $conditions['CurrentWorkProfilePositionId'] ?? 10;

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
      

      $query = $this->_model->newQuery();

      $query->select('ReportBranchDaily.*');

      $query->join('in.Branch', 'Branch.BranchId', '=', 'ReportBranchDaily.BranchId');

      $query->where(function ($subQuery) {
         $beginMonth = Carbon::now()->startOfMonth()->format('Y-m-d');
         $endMonth = Carbon::now()->endOfMonth()->format('Y-m-d');
         $subQuery->whereBetween('ReportBranchDaily.DateReport', [$beginMonth, $endMonth]);
         $subQuery->orWhereNull('ReportBranchDaily.Content');
      });

      $query->whereIn('ReportBranchDaily.BranchId', $branchIds);

      if (isset($conditions['BranchId']) && !empty($conditions['BranchId'])) {
         $query->where('ReportBranchDaily.BranchId', $conditions['BranchId']);
      }

      $query->orderBy('ReportBranchDaily.DateReport', 'DESC');
      $query->orderBy('Branch.Priority', 'ASC');

      $query->with(['branch' => function ($query) {
         $query->select('BranchId', 'BranchCode', 'Name', 'Address', 'Priority');
      }]);

      $query->with(['createdByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);
      $query->with(['updatedByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);
      $query->with(['reportedByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);
      $query->with(['doctorAssistantStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);
      $query->with(['comments' => function ($query) {
         $query->with(['createdByStaff' => function ($query) {
            $query->select('StaffId', 'FullName', 'StaffCode');
         }]);
      }]);

      $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);

      if (!$result || empty($result)) {
         return $result;
      }
      //Get 
      $branchIds = [];
      foreach ($result as $item) {
         if (!isset($item->BranchId) || empty($item->BranchId)) {
            continue;
         }
         $branchIds[] = $item->BranchId;
      }
      $branchIds = array_unique($branchIds);
      if (count($branchIds) < 1) {
         return $result;
      }
      if (is_array($branchIds)) {
         $branchIds = implode(',', $branchIds);
      }
      try {
         $today = date('Y-m-d');
         $sqlQueryReport = "CALL `pos`.usp_Report_Branch_Daily('".$branchIds."', '".$today."')";
         $reports = DB::select(DB::raw($sqlQueryReport));
         if (!$reports || empty($reports)) {
            return $result;
         }
         foreach ($result as $item) {
            foreach ($reports as $report) {
               if ($item->BranchId == $report->BranchId && $item->DateReport == $today) {
                  if (isset($report->TotalAmount)) {
                     $item->TotalCash = $report->TotalAmount ?? 0.0;
                  }
                  if (isset($report->TotalApp)) {
                     $item->TotalTraffic = $report->TotalApp ?? 0;
                  }
                  if (isset($report->TotalNewVisitor)) {
                     $item->ConsultingAppointmentTotal = $report->TotalNewVisitor ?? 0;
                  }
                  if (isset($report->TotalRevenue)) {
                     $item->TotalRevenue = $report->TotalRevenue ?? 0.0;
                  }
                  break;
               }
            }
         }

         //CreateReportBranchDaily - Tạo báo cáo hằng ngày của QLPK
         $reportBranchDailyBranchIds = [];
         $configBranchIds = Helper::getListOrgBranch('CreateReportBranchDaily', $currentWorkProfilePositionId);
         if ($configBranchIds && is_array($configBranchIds)) {
            foreach ($configBranchIds as $itemBranch) {
               if (!isset($itemBranch->BranchId) || empty($itemBranch->BranchId)) {
                  continue;
               }
               $reportBranchDailyBranchIds[] = $itemBranch->BranchId ?? 0;
            }
         }
         //CreateDoctorAssistantReportBranchDaily - Tạo báo cáo hằng ngày của Phụ tá
         $reportDABranchDailyBranchIds = [];
         $configBranchIds = Helper::getListOrgBranch('CreateDoctorAssistantReportBranchDaily', $currentWorkProfilePositionId);
         if ($configBranchIds && is_array($configBranchIds)) {
            foreach ($configBranchIds as $itemBranch) {
               if (!isset($itemBranch->BranchId) || empty($itemBranch->BranchId)) {
                  continue;
               }
               $reportDABranchDailyBranchIds[] = $itemBranch->BranchId ?? 0;
            }
         }         

         //Check allow report
         foreach ($result as $item) {
            $item->IsAllowSupervisorReport = false;
            $item->IsAllowDoctorAssistantReport = false;
            if (!isset($item->BranchId)) {
               continue;
            }
            if (in_array($item->BranchId, $reportBranchDailyBranchIds) && empty($item->Content)) {
               $item->IsAllowSupervisorReport = true;
            }
            if (in_array($item->BranchId, $reportDABranchDailyBranchIds) && empty($item->DoctorAssistantContent)) {
               $item->IsAllowDoctorAssistantReport = true;
            }
         }
      } catch (\Exception $e) {
         Log::error("Error getDailyActive: " . $e->getMessage());
         Log::error("Error getDailyActive query: " . $sqlQueryReport);
         return $result;
      }
      
      return $result;
   }

   public function getDailyHistory($conditions)
   {
      $lmstart = $conditions['lmstart'] ?? 0;
      $limit = $conditions['limit'] ?? 10;
      
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

      $query = $this->_model->newQuery();

      $query->select('ReportBranchDaily.*');

      $query->join('in.Branch', 'Branch.BranchId', '=', 'ReportBranchDaily.BranchId');

      $query->where(function ($subQuery) {
         $subQuery->where('ReportBranchDaily.DateReport', '<', date('Y-m-01'));
         $subQuery->whereNotNull('ReportBranchDaily.Content');
      });

      $query->whereIn('ReportBranchDaily.BranchId', $branchIds);

      if (isset($conditions['BranchId']) && !empty($conditions['BranchId'])) {
         $query->where('ReportBranchDaily.BranchId', $conditions['BranchId']);
      }

      $query->orderBy('ReportBranchDaily.DateReport', 'DESC');
      $query->orderBy('Branch.Priority', 'ASC');

      $query->with(['branch' => function ($query) {
         $query->select('BranchId', 'BranchCode', 'Name', 'Address', 'Priority');
      }]);

      $query->with(['createdByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);
      $query->with(['updatedByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);
      $query->with(['reportedByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);
      $query->with(['doctorAssistantStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);
      $query->with(['comments' => function ($query) {
         $query->with(['createdByStaff' => function ($query) {
            $query->select('StaffId', 'FullName', 'StaffCode');
         }]);
      }]);

      $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);

      return $result;
   }

   public function getDailyDetail($reportBranchDailyId)
   {
      if (!$reportBranchDailyId || empty($reportBranchDailyId)) {
         return false;
      }
      $query = $this->_model->newQuery();

      $query->where('ReportBranchDailyId', $reportBranchDailyId);

      $query->with(['branch' => function ($query) {
         $query->select('BranchId', 'BranchCode', 'Name', 'Address', 'Priority');
      }]);
      $query->with(['createdByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);
      $query->with(['updatedByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);
      $query->with(['reportedByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);
      $query->with(['doctorAssistantStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);
      $query->with(['comments' => function ($query) {
         $query->with(['createdByStaff' => function ($query) {
            $query->select('StaffId', 'FullName', 'StaffCode');
         }]);
      }]);

      return $query->first();
   }

   public function createContentReport($reportBranchDailyId, $content)
   {
      if (!$reportBranchDailyId || empty($reportBranchDailyId)) {
         return false;
      }
      $staffId = Auth::user()['StaffId'] ?? 0;
      $dataUpdate = [
         'Content' => $content,
         'ReportedByStaffId' => $staffId,
         'ReportedTime' => date('Y-m-d H:i:s'),
         'UpdatedBy' => $staffId,
         'UpdatedDate' => date('Y-m-d H:i:s')
      ];
      return $this->_model->where('ReportBranchDailyId', $reportBranchDailyId)->update($dataUpdate);
   }

   public function createCommentReport($reportBranchDailyId, $comment)
   {
      if (!$reportBranchDailyId || empty($reportBranchDailyId)) {
         return false;
      }
      $staffId = Auth::user()['StaffId'] ?? 0;
      $data = [
         'ReportBranchDailyId' => $reportBranchDailyId,
         'CommentDetail' => $comment,
         'CreatedBy' => $staffId,
         'CreatedDate' => date('Y-m-d H:i:s')
      ];
      return ReportBranchDailyComment::create($data);
   }

   public function createDoctorAssistantContentReport($reportBranchDailyId, $content)
   {
      if (!$reportBranchDailyId || empty($reportBranchDailyId)) {
         return false;
      }
      $staffId = Auth::user()['StaffId'] ?? 0;
      $dataUpdate = [
         'DoctorAssistantContent' => $content,
         'DoctorAssistantStaffId' => $staffId,
         'ReportedDoctorAssistantTime' => date('Y-m-d H:i:s'),
         'UpdatedBy' => $staffId,
         'UpdatedDate' => date('Y-m-d H:i:s')
      ];
      return $this->_model->where('ReportBranchDailyId', $reportBranchDailyId)->update($dataUpdate);
   }

   public function checkAllowCommentReport($reportBranchDailyId)
   {
      if (!$reportBranchDailyId || empty($reportBranchDailyId)) {
         return false;
      }
      $configLimitDate = 7;
      $paramConfigDate = ParamConfig::where('ObjectCode', 'ReportBranchDailyLockCommentDate')->first();
      if ($paramConfigDate && isset($paramConfigDate->ObjectValue)) {
         $configLimitDate = (int) $paramConfigDate->ObjectValue ?? 0;
      }

      $query = $this->_model->newQuery();

      $query->where(function ($subQuery){
         //Report history
         $subQuery->where(function($subSubQuery) {
            $beginMonth = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
            $endMonth = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');
            $subSubQuery->whereBetween('DateReport', [$beginMonth, $endMonth]);
            $subSubQuery->whereNotNull('Content');
         });
         //Report active
         $subQuery->orWhere(function($subSubQuery) {
            $beginMonth = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endMonth = Carbon::now()->endOfMonth()->format('Y-m-d');
            $subSubQuery->whereBetween('DateReport', [$beginMonth, $endMonth]);
            $subSubQuery->orWhereNull('Content');
         });
        
      });
      
      $query->where('ReportBranchDailyId', $reportBranchDailyId);
      
      $reportBranchDaily = $query->first();

      if (!$reportBranchDaily || empty($reportBranchDaily)) {
         return false;
      }

      $dateReport = $reportBranchDaily->DateReport ?? date('Y-m-d');
      $dateReport = Carbon::parse($dateReport)->endOfMonth()->addDays($configLimitDate);
      if (Carbon::now()->endOfDay() > $dateReport->endOfDay()) {
         return false;
      }
      return true;

   }


}
