<?php

namespace App\Repositories;

use App\Exports\BaseReport;
use App\Exports\S3ExportStorage;
use App\OrderDetail;
use App\Repositories\Abstracts\EloquentRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderDetailRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return OrderDetail::class;
   }

   public function exportConsultedServicesReport($conditions)
   {
      $result = $this->getConsultedServicesReport($conditions);

      if (!$result || empty($result)) {
         return false;
      }

      $staffId = Auth::user()['StaffId'] ?? 0;
      $formatData = [];

      foreach ($result as $value) {
         $formatData[] = [
            'BranchCode' => trim($value->BranchCode),
            'ChangedAt' => Carbon::parse($value->ChangedAt)->format('Y-m-d'),
            'CustomerCode' => $value->CustomerCode,
            'CustomerName' => $value->CustomerName,
            'ServiceCode' => $value->ServiceCode,
            'ServiceName' => $value->ServiceName,
            'AnatomyBodyPartItemName' => $value->AnatomyBodyPartItemName,
            'ServiceType' => $value->ServiceType,
            'StaffCode' => $value->StaffCode,
            'StaffName' => $value->StaffName,
            'ServicePrice' => $value->ServicePrice,
            'DiscountAmount' => $value->DiscountAmount,
            'PriceAfterDiscount' => $value->PriceAfterDiscount,
         ];
      }

      if (count($formatData) > 0 && !empty($formatData)) {
         $headings = ['Chi nhánh', 'Ngày tư vấn', 'Mã KH', 'Tên KH', 'Mã dịch vụ', 'Tên dịch vụ', 'Vị trí', 'Loại dịch vụ', 'Mã nhân viên', 'Tên nhân viên', 'Giá dịch vụ (VNĐ)', 'Giảm giá (VNĐ)', 'Giá sau giảm (VNĐ)'];

         $fileExportName = 'DichVuDaLen' . '_report_' . date('Y_m') . '_' . time() . '_' . $staffId . '.xlsx';
         $filePathExport = storage_path('app/excel') . '/' . $fileExportName;

         $saveDir = 'MedicalCouncilReport/exports';
         $s3Storage = new S3ExportStorage();

         $reportExport = new BaseReport($formatData);
         $reportExport->setStorage($s3Storage);
         $exportURL = $reportExport->setHeadings($headings)
            ->formatHeadings('A1:M1','FFFFFF', '4285F4')
            ->setNumberFormat('K2:M9000', 'integer')
            ->store('excel/' . $fileExportName)
            ->export($filePathExport, $saveDir, $fileExportName);

         $reportExport->unlink($filePathExport);
         return $exportURL;
      }
   }

   public function getConsultedServicesReport($conditions)
   {
      $fromDate = Carbon::parse($conditions['FromDate'])->startOfDay()->timestamp;
      $toDate = Carbon::parse($conditions['ToDate'])->endOfDay()->timestamp;

      DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
      $query = $this->_model->newQuery();
      $result = $query->join('OrderChanging as oc', function ($join) use ($fromDate, $toDate) {
               $join->on('oc.OrderChangingId', '=', 'OrderDetail.OrderChangingId')
                  ->whereBetween('oc.ChangedAt', [$fromDate, $toDate]);
         })
         ->join('in.Staff as st', 'st.StaffId', '=', 'oc.ChangedBy')
         ->join('in.Branch as b', 'b.BranchId', '=', 'OrderDetail.ConsultedBranchId')
         ->join('Treatment as t', 't.TreatmentId', '=', 'OrderDetail.TreatmentId')
         ->join('Customer as c', 'c.CustomerId', '=', 't.PersonId')
         ->join('Service as sv', 'sv.ServiceId', '=', 'OrderDetail.ServiceId')
         ->where('OrderDetail.Status', '!=', 0)
         ->select([
               'b.BranchCode',
               'oc.ChangedAt',
               'c.CustomerCode',
               'c.FullName as CustomerName',
               'OrderDetail.ServiceCode',
               'OrderDetail.ServiceName',
               'OrderDetail.AnatomyBodyPartItemName',
               'sv.OrthodonticLevel',
               'sv.WarrantyType',
               'st.StaffCode',
               'st.FullName as StaffName',
               'OrderDetail.ServicePrice',
               'OrderDetail.DiscountAmount',
               'OrderDetail.Amount as PriceAfterDiscount',
         ])
         ->get();
      DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

      foreach ($result as $row) {
         $row->ServiceType = $this->detectServiceType(
               $row->OrthodonticLevel,
               $row->WarrantyType
         );
      }

      return $result;
   }

   public function exportConsultedServicesByConsultedDateReport($conditions)
   {
      $result = $this->getConsultedServicesByConsultedDate($conditions);

      if (!$result || empty($result)) {
         return false;
      }

      $staffId = Auth::user()['StaffId'] ?? 0;
      $formatData = [];

      foreach ($result as $value) {
         $formatData[] = [
            'BranchCode' => trim($value->BranchCode),
            'ConsultedDate' => Carbon::parse($value->ConsultedDate)->format('Y-m-d'),
            'CustomerCode' => $value->CustomerCode,
            'CustomerName' => $value->CustomerName,
            'ServiceCode' => $value->ServiceCode,
            'ServiceName' => $value->ServiceName,
            'AnatomyBodyPartItemName' => $value->AnatomyBodyPartItemName,
            'ServiceType' => $value->ServiceType,
            'StaffCode' => $value->StaffCode,
            'StaffName' => $value->StaffName,
            'ServicePrice' => $value->ServicePrice,
            'DiscountAmount' => $value->DiscountAmount,
            'PriceAfterDiscount' => $value->PriceAfterDiscount,
         ];
      }

      if (count($formatData) > 0 && !empty($formatData)) {
         $headings = ['Chi nhánh', 'Ngày tư vấn', 'Mã KH', 'Tên KH', 'Mã dịch vụ', 'Tên dịch vụ', 'Vị trí', 'Loại dịch vụ', 'Mã nhân viên', 'Tên nhân viên', 'Giá dịch vụ (VNĐ)', 'Giảm giá (VNĐ)', 'Giá sau giảm (VNĐ)'];

         $fileExportName = 'DichVuDaTuVanThanhCong' . '_report_' . date('Y_m') . '_' . time() . '_' . $staffId . '.xlsx';
         $filePathExport = storage_path('app/excel') . '/' . $fileExportName;

         $saveDir = 'MedicalCouncilReport/exports';
         $s3Storage = new S3ExportStorage();

         $reportExport = new BaseReport($formatData);
         $reportExport->setStorage($s3Storage);
         $exportURL = $reportExport->setHeadings($headings)
            ->formatHeadings('A1:M1','FFFFFF', '4285F4')
            ->setNumberFormat('K2:M9000', 'integer')
            ->store('excel/' . $fileExportName)
            ->export($filePathExport, $saveDir, $fileExportName);

         $reportExport->unlink($filePathExport);
         return $exportURL;
      }
   }

   public function getConsultedServicesByConsultedDate($conditions)
   {
      $fromDate = Carbon::parse($conditions['FromDate'])->startOfDay()->toDateTimeString();
      $toDate = Carbon::parse($conditions['ToDate'])->endOfDay()->toDateTimeString();

      DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
      $query = $this->_model->newQuery();
      $results = $query->join('OrderChanging as oc', 'oc.OrderChangingId', '=', 'OrderDetail.OrderChangingId')
         ->join('in.Staff as ss', 'ss.StaffId', '=', 'oc.ChangedBy')
         ->join('in.Branch as b', 'b.BranchId', '=', 'OrderDetail.ConsultedBranchId')
         ->join('Treatment as t', 't.TreatmentId', '=', 'OrderDetail.TreatmentId')
         ->join('Customer as c', 'c.CustomerId', '=', 't.PersonId')
         ->join('Service as sv', 'sv.ServiceId', '=', 'OrderDetail.ServiceId')
         ->whereIn('OrderDetail.Status', [2, 50, 100])
         ->whereBetween('OrderDetail.ConsultedDate', [$fromDate, $toDate])
         ->select([
               'b.BranchCode',
               'OrderDetail.ConsultedDate',
               'c.CustomerCode',
               'c.FullName as CustomerName',
               'OrderDetail.ServiceCode',
               'OrderDetail.ServiceName',
               'OrderDetail.AnatomyBodyPartItemName',
               'sv.OrthodonticLevel',
               'sv.WarrantyType',
               'ss.StaffCode',
               'ss.FullName as StaffName',
               'OrderDetail.ServicePrice',
               'OrderDetail.DiscountAmount',
               'OrderDetail.Amount as PriceAfterDiscount',
         ])
         ->get();
      DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

      foreach ($results as $result) {
         $result->ServiceType = $this->detectServiceType(
               $result->OrthodonticLevel,
               $result->WarrantyType
         );
      }

      return $results;
   }

   private function detectServiceType($orthodonticLevel, $warrantyType)
   {
      $serviceType = config('constants.service.type');

      if ($orthodonticLevel != null) {
         return $serviceType['C'];
      }

      return $serviceType[$warrantyType] ?? $serviceType['T'];
   }
}
