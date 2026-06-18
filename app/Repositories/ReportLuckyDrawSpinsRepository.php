<?php

namespace App\Repositories;

use App\Exports\BaseReport;
use App\Exports\S3ExportStorage;
use App\LuckyDrawSpins;
use App\Repositories\Abstracts\EloquentRepository;
use Illuminate\Support\Facades\Auth;

class ReportLuckyDrawSpinsRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return LuckyDrawSpins::class;
   }

   public function getLuckyDrawSpins($condition)
   {
      $luckyDrawCampaignId = $condition['LuckyDrawCampaignId'];

      $query = $this->_model->newQuery();
      $query->select('LuckyDrawSpins.*', 'ldc.Name', 'c.CustomerCode', 'c.FullName');
      $query->join('pos.LuckyDrawCampaign as ldc', 'ldc.LuckyDrawCampaignId', '=', 'LuckyDrawSpins.LuckyDrawCampaignId');
      $query->join('pos.Customer as c', 'c.CustomerId', '=', 'LuckyDrawSpins.CustomerId');

      $query->with([
         'luckyDrawGiftType',
         'branch',
         'staff',
      ]);

      $query->where('ldc.LuckyDrawCampaignId', $luckyDrawCampaignId);
      $query->where('c.IsTest', '!=', 1);
      $query->whereNotNull('LuckyDrawSpins.ReceivedTime');

      $result = $query->get();

      return $result;
   }

   public function getLuckyDrawSpinsReport($condition)
   {
      $staffId = Auth::user()['StaffId'] ?? 0;
      $result = $this->getLuckyDrawSpins($condition);
      if (!$result || empty($result)) {
         return false;
      }

      $formatData = [];
      foreach ($result as $value) {
         $formatData[] = [
            'CustomerCode' => trim($value['CustomerCode']),
            'CustomerName' => trim($value['FullName']),
            'GiftName' => trim($value['luckyDrawGiftType']['Name']),
            'SpinTime' => $value['CreatedDate'],
            'CampaignName' => $value['Name'],
            'BranchCode' => trim($value['branch']['BranchCode']),
            'StaffSupport' => trim($value['staff']['FullName'])
         ];
      }

      if (count($formatData) > 0 && !empty($formatData)) {
         $headings = ['Mã khách hàng', 'Tên khách hàng', 'Giải thưởng', 'Thời gian quay thưởng', 'Đợt quay thưởng', 'Chi nhánh', 'Nhân viên hỗ trợ'];

         $fileExportName = 'Lucky_Draw_Spin_' . '_report_' . date('Y_m') . '_' . time() . '_' . $staffId . '.xlsx';
         $filePathExport = storage_path('app/excel') . '/' . $fileExportName;

         $saveDir = 'LuckyDrawSpin/exports';
         $s3Storage = new S3ExportStorage();

         $reportExport = new BaseReport($formatData);
         $reportExport->setStorage($s3Storage);
         $exportURL = $reportExport->setHeadings($headings)
            ->formatHeadings('A1:G1','FFFFFF', '4285F4')
            ->store('excel/' . $fileExportName)
            ->export($filePathExport, $saveDir);

         $reportExport->unlink($filePathExport);
         return $exportURL;
      }

      return false;
   }
}
