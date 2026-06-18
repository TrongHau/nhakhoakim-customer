<?php

namespace App\Repositories;

use App\Exports\BaseReport;
use App\Exports\S3ExportStorage;
use App\Rating;
use App\Repositories\Abstracts\EloquentRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RatingRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return Rating::class;
   }

   public function getRatingDetail($ratingId)
   {
      if (!$ratingId || empty($ratingId)) {
         return null;
      }
      $query = $this->_model->newQuery();
      $query->where('RatingId', $ratingId);
      $query->with(['branch', 'customer','appointment', 'ratingDetails', 'customerCareRatingRecommends']);

      return $query->first();
   }

   public function updateCustomerRatingWeb($ratingId, $customerId)
   {
      try {
         $rating = Rating::find($ratingId);
         if(!$rating){
            return false;
         }

         $result = Rating::where('RatingId', $ratingId)->update(['CustomerId' => $customerId]);
         return $result;
      } catch (\Exception $e) {
         Log::error("updateCustomerRatingWeb errors", [$e->getMessage()]);
         return false;
      }
   }

   public function exportRatingReport($conditions)
   {
      $result = $this->getRatingReport($conditions);

      if (!$result || empty($result)) {
         return false;
      }

      $staffId = Auth::user()['StaffId'] ?? 0;
      $formatData = [];

      foreach ($result as $value) {
         $formatData[] = [
            'Date' => trim($value->Date),
            'CustomerCode' => $value->CustomerCode,
            'FullName' => trim($value->FullName),
            'BranchCode' => $value->BranchCode,
            'Value' => $value->Value,
            'DoctorName' => $value->DoctorName,
            'DoctorCode' => $value->DoctorCode,
         ];
      }

      if (count($formatData) > 0 && !empty($formatData)) {
         $headings = ['Thời gian', 'Mã Khách hàng', 'Tên Khách hàng', 'Chi nhánh', 'Giá trị', 'Tên bác sĩ', 'Mã bác sĩ'];

         $fileExportName = 'KhachHangDanhGia' . '_report_' . date('Y_m') . '_' . time() . '_' . $staffId . '.xlsx';
         $filePathExport = storage_path('app/excel') . '/' . $fileExportName;

         $saveDir = 'MedicalCouncilReport/exports';
         $s3Storage = new S3ExportStorage();

         $reportExport = new BaseReport($formatData);
         $reportExport->setStorage($s3Storage);
         $exportURL = $reportExport->setHeadings($headings)
            ->formatHeadings('A1:G1','FFFFFF', '4285F4')
            ->setNumberFormat('E2:E9000', 'decimal_1')
            ->store('excel/' . $fileExportName)
            ->export($filePathExport, $saveDir, $fileExportName);

         $reportExport->unlink($filePathExport);
         return $exportURL;
      }
   }

   public function getRatingReport($conditions)
   {
      $fromDate = Carbon::parse($conditions['FromDate'])->startOfDay()->timestamp;
      $toDate = Carbon::parse($conditions['ToDate'])->endOfDay()->timestamp;

      DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
      $query = $this->_model->newQuery();
      $result = $query->join('Customer as c', 'c.CustomerId', '=', 'Rating.CustomerId')
         ->join('in.Branch as b', 'b.BranchId', '=', 'Rating.BranchId')
         ->join('Appointment as a', 'a.AppointmentId', '=', 'Rating.AppointmentId')
         ->join('Doctor as d', 'd.DoctorId', '=', 'a.AppointedTo')
         ->join('in.Staff as s', 's.StaffId', '=', 'd.StaffId')
         ->where('Rating.CreatedDate', '>', $fromDate)
         ->where('Rating.CreatedDate', '<', $toDate)
         ->select([
            DB::raw('date(from_unixtime(Rating.CreatedDate)) as Date'),
            'c.CustomerCode',
            'c.FullName',
            'b.BranchCode',
            'Rating.Value',
            's.FullName as DoctorName',
            's.StaffCode as DoctorCode',
         ])
         ->orderBy('Rating.CreatedDate')
         ->get();
      DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

      return $result;
   }
}
