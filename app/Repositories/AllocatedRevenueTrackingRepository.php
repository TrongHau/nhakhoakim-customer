<?php

namespace App\Repositories;

use App\AllocatedRevenueTracking;
use App\Exports\BaseReport;
use App\Exports\S3ExportStorage;
use App\Repositories\Abstracts\EloquentRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AllocatedRevenueTrackingRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return AllocatedRevenueTracking::class;
   }

   public function exportTreatmentRevenueReport($conditions)
   {
      $result = $this->getTreatmentRevenueReport($conditions);

      if (!$result || empty($result)) {
         return false;
      }

      $staffId = Auth::user()['StaffId'] ?? 0;
      $formatData = [];

      foreach ($result as $value) {
         $formatData[] = [
            'BranchCode' => $value->BranchCode,
            'TreatmentDate' => Carbon::parse($value->TreatmentDate)->format('Y-m-d'),
            'CustomerCode' => $value->CustomerCode,
            'CustomerName' => trim($value->CustomerName),
            'ServiceCode' => $value->ServiceCode,
            'ServiceName' => $value->ServiceName,
            'AnatomyBodyPartItemName' => $value->AnatomyBodyPartItemName,
            'TreatmentDoctorCode' => $value->TreatmentDoctorCode,
            'TreatmentDoctorName' => $value->TreatmentDoctorName,
            'AdvisorDoctorCode' => $value->AdvisorDoctorCode,
            'AdvisorDoctorName' => $value->AdvisorDoctorName,
            'ServicePrice' => $value->ServicePrice,
            'DiscountAmount' => $value->DiscountAmount,
            'PriceAfterDiscount' => $value->PriceAfterDiscount,
         ];
      }

      if (count($formatData) > 0 && !empty($formatData)) {
         $headings = ['Chi nhánh', 'Ngày điều trị', 'Mã khách hàng', 'Tên khách hàng', 'Mã dịch vụ', 'Tên dịch vụ', 'Vị trí', 'Mã bác sĩ điều trị', 'Tên bác sĩ điều trị', 'Mã bác sĩ tư vấn', 'Tên bác sĩ tư vấn', 'Giá dịch vụ (VNĐ)', 'Giảm giá (VNĐ)', 'Giá sau giảm (VNĐ)'];

         $fileExportName = 'DieuTriHoTroChuyenMon' . '_report_' . date('Y_m') . '_' . time() . '_' . $staffId . '.xlsx';
         $filePathExport = storage_path('app/excel') . '/' . $fileExportName;

         $saveDir = 'MedicalCouncilReport/exports';
         $s3Storage = new S3ExportStorage();

         $reportExport = new BaseReport($formatData);
         $reportExport->setStorage($s3Storage);
         $exportURL = $reportExport->setHeadings($headings)
            ->formatHeadings('A1:N1','FFFFFF', '4285F4')
            ->setNumberFormat('L2:N9000', 'integer')
            ->store('excel/' . $fileExportName)
            ->export($filePathExport, $saveDir, $fileExportName);

         $reportExport->unlink($filePathExport);
         return $exportURL;
      }
   }

   public function getTreatmentRevenueReport($conditions)
   {
      $fromDate = Carbon::parse($conditions['FromDate'])->startOfDay()->toDateTimeString();
      $toDate = Carbon::parse($conditions['ToDate'])->endOfDay()->toDateTimeString();

      DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
      $query = $this->_model->newQuery();
      $subQuery = $query->select([
               'AllocatedRevenueTracking.CreatedDate',
               'AllocatedRevenueTracking.CustomerId',
               'AllocatedRevenueTracking.ServiceId',
               DB::raw('MAX(AllocatedRevenueTracking.AllocatedRevenueId) as AllocatedRevenueId'),
               'AllocatedRevenueTracking.TreatmentDoctorId',
               'AllocatedRevenueTracking.BranchId',
               'AllocatedRevenueTracking.OrderDetailId',
         ])
         ->whereBetween('AllocatedRevenueTracking.CreatedDate', [$fromDate, $toDate])
         ->where('AllocatedRevenueTracking.TreatmentMedicalProcedureId', '>', 0)
         ->where('AllocatedRevenueTracking.TrackingType', 1)
         ->groupBy([
               'AllocatedRevenueTracking.CreatedDate',
               'AllocatedRevenueTracking.CustomerId',
               'AllocatedRevenueTracking.ServiceId',
               'AllocatedRevenueTracking.OrderDetailId',
               'AllocatedRevenueTracking.TreatmentDoctorId',
               'AllocatedRevenueTracking.BranchId',
         ]);

      $rows = DB::query()
         ->fromSub($subQuery, 't')
         ->join(DB::raw('`in`.Branch as b'), 'b.BranchId', '=', 't.BranchId')
         ->join('Customer as c', 'c.CustomerId', '=', 't.CustomerId')
         ->join('OrderDetail as s', 's.OrderDetailId', '=', 't.OrderDetailId')
         ->join(DB::raw('`in`.Staff as ss'), 'ss.StaffId', '=', 't.TreatmentDoctorId')
         ->leftJoin('AllocatedRevenueCoL as col', function ($join) {
               $join->on('col.AllocatedRevenueId', '=', 't.AllocatedRevenueId')
                  ->where('col.AllocatedGroup', 3);
         })
         ->leftJoin(DB::raw('`in`.Staff as ss2'), 'ss2.StaffId', '=', 'col.AllocatedId')
         ->whereColumn('t.TreatmentDoctorId', '!=', 'col.AllocatedId')
         ->select([
               'b.BranchCode',
               't.CreatedDate as TreatmentDate',
               'c.CustomerCode',
               'c.FullName as CustomerName',
               's.ServiceCode',
               's.ServiceName',
               's.AnatomyBodyPartItemName',
               'ss.StaffCode as TreatmentDoctorCode',
               'ss.FullName as TreatmentDoctorName',
               'ss2.StaffCode as AdvisorDoctorCode',
               'ss2.FullName as AdvisorDoctorName',
               's.ServicePrice',
               's.DiscountAmount',
               's.Amount as PriceAfterDiscount',
         ])
         ->orderBy('t.CreatedDate')
         ->get();

      return $rows;
   }
}
