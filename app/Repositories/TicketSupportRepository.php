<?php

namespace App\Repositories;

use App\Exports\BaseReport;
use App\Exports\S3ExportStorage;
use App\Libs\Helper;
use App\TicketSupport;
use App\Repositories\Abstracts\EloquentRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;

class TicketSupportRepository extends EloquentRepository
{
   protected const PUBLIC_VALUE = [0,1];
   protected const TICKET_STATUS = [
      1 => 'Mới',
      5 => 'Đã xử lý',
      10 => 'Hủy',
      15 => 'Đã tiếp nhận',
      20 => 'Từ chối',
   ];
   protected const IT_RECEIVING_ORGS = 5;
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return TicketSupport::class;
   }

   public function getTicketSupportForIT($condition)
   {
      $fromDate = $condition['FromDate'] ?? Carbon::now();
      $toDate = $condition['ToDate'] ?? Carbon::now();
      $receivingOrgId = $condition['DepartmentId'] ?? null;
      if (empty($receivingOrgId)) {
         $receivingOrgId = null;
      }

      $query = $this->_model->newQuery();

      $query->select(DB::raw("TicketSupport.TicketSupportId, TicketSupport.Status, TicketSupport.CreatedDate, tsro.Name AS DepartmentName, tsc.Name AS CategoryName, TicketSupport.Content, s.FullName, s.StaffCode, o.OrgCode,  s1.FullName AS ReceiveByName, s1.StaffCode AS ReceiveByCode, s2.FullName AS ResultByName, s2.StaffCode AS ResultByCode, TicketSupport.ResultDate, TicketSupport.ResultContent, s3.FullName AS RejectedByName, s3.StaffCode AS RejectedByCode, TicketSupport.RejectedContent, s4.FullName AS ActionByName, s4.StaffCode AS ActionByCode"));

      $query->join('in.Staff as s', 's.StaffId', 'TicketSupport.CreatedBy')
         ->leftJoin('in.TicketSupportCategory as tsc', 'tsc.TicketSupportCategoryId', 'TicketSupport.TicketCategoryId')
         ->leftJoin('in.Org as o', 'o.OrgId', 'TicketSupport.SendingOrgId')
         ->leftJoin('in.TicketSupportReceivingOrg as tsro', 'tsro.TicketSupportReceivingOrg', 'TicketSupport.ReceivingOrgId')
         ->leftJoin('in.Staff as s1', 's1.StaffId', 'TicketSupport.ReceiveBy')
         ->leftJoin('in.Staff as s2', 's2.StaffId', 'TicketSupport.ResultBy')
         ->leftJoin('in.Staff as s3', 's3.StaffId', 'TicketSupport.RejectedBy')
         ->leftJoin('in.Staff as s4', 's4.StaffId', 'TicketSupport.ActionBy');

      $query->where('TicketSupport.ReceivingOrgId', '<>', 21);
      if (isset($fromDate) && !empty($fromDate)) {
         $query->where('TicketSupport.CreatedDate', '>=', $fromDate);
      }
      if (isset($toDate) && !empty($toDate)) {
         $query->where('TicketSupport.CreatedDate', '<=', $toDate);
      }
      if ($receivingOrgId) {
         $query->where(function ($subQuery) use ($receivingOrgId) {
            $subQuery->where(function ($q) use ($receivingOrgId) {
               $q->where('TicketSupport.ReceivingOrgId', $receivingOrgId);
            });
            $subQuery->orWhere(function ($q) use ($receivingOrgId) {
               $q->whereExists(function ($existsQuery) use ($receivingOrgId) {
                  $existsQuery->from('in.TicketSupportRelatedObject as tro')
                     ->select(DB::raw(1))
                     ->whereColumn('tro.TicketSupportId', 'TicketSupport.TicketSupportId')
                     ->where('tro.ObjectType', 1)
                     ->where('tro.ObjectId', $receivingOrgId);
               });
            });
         });
      }

      $query->orderBy('TicketSupport.TicketSupportId', 'DESC');
      return $query->get();
   }

   public function exportTicketSupport($condition)
   {
      $result = $this->getTicketSupportForIT($condition ?? []);
      $staffId = Auth::user()['StaffId'] ?? 0;

      if (isset($result) && !empty($result) && count($result) > 0) {
         $headings = ['ID', 'Thời gian tạo', 'Phòng ban', 'Công việc', 'Nội dung', 'Nhân viên tạo', 'Phòng khám', 'Người tiếp nhận', 'Người thực hiện', 'Người xử lý', 'Thời gian xử lý', 'Nội dung xử lý', 'Trạng thái', 'Người từ chối', 'Nội dung từ chối'];

         $formatDate = [];
         foreach ($result as $value) {
            /** @var TicketSupport $value */
            if (Helper::isJSON($value->Content)) {
               $value->Content = json_decode($value->Content);
            }
            if (Helper::isJSON($value->ResultContent)) {
               $value->ResultContent = json_decode($value->ResultContent);
            }
            if (Helper::isJSON($value->RatingContent)) {
               $value->RatingContent = json_decode($value->RatingContent);
            }
            if (Helper::isJSON($value->ReceiveContent)) {
               $value->ReceiveContent = json_decode($value->ReceiveContent);
            }
            if (Helper::isJSON($value->RejectedContent)) {
               $value->RejectedContent = json_decode($value->RejectedContent);
            }

            $value->Content = $this->formatExportContent($value->Content);
            $value->ResultContent = $this->formatExportContent($value->ResultContent);
            $value->RatingContent = $this->formatExportContent($value->RatingContent);
            $value->ReceiveContent = $this->formatExportContent($value->ReceiveContent);
            $value->RejectedContent = $this->formatExportContent($value->RejectedContent);

            $createDate = '';
            if (isset($value->CreatedDate) && !empty($value->CreatedDate)) {
               $createDate = Carbon::parse($value->CreatedDate)->format('d/m/Y H:i:s');
            }

            $formatDate[] = [
               'TicketSupportId' => $value->TicketSupportId ?? '',
               'CreatedDate' => $createDate,
               'DepartmentName' => $value->DepartmentName ?? '',
               'Name' => $value->CategoryName ?? '',
               'Content' => $value->Content ?? '',
               'sFullName' => Helper::formatStaffDisplay($value->FullName, $value->StaffCode) ?? '',
               'OrgCode' => $value->OrgCode ?? '',
               's1FullName' => Helper::formatStaffDisplay($value->ReceiveByName, $value->ReceiveByCode) ?? '',
               's4FullName' => Helper::formatStaffDisplay($value->ActionByName, $value->ActionByCode) ?? '',
               's2FullName' => Helper::formatStaffDisplay($value->ResultByName, $value->ResultByCode) ?? '',
               'ResultDate' => $value->ResultDate ?? '',
               'ResultContent' => $value->ResultContent ?? '',
               'Status' => self::TICKET_STATUS[$value->Status] ?? '',
               's3FullName' => Helper::formatStaffDisplay($value->RejectedByName, $value->RejectedByCode) ?? '',
               'RejectedContent' => $value->RejectedContent ?? ''
            ];
         }

         $dataRowCount = count($formatDate);
         $fileExportName = 'Ticket_Support_' . '_report_' . date('Y_m') . '_' . time() . '_' . $staffId . '.xlsx';
         $filePathExport = storage_path('app/excel') . '/' . $fileExportName;

         $saveDir = 'TicketSupport/exports';
         $s3Storage = new S3ExportStorage();

         $reportExport = new BaseReport($formatDate);
         $reportExport->setStorage($s3Storage);
         $exportURL = $reportExport->setHeadings($headings)
            ->formatHeadings('A1:O1','FFFFFF', '4285F4')
            ->setWrapTextMultipleColumns(['E', 'L', 'O'], $dataRowCount)
            ->store('excel/' . $fileExportName)
            ->export($filePathExport, $saveDir);

         $reportExport->unlink($filePathExport);
         return $exportURL;
      }

      return false;
   }

   private function formatExportContent($content): string
   {
      if (!isset($content) || $content === '') {
         return '';
      }

      $formatted = str_replace(["\\n", "\\t", "\\r"], ["\n", "\t", "\r"], (string) $content);
      $formatted = str_ireplace(['<br />', '<br/>', '<br>'], PHP_EOL, $formatted);

      return $formatted;
   }

   public function updateInvoiceDiscount($condition)
   {
      $customerCode = $condition['CustomerCode'] ?? '';
      $amount = $condition['Amount'] ?? 0;
      $ticketId = $condition['TicketId'] ?? 0;

      $staffId = Auth::user()['StaffId'] ?? 0;

      try {
         $custoemrRepo = new CustomerRepository();
         $customer = $custoemrRepo->getCustomerByCode($customerCode);

         if (!$customer) {
            return [
               'Result' => -1,
               'ResultMessage' => 'Mã KH không hợp lệ!'
            ];
         }
         // Khai báo OUT params
         DB::statement('SET @result = NULL, @msg = NULL');
         DB::statement('CALL pos.dbs_Deposit_UpdateInvoiceDiscountAmount(?, ?, ?, ?, @result, @msg)', [$customer->CustomerId, $amount, $ticketId, $staffId]);

         // Select OUT params
         $data = DB::select('SELECT @result AS Result, @msg AS ResultMessage');
         if (isset($data[0])) {
            return (array) $data[0];
         }

         return null;
      } catch (\Exception $e) {
         Log::error('call store dbs_Deposit_UpdateInvoiceDiscountAmount error: ', [$e->getMessage()]);
         return false;
      }
   }
}
