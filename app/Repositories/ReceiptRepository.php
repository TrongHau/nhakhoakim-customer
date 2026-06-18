<?php

namespace App\Repositories;

use App\Receipt;
use App\Deposit;
use App\PartnerCompany;
use App\OrderDetail;
use App\OrderDetailFinancial;
use App\Treatment;
use App\Appointment;
use App\ReceiptDetail;
use App\DepositTransaction;
use App\AppointmentRevenue;
use App\Bank;
use App\ReceiptTracking;
use App\Receipt_Log;
use App\Branch;
use App\Customer;
use App\CustomerPhoneNumber;
use App\ReceiptService;
use App\OrderDetailFinancialTrans;
use App\TreatmentProcedureProgress;
use App\TreatmentFinancial;
use App\OrderTransferAmountDetail;
use App\OrderTransferAmount;
use App\OrderInstallmentPlan;
use App\OrderInstallmentSchedule;
use App\OrderInstallmentTrans;
use App\Repositories\Abstracts\EloquentRepository;
use App\Repositories\Traits\FilterLockedOrderDetailTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Libs\Factory;
use App\PayProvider;
use App\ReceiptPending;
use App\Staff;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use App\Jobs\UpdateCRMDepositJob;
use App\Jobs\InsertDepositSendZNSJob;

class ReceiptRepository extends EloquentRepository
{
    use FilterLockedOrderDetailTrait;

    private $cachedReceipt = null;

   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return Receipt::class;
   }

   public function listServiceCreateReceipt($customerId)
   {
      try {

         $treatment = Treatment::where('PersonId', $customerId)->whereNull('ClosedAt')->select('TreatmentId')->first();

         if (empty($treatment)) {
            return [];
         }
         $treatmentId = $treatment->TreatmentId;

         $query = OrderDetailFinancial::where('OrderDetailFinancial.CustomerId', $customerId);
         $query->join('pos.Service AS s', 's.ServiceId', '=', 'OrderDetailFinancial.ServiceId');
         $query->join('pos.OrderDetail AS od', 'od.OrderDetailId', '=', 'OrderDetailFinancial.OrderDetailId');
         $query->join('pos.OrderChanging AS oc', 'oc.OrderChangingId', '=', 'od.OrderChangingId');
         $query->join('in.Staff AS s1', 's1.StaffId', '=', 'oc.ChangedBy');
         $query->leftJoin('pos.OrderDetailRecording AS odr', 'odr.OrderDetailId', '=', 'od.OrderDetailId');
         $query->leftJoin('in.Staff AS s2', 's2.StaffId', '=', 'odr.ActionBy');
         $query->leftJoin('in.Staff AS s3', 's3.StaffId', '=', 'od.ConsultedBy');
         $query->leftJoin('pos.TreatmentMedicalProcedure AS tmp', 'tmp.TreatmentMedicalProcedureId', '=', 'od.TreatmentMedicalProcedureId');
         $query->leftJoin('pos.PromotionOrderDetail AS pod', 'pod.OrderDetailId', '=', 'od.OrderDetailId');
         $query->leftJoin('pos.promotions AS p', 'p.ID', '=', 'pod.PromotionId');
         // thêm join vào kế hoạch trả góp
         $query->leftJoin('pos.OrderInstallmentPlan AS oip', 'oip.OrderDetailId', '=', 'od.OrderDetailId');
         $query->leftJoin('pos.OrderInstallmentPlanStatus AS oips', 'oips.OrderInstallmentPlanId', '=', 'oip.OrderInstallmentPlanStatus');
         $query->leftJoin('pos.ServiceInstallmentConfig AS sic', 'sic.ServiceId', '=', 's.ServiceId');
         $query->where('OrderDetailFinancial.TreatmentId', $treatmentId);
         $query->where('od.Status', '>', 1);
         
         $query->select(
            'OrderDetailFinancial.*',
            's.ServiceCode',
            'od.ServiceName',
            's.ServiceDomainType',
            'od.AnatomyBodyPartItemName',
            'od.AnatomyBodyPartItemCode',
            'od.Status',
            'od.Quantity',
            'od.ServicePrice',
            'od.TaxAmount',
            'od.TaxPercent',
            'od.DiscountPercent',
            'od.DiscountAmount',
            'od.ConsultedBy',
            'od.ConsultedDate',
            'od.MedicalProcedureId',
            'od.IsPayInstallments',
            'tmp.TreatmentMedicalProcedureStatusId',
            'p.Code as PromotionCode',
            'p.Name as PromotionName',
            'pod.PromotionId',
            'odr.ActionBy',
            'odr.ActionDate',
            's2.FullName',
            'oc.ChangedAt',
            'oc.ChangedBy',
            's1.FullName as ChangedByName',
            's3.FullName as ConsultedByName',
            's3.StaffCode',

            // thêm các field từ OrderInstallmentPlan
            'oip.OrderInstallmentPlanStatus',
            'oip.DownPaymentRequired',
            'oip.MonthlyAmount',
            'oip.TotalPeriods',
            'oip.RemainingPeriods',
            'oip.PaidAmount as PlanPaidAmount',
            'oip.OutstandingAmount',
            'oip.StartInstallmentDate',
            // thêm field từ OrderInstallmentPlanStatus
            'oips.OrderInstallmentPlanNameVi',
            'sic.MinDownPaymentPercent'
        );

         $result = $query->get();

         foreach ($result as &$row) {
            $row->Schedules = OrderInstallmentSchedule::where('OrderInstallmentSchedule.OrderDetailId', $row->OrderDetailId)
               ->join('OrderInstallmentScheduleStatus as oiss', 'oiss.OrderInstallmentScheduleId', '=', 'OrderInstallmentSchedule.OrderInstallmentScheduleStatus')
               ->orderBy('OrderInstallmentSchedule.PeriodNumber')
               ->select(
                  'OrderInstallmentSchedule.*',
                  'oiss.OrderInstallmentScheduleNameVi'
               )
               ->get();
         }

         return $result;
      } catch (\Exception $e) {
         Log::error('listServiceCreateReceipt error', [
            'customerId' => $customerId,
            'exception'  => $e
         ]);
         return [];
      }
   }

   public function getInfoInvoice($orderDetailId)
   {
      $query = DB::table('invoice.InvoiceDetail')->select('InvoiceId');
      $query->where('OrderDetailId', '=', $orderDetailId);
      $result = $query->first();
      if($result){
         return $result->InvoiceId;
      }
      return NULL;
   }

   public function checkIpAddress($ipAddress)
   {
      try {

         $value = Redis::get('common:IPCreatedReceipt_' . $ipAddress);
         if ($value) {
            return json_decode($value);
         } else {
            $query = DB::table('in.NetworkConfig as nc')->select('bwl.BranchId');
            $query->join('in.BranchWorkLocation as bwl', 'bwl.WorkLocationId', '=', 'nc.WorkLocationId');
            $query->where('nc.WanIp', '=', $ipAddress);
            $query->whereIn('bwl.CompanyId', [1, 2, 3, 13]);
            $query->where('nc.State', '=', 1);
            $result = $query->first();

            if ($result) {
               Redis::set('common:IPCreatedReceipt_' . $ipAddress, json_encode($result->BranchId));
               return $result->BranchId;
            }
            return NULL;
         }
      } catch (\Exception $e) {
         Log::error($e->getMessage());
         return NULL;
      }
   }

   public function checkServiceCreateReceipt($data)
   {
      $arrReceipt   = $data['Receipt'] ?? [];
      $orderDetails = $data['OrderDetails'] ?? [];

      $orderDetailIds = [];

      if (!empty($orderDetails)) {
         foreach ($orderDetails as $d) {
            if (!empty($d['OrderDetailId'])) {
                  $orderDetailIds[] = $d['OrderDetailId'];
            }
         }
      }

      if (!empty($arrReceipt)) {
         foreach ($arrReceipt as $d) {
            if (!empty($d['OrderDetailId']) && !in_array($d['OrderDetailId'], $orderDetailIds, true)) {
                  $orderDetailIds[] = $d['OrderDetailId'];
            }
         }
      }

      $orderDetailIds = array_unique($orderDetailIds);

      if (empty($orderDetailIds)) {
         return true;
      }

      $result = OrderDetailFinancial::whereIn('OrderDetailId', $orderDetailIds)->whereColumn('TotalAmount', '>=', 'OrderDetailAmount')->first();

      if ($result) {
         Log::error(
               'Dịch vụ đã thu đủ tiền, không thể tạo phiếu thu tiếp tục.',
               ['OrderDetailId' => $result->OrderDetailId]
         );
         return false;
      }

      return true;
   }

   // Kiểm tra dịch vụ đã bị xoá
   public function checkoutServiceDelete ($orderDetails)
   {
      if (!empty($orderDetails) && is_array($orderDetails)) {
         $orderDetailIds = [];
         foreach ($orderDetails as $d) {
            if (!empty($d['OrderDetailId'])) {
               $orderDetailIds[] = $d['OrderDetailId'];
            }
         }
         if (!empty($orderDetailIds)) {
            $orderDetailDelete = OrderDetail::whereIn('OrderDetailId', $orderDetailIds)
               ->whereIn('Status', [0,1])
               ->first();
            if ($orderDetailDelete) {
               return true;
            }
         }
      }
      return false;
   }

   // Tạo mới phiếu thu
   public function createReceipt($data)
   {
      try {

         $customerId = $data['CustomerId'];
         $arrReceipt = $data['Receipt'];
         $orderDetails = is_array($data['OrderDetails'] ?? null) ? $data['OrderDetails'] : [];
         // $ePaymentCheck = $data['Epaymentcheck'];
         $branchId = $data['BranchId'];
         // $mccCode = $data['MccCode'];
         $treatmentId = $data['TreatmentId'];
         $note = $data['Note'];
         $receiptType = $data['ReceiptType'];
         $staffId = Auth::user()['StaffId'];

         if ($receiptType == 1) {
            $note = "Thu tiền điều trị nha khoa";
         } else if ($receiptType == 2) {
            $note = "Phiếu thu công nợ";
         } else if ($receiptType == 3) {
            $orderDetailId   = $orderDetails[0]['OrderDetailId'];
            $orderInstallmentPlan = OrderInstallmentPlan::where('OrderDetailId', $orderDetailId)
               ->first();
            if($orderInstallmentPlan){
               $paidAmount = $orderInstallmentPlan->PaidAmount;
               $downPaymentRequired = $orderInstallmentPlan->DownPaymentRequired;
               if($paidAmount >= $downPaymentRequired) {
                  $note = "Thu tiền trả góp gói niềng răng";
               } else {
                  $note = "Thu tiền trả trước gói niềng răng trả góp";
               }
            } else {
               $note = "Thu tiền trả trước gói niềng răng trả góp";
            }
         } else {
            $note = "Phiếu thu khác";
         }
         // loại các dịch vụ khóa
         $orderDetails = $this->filterLockedOrderDetails($orderDetails);

         // Lấy ví khách hàng hoặc tạo ví mới
         $depositId = $this->getDepositIdByCustomer($customerId);
         $appointmentId = $this->getAppointmentIdByCustomer($customerId);

         // Lấy thông tin khách hàng
         $customer = Customer::find($customerId);
         if (!$customer || empty($customer)) {
            return false;
         }

         $receiptDetail = [];
         $totalAmount = 0;
         $insuranceAmount = 0;
         $sendPOSQR = false;
         $sendPOSCard = false;
         $sendPOSAmount = 0;
         foreach ($arrReceipt as $v) {
            $totalAmount += $v['Amount'];
            if (isset($v['PartnerCompanyId']) && $v['PartnerCompanyId'] > 0) {
               $result = PartnerCompany::select('pct.PartnerCompanyTypeId')
                  ->join('pos.PartnerCompanyType as pct', function ($join) {
                     $join->on(
                           'PartnerCompany.PartnerCompanyTypeId',
                           '=',
                           'pct.PartnerCompanyTypeId'
                     );
                  })->where('PartnerCompany.PartnerCompanyId', $v['PartnerCompanyId'])->first();
               $result = $result ? $result->PartnerCompanyTypeId : null;
               if ($result && in_array($result, [1, 2])) {
                  $insuranceAmount += $v['Amount'];
               }
            }
            switch (true) {
               case $v['PaymentMethodId'] == 1 && !empty($v['Amount']):
                  $receiptDetail[] = [
                     'BankId' => NULL,
                     'Amount' => $v['Amount'],
                     'PartnerCompanyId' => NULL,
                     'PaymentMethodId' => $v['PaymentMethodId'],
                     'OrderDetailId' => NULL,
                     'OrderDetailAmount' => NULL,
                     'PrepayCardId' => NULL,
                     'InstallmentPaymentPartnerId' => NULL,
                     'MCC' => NULL,
                     'ForControlCode' => isset($v['ForControlCode']) ? $v['ForControlCode'] : NULL,
                     'CreatedStaffId' => $staffId,
                     'UpdatedStaffId' => $staffId,
                     'ServiceId' => NULL
                  ];
                  break;
               case $v['PaymentMethodId'] == 2 && !empty($v['Amount']):
                  if (!isset($v['BankId']) || !is_numeric($v['BankId']) || $v['BankId'] < 1) {
                     Log::error('Hệ thống không xác định được ngân hàng khi tạo phiếu thu.');
                     return false;
                  }
                  $bank = Bank::find($v['BankId'] ?? 0);
                  if ($bank && !empty($bank) &&  in_array(strtoupper($bank->NameEn), ['MPOS'])) {
                     $sendPOSQR = true;
                     $sendPOSAmount = $v['Amount'] ?? 0;
                  }
                  $receiptDetail[] = [
                     'BankId' => $v['BankId'],
                     'Amount' => $v['Amount'],
                     'PartnerCompanyId' => NULL,
                     'PaymentMethodId' => $v['PaymentMethodId'],
                     'OrderDetailId' => NULL,
                     'OrderDetailAmount' => NULL,
                     'PrepayCardId' => NULL,
                     'InstallmentPaymentPartnerId' => NULL,
                     'MCC' => NULL,
                     'ForControlCode' => isset($v['ForControlCode']) ? $v['ForControlCode'] : NULL,
                     'CreatedStaffId' => $staffId,
                     'UpdatedStaffId' => $staffId,
                     'ServiceId' => NULL
                  ];
                  break;
               case $v['PaymentMethodId'] == 3 && !empty($v['Amount']):
                  if (!isset($v['BankId']) || !is_numeric($v['BankId']) || $v['BankId'] < 1) {
                     Log::error('Hệ thống không xác định được ngân hàng khi tạo phiếu thu.');
                     return false;
                  }
                  $bank = Bank::find($v['BankId'] ?? 0);
                  if ($bank && !empty($bank) &&  in_array(strtoupper($bank->NameEn), ['MPOS'])) {
                     $sendPOSCard = true;
                     $sendPOSAmount = $v['Amount'] ?? 0;
                  }
                  $receiptDetail[] = [
                     'BankId' => $v['BankId'],
                     'Amount' => $v['Amount'],
                     'PartnerCompanyId' => NULL,
                     'PaymentMethodId' => $v['PaymentMethodId'],
                     'OrderDetailId' => NULL,
                     'OrderDetailAmount' => NULL,
                     'PrepayCardId' => NULL,
                     'InstallmentPaymentPartnerId' => NULL,
                     'MCC' => NULL,
                     'ForControlCode' => isset($v['ForControlCode']) ? $v['ForControlCode'] : NULL,
                     'CreatedStaffId' => $staffId,
                     'UpdatedStaffId' => $staffId,
                     'ServiceId' => NULL
                  ];
                  break;
               case $v['PartnerCompanyId'] > 0 && $v['OrderDetailId'] > 0 && !empty($v['Amount']):
                  $receiptDetail[] = [
                     'BankId' => NUll,
                     'Amount' => $v['Amount'],
                     'PartnerCompanyId' => $v['PartnerCompanyId'],
                     'PaymentMethodId' => $v['PaymentMethodId'],
                     'OrderDetailId' => $v['OrderDetailId'],
                     'OrderDetailAmount' => $v['OrderDetailAmount'],
                     'OrderChangingId' => $v['OrderChangingId'],
                     'PrepayCardId' => NULL,
                     'InstallmentPaymentPartnerId' => NULL,
                     'MCC' => NULL,
                     'ForControlCode' => isset($v['ForControlCode']) ? $v['ForControlCode'] : NULL,
                     'CreatedStaffId' => $staffId,
                     'UpdatedStaffId' => $staffId,
                     'ServiceId' => $v['ServiceId']
                  ];
                  break;
               case $v['PartnerCompanyId'] > 0 && $v['InstallmentPaymentPartnerId'] > 0 && !empty($v['Amount']):
                  $receiptDetail[] = [
                     'BankId' => $v['BankId'],
                     'Amount' => $v['Amount'],
                     'PartnerCompanyId' => $v['PartnerCompanyId'],
                     'PaymentMethodId' => $v['PaymentMethodId'],
                     'OrderDetailId' => $v['OrderDetailId'],
                     'OrderDetailAmount' => $v['OrderDetailAmount'],
                     'OrderChangingId' => $v['OrderChangingId'],
                     'PrepayCardId' => NULL,
                     'InstallmentPaymentPartnerId' => $v['InstallmentPaymentPartnerId'],
                     'MCC' => NULL,
                     'ForControlCode' => isset($v['ForControlCode']) ? $v['ForControlCode'] : NULL,
                     'CreatedStaffId' => $staffId,
                     'UpdatedStaffId' => $staffId,
                     'ServiceId' => $v['ServiceId']
                  ];
                  break;
               case $v['PrepayCardId'] > 0 && !empty($v['Amount']):
                  // Ví trả trước
                  break;
               case $v['EpaymentCompanyId'] > 0 && !empty($v['Amount']):
                  // Thanh toán điện tử
                  break;
               default:
                  Log::error('Thông tin phiếu thu không chính xác.');
                  return false;
            }
         }
         if (empty($totalAmount) || $totalAmount < 0) {
            Log::error('Tổng số tiền phải lớn hơn không');
            return false;
         }

         if ($sendPOSQR && $sendPOSCard) {
            Log::error("Create Receipt: Không thể đồng thời chọn Chuyển khoản QR và Cà thẻ trên cùng thiết bị MPOS");
            return false;
         }

         $receiptArr = [
            "DepositId" => $depositId,
            'TotalAmount' => $totalAmount,
            'BranchId' => $branchId,
            'State' => 1,
            'Note' => $note,
            'AppointmentId' => $appointmentId,
            'ReceiptStatusId' => 2,
            'ReceipStatusDate' => Carbon::now()->toDateTimeString(),
            'AddedBy' => $staffId,
            'AddedAt' => Carbon::now()->timestamp,
            'UpdatedBy' => $staffId,
            'UpdatedAt' => Carbon::now()->timestamp,
            'ReceiptCode' => '',
            'IsNotified' => 1,
            'InsuranceAmount' => $insuranceAmount,
            'ReceiptType' => $receiptType == 2 ? 2 : 1,
            'InsuranceUnPaidAmount' => $insuranceAmount,
            'ReceiptInsuranceStatusId' => $receiptType > 1 ? 10 : 1,
            'TreatmentId' => $treatmentId
         ];

        /**
         * Nếu thanh toán qua MPOS thì tạo ReceiptPending
         * Sau khi khách hàng thanh toán xong thì webhook tạo Receipt
         */
         if ($sendPOSQR || $sendPOSCard) {
            $receiptPendingCode =  $this->createReceiptPendingCode();
            $description = Str::upper(Str::slug($customer->FullName ?? '', ' '));
            $dataReceiptPending = [
               'ReceiptPendingCode' => $receiptPendingCode,
               'State' => 10, //Active
               'CustomerId' => $customerId,
               'TreatmentId' => $treatmentId,
               'DepositId' => $depositId,
               'BranchId' => $branchId,
               'AppointmentId' => $appointmentId,
               'Receipt' => $receiptArr,
               'ReceiptDetail' => $receiptDetail,
               'ReceiptType' => $receiptType,
               'TotalAmount' => $totalAmount,
               'OrderDetail' => $orderDetails,
               'Note' => $note,
               'CreatedBy' => $staffId,
               'CreatedDate' => Carbon::now()->toDateTimeString(),
               'UpdatedBy' => $staffId,
               'UpdatedDate' => Carbon::now()->toDateTimeString()
            ];
            DB::beginTransaction();
            try {
               $receiptPending = ReceiptPending::create($dataReceiptPending);
               
               if (!$receiptPending || empty($receiptPending)) {
                  Log::error("Create Receipt Pending fail", $dataReceiptPending);
               }
               //Commit data
               DB::commit();

               $sendPaymentRequest = true;
               if ($sendPOSQR) {
                  $sendPaymentRequest = $this->paymentMPOSQR($sendPOSAmount, $receiptPending->ReceiptPendingId ?? 0, $receiptPendingCode, $branchId, $description);
               }
               if ($sendPOSCard) {
                  $sendPaymentRequest = $this->paymentMPOSCard($sendPOSAmount, $receiptPending->ReceiptPendingId ?? 0, $receiptPendingCode, $branchId, $description);
               }
               //Response data
               if (!$sendPaymentRequest || empty($sendPaymentRequest)) {
                  return false;
               }
               return $sendPaymentRequest;
            } catch (\Exception $ex) {
               DB::rollBack();
               Log::error("Create Receipt Pending fail", [$ex->getMessage()]);
               return false;
            }
            return false;
         }

         /**
          * Các thanh toán còn lại thì xử lý bình thường
          */
         return $this->progressCreateReceipt(
            $receiptArr, 
            $receiptDetail,
            $receiptType,
            $totalAmount,
            $branchId,
            $customerId,
            $treatmentId,
            $depositId,
            $staffId,
            $appointmentId,
            $orderDetails
         );
         
      } catch (\Exception $e) {
         Log::error("createReceipt errors", [$e->getMessage()]);
         return false;
      }
      return false;
   }


   /**
    * Function sử dụng chung cho việc tạo Receipt - Phiếu thu
    * @param mixed $receipt 
    * @param mixed $receiptDetail 
    * @param mixed $receiptType 
    * @param mixed $totalAmount 
    * @param mixed $branchId 
    * @param mixed $customerId 
    * @param mixed $treatmentId 
    * @param mixed $depositId 
    * @param mixed $staffId 
    * @param mixed $appointmentId 
    * @param mixed $orderDetails 
    * @return mixed|false 
    */
   protected function progressCreateReceipt($receipt, $receiptDetail, $receiptType, $totalAmount, $branchId, $customerId, $treatmentId, $depositId, $staffId, $appointmentId, $orderDetails)
   {
      DB::beginTransaction();
      try {
         $receipt['ReceiptCode'] = $this->createReceiptCode($branchId);
         $receiptId = Receipt::insertGetId($receipt);

         if ($receiptId > 0) {
            // Lưu chi tiết phiếu thu
            $this->saveReceiptDetail($receiptDetail, $receiptId);
            // Cập nhật số dư ví khách hàng
            $this->updateBalanceDeposit($depositId, $totalAmount);
            // Lưu giao dịch ví khách hàng
            $this->saveDepositTransaction($depositId, $totalAmount, $branchId, $customerId, $receiptId, $staffId);
            // Lưu doanh thu cuộc hẹn
            $this->saveAppointmentRevenue($customerId, $appointmentId, $totalAmount, $branchId, $receiptId);
            // Lưu lịch sử phiếu thu
            $this->saveReceiptTracking($receiptId, $receipt, $receiptDetail, NULL, 10);
            // Cập nhật doanh thu mục tiêu
            $this->updateTargetRevenue($receiptId, $totalAmount, $branchId);
            // Ghi log phiếu thu
            $this->insertReceiptLog($receiptId, $staffId, $totalAmount);
            // Lưu dịch vụ thu trong phiếu thu
            $this->insertReceiptService($receiptId, $treatmentId, $staffId, $totalAmount, $receiptType, $customerId, $orderDetails, $receiptDetail);
            $this->insertDepositSendZNS($customerId, $receiptId, $totalAmount, $branchId, 'Add');
            $this->updateCRMDeposit($customerId, $receiptId, $appointmentId, $totalAmount);
            $orderDetailIds = [];

            if (!empty($orderDetails) && is_array($orderDetails)) {
               foreach ($orderDetails as $d) {
                  if (!empty($d['OrderDetailId'])) {
                        $orderDetailIds[] = $d['OrderDetailId'];
                  }
               }
            }

            if (!empty($arrReceipt) && is_array($arrReceipt)) {
               foreach ($arrReceipt as $d) {
                  if (!empty($d['OrderDetailId']) && !in_array($d['OrderDetailId'], $orderDetailIds, true)) {
                        $orderDetailIds[] = $d['OrderDetailId'];
                  }
               }
            }
            // Cập nhật trạng thái dịch vụ đã thu tiền
            $this->updateStatusOrderDetail($orderDetailIds, $receiptId);
         }

         DB::commit();
          // Gửi ZNS thông báo phiếu thu
         // dispatch(new InsertDepositSendZNSJob($customerId, $receiptId, $totalAmount, $branchId, 'Add'));
          // Cập nhật phiếu thu qua CRM
         // dispatch(new UpdateCRMDepositJob($customerId, $receiptId, $appointmentId, $totalAmount));
         return $receiptId;
      } catch (\Exception $ex) {
         DB::rollBack();
         Log::error("Progress create receipt fail", [$ex->getMessage()]);
         return false;
      }
      DB::rollBack();
      return false;
   }

   // Ví khách hàng
   public function getDepositIdByCustomer($customerId)
   {
      try {

         $result = Deposit::where('CustomerId', '=', $customerId)->select('DepositId')->first();

         if ($result) {
            return $result->DepositId;
         }

         $depositId = Deposit::insertGetId([
            'CustomerId' => $customerId,
            'TotalAmount' => 0,
            'CurrentBalance' => 0,
            'PaidAmount' => 0,
            'State' => 1
         ]);

         return $depositId;
      } catch (\Exception $e) {
         Log::error("getDepositIdByCustomer errors", [$e->getMessage()]);
         throw $e;
      }
   }

   // Lấy AppointmentId từ CustomerId
   public function getAppointmentIdByCustomer($customerId)
   {
      try {

         $today = date('Y-m-d');
         $starDate = strtotime($today . ' 00:00:00');
         $endDate = strtotime($today . ' 23:59:59');
         $query = Appointment::where('CustomerId', '=', $customerId)->whereBetween('StartAt', [$starDate, $endDate])->select('AppointmentId')->orderByDesc('AppointmentId');

         $result = $query->first();

         if ($result) {
            return $result->AppointmentId;
         }

         return NULL;
      } catch (\Exception $e) {
         Log::error("getAppointmentIdByCustomer errors", [$e->getMessage()]);
         throw $e;
      }
   }

    protected function createReceiptCode($branchId)
    {
        $now   = Carbon::now();
        $year  = $now->format('y');
        $month = $now->format('m');
        $day   = $now->format('d');

        $infoBranch = Branch::where('BranchId', $branchId)
            ->select('BranchCode')
            ->first();

        if (!$infoBranch) {
            Log::error("Branch not found when create receipt code", ['branch_id' => $branchId]);
            throw new \Exception("Branch not found: {$branchId}");
        }

        $code     = substr($infoBranch->BranchCode, -3);
        $prefix   = 'PT' . $code . $year . $month . $day;
        $cacheKey = "receipt_pre:{$prefix}";

        if (Cache::get($cacheKey) === null) {
            $lock = Cache::lock("init_lock:{$cacheKey}", 5);

            if ($lock->get()) {
                try {
                    $latest = Receipt::where('ReceiptCode', 'like', $prefix . '%')
                     ->orderByDesc('ReceiptId')
                     ->value('ReceiptCode');
                    $lastSeq = ($latest && str_starts_with($latest, $prefix))
                        ? (int) substr($latest, strlen($prefix))
                        : 0;
                    Cache::add($cacheKey, $lastSeq, $now->secondsUntilEndOfDay());
                } finally {
                    $lock->release();
                }
            } else {
                $lock->block(3);
                $lock->release();
            }
        }

        $seq    = Cache::increment($cacheKey);
        $seqStr = strlen((string) $seq) < 5
            ? str_pad($seq, 4, '0', STR_PAD_LEFT)
            : (string) $seq;

        return $prefix . $seqStr;
    }

   protected function createReceiptPendingCode()
   {
      return (string) Str::uuid();
   }

   public function saveReceiptDetail($receiptDetail, $receiptId)
   {
      try {

         foreach ($receiptDetail as &$detail) {
            $detail['ReceiptId'] = $receiptId;
            unset($detail['OrderDetailAmount']);
            unset($detail['ServiceId']);
            unset($detail['OrderChangingId']);
         }
         unset($detail);
         ReceiptDetail::insert($receiptDetail);
         return true;
      } catch (\Exception $e) {
         Log::error("saveReceiptDetail errors", [$e->getMessage()]);
         throw $e;
      }
   }

   public function updateBalanceDeposit($depositId, $amount)
   {
      try {
         Deposit::where('DepositId', $depositId)
            ->update([
               'TotalAmount'     => DB::raw("TotalAmount + {$amount}"),
               'CurrentBalance'  => DB::raw("CurrentBalance + {$amount}"),
               'PaidAmount'      => DB::raw("(TotalAmount + {$amount}) - (CurrentBalance + {$amount})"),
            ]);

         return true;
      } catch (\Exception $e) {
         Log::error('updateBalanceDeposit error', [
            'deposit_id' => $depositId,
            'amount'     => $amount,
            'message'    => $e->getMessage(),
         ]);
         throw $e;
      }
   }

   public function saveDepositTransaction($depositId, $amount, $branchId, $customerId, $receiptId, $staffId)
   {
      try {

         $arrDepositTransaction = [
            'DepositId' => $depositId,
            'CustomerId' => $customerId,
            'BranchId' => $branchId,
            'Type' => 1,
            'Amount' => $amount,
            'ObjectType' => 'Receipt',
            'ObjectId' => $receiptId,
            'BalanceType' => 'Current',
            'CreatedBy' => $staffId,
            'CreatedDate' => Carbon::now()->toDateTimeString()
         ];

         DepositTransaction::insert($arrDepositTransaction);

         return true;
      } catch (\Exception $e) {
         Log::error("saveDepositTransaction errors", [$e->getMessage()]);
         throw $e;
      }
   }

   public function saveAppointmentRevenue($customerId, $appointmentId = 0, $amount = 0, $branchId = 0, $receiptId = 0)
   {
      try {
         $receipt = $this->getDetail($receiptId);
         $addedAt = $receipt->AddedAt;
         $data = [
            'AppointmentId'   => $appointmentId,
            'ReceiptId'       => $receiptId,
            'Amount'          => $amount,
            'BranchId'        => $branchId,
            'Date'            => Carbon::now()->toDateString(),
            'UpdatedAt'       => $addedAt,
            'PushedAt'        => $addedAt,
            'CustomerId'      => $customerId,
         ];

         AppointmentRevenue::insert($data);

         return true;
      } catch (\Exception $e) {
         Log::error("saveAppointmentRevenue errors", [$e->getMessage()]);
         throw $e;
      }
   }

    private function getDetail(int $receiptId): ?Receipt
    {
        if ($this->cachedReceipt === null || $this->cachedReceipt->ReceiptId !== $receiptId) {
            $this->cachedReceipt = Receipt::find($receiptId);
        }
        return $this->cachedReceipt;
    }

   public function saveReceiptTracking($receiptId, $receiptArr, $receiptDetail, $oldData, $actionId)
   {
      try {

         $data = [
            'ReceiptId'        => $receiptId,
            'ActionId'         => $actionId,
            'NewData'          => json_encode([
               'Receipt' => $receiptArr,
               'ReceiptDetail' => $receiptDetail
            ]),
            'OldData'          => $oldData,
         ];

         ReceiptTracking::insert($data);

         return true;
      } catch (\Exception $e) {
         Log::error("saveReceiptTracking errors", [$e->getMessage()]);
         throw $e;
      }
   }

   public function updateTargetRevenue($receiptId, $totalAmount, $branchId)
   {
      try {
        $this->updateTargetRevenue_v2($receiptId, $totalAmount, $branchId);
//         DB::select("CALL sp_Report_UpdateTargetRevenue(?)", [$receiptId]);
         return true;
      } catch (\Exception $e) {
         Log::error("updateTargetRevenue errors", [$e->getMessage()]);
         throw $e;
      }
   }

    private function updateTargetRevenue_v2($receiptId, $totalAmount, $branchId): bool
    {
        try {
            $receipt = $this->getDetail($receiptId);
            $datePayment = Carbon::createFromTimestamp($receipt->AddedAt)->format('Y-m-d');

            // 1. Update Daily
            DB::table('TargetRevenueDaily')
                ->where('BranchId', $branchId)
                ->where('TargetDate', $datePayment)
                ->update([
                    'CurrentRevenue' => DB::raw("CurrentRevenue + {$totalAmount}"),
                    'TimeReached'    => DB::raw("
                    CASE
                        WHEN TimeReached IS NULL AND (CurrentRevenue + {$totalAmount}) >= TargetRevenue
                            THEN NOW()
                        WHEN (CurrentRevenue + {$totalAmount}) < TargetRevenue
                            THEN NULL
                        ELSE TimeReached
                    END
                "),
                    'TimeRollBack'   => $totalAmount < 0
                        ? DB::raw('NOW()')
                        : DB::raw('TimeRollBack'),
                    'RollBackReason' => $totalAmount < 0
                        ? DB::raw("CONCAT(COALESCE(RollBackReason, ''), {$receiptId}, ',')")
                        : DB::raw('RollBackReason'),
                ]);

            // 2. Update Monthly
            DB::table('TargetRevenueExpectTime')
                ->where('BranchId', $branchId)
                ->where('TypeTarget', 'Monthly')
                ->whereRaw("'{$datePayment}' BETWEEN DateFrom AND DateTo")
                ->update([
                    'CurrentRevenue' => DB::raw("CurrentRevenue + {$totalAmount}"),
                    'TimeReached'    => DB::raw("
                    CASE
                        WHEN TimeReached IS NULL AND (CurrentRevenue + {$totalAmount}) >= TargetRevenue
                            THEN NOW()
                        WHEN (CurrentRevenue + {$totalAmount}) < TargetRevenue
                            THEN NULL
                        ELSE TimeReached
                    END
                "),
                    'TimeRollBack'   => $totalAmount < 0
                        ? DB::raw('NOW()')
                        : DB::raw('TimeRollBack'),
                    'RollBackReason' => $totalAmount < 0
                        ? DB::raw("CONCAT(COALESCE(RollBackReason, ''), {$receiptId}, ',')")
                        : DB::raw('RollBackReason'),
                ]);

            // 3. Update Weekly
            DB::table('TargetRevenueExpectTime')
                ->where('BranchId', $branchId)
                ->where('TypeTarget', 'Weekly')
                ->whereRaw("'{$datePayment}' BETWEEN DateFrom AND DateTo")
                ->update([
                    'CurrentRevenue' => DB::raw("CurrentRevenue + {$totalAmount}"),
                    'TimeReached'    => DB::raw("
                    CASE
                        WHEN TimeReached IS NULL AND (CurrentRevenue + {$totalAmount}) >= TargetRevenue
                            THEN NOW()
                        WHEN (CurrentRevenue + {$totalAmount}) < TargetRevenue
                            THEN NULL
                        ELSE TimeReached
                    END
                "),
                    'TimeRollBack'   => $totalAmount < 0
                        ? DB::raw('NOW()')
                        : DB::raw('TimeRollBack'),
                    'RollBackReason' => $totalAmount < 0
                        ? DB::raw("CONCAT(COALESCE(RollBackReason, ''), {$receiptId}, ',')")
                        : DB::raw('RollBackReason'),
                ]);

            return true;

        } catch (\Exception $e) {
            Log::error("updateTargetRevenue errors", [
                'receiptId'   => $receiptId,
                'totalAmount' => $totalAmount,
                'branchId'    => $branchId,
                'message'     => $e->getMessage(),
            ]);
            throw $e;
        }
    }

   public function updateCRMDeposit($customerId, $receiptId, $appointmentId, $totalAmount)
   {
      try {

         $receipt = Receipt::find($receiptId);

         if (!$receipt) {
               throw new \Exception("Receipt not found: {$receiptId}");
         }

         if ($totalAmount <= 0) {
               throw new \Exception("Invalid totalAmount: {$totalAmount}");
         }

         $data = [
               'CustomerId'    => $customerId,
               'ReceiptId'     => $receiptId,
               'AddedAt'       => $receipt->AddedAt,
               'AppointmentId'=> $appointmentId,
               'TotalAmount'   => $totalAmount,
         ];

         $header = ['Authorization'=>'Bearer ' . JWT_APP_TOKEN];
         $remote = Factory::getRemote();
         $remote->request('module')
               ->from(API_SYNC_PAYMENT_APPOINTMENT)
               ->where([
                  'data' => $data,
                  'service_type' => 'kim'
               ])
               ->execute(true, $header);
         
         $response = $remote->loadVar(false);

         return true;

      } catch (\Throwable $e) {

         Log::error('updateCRMDeposit failed', [
               'CustomerId'    => $customerId,
               'ReceiptId'     => $receiptId,
               'AppointmentId'=> $appointmentId,
               'TotalAmount'   => $totalAmount,
               'Error'         => $e->getMessage(),
         ]);

         throw $e;
      }
   }

   public function insertReceiptLog($receiptId, $staffId, $totalAmount, $type = 'Insert')
   {
      try {

         $receipt = $this->getDetail($receiptId);
         $addedAt = $receipt->AddedAt;

         $data = [
            'ReceiptId'    => $receiptId,
            'UpdatedAt'    => $addedAt,
            'UpdatedBy'    => $staffId,
            'TotalAmount'  => $totalAmount,
            'Type'         => $type
         ];
         Receipt_Log::insert($data);
         return true;
      } catch (\Exception $e) {
         Log::error("insertReceiptLog errors", [$e->getMessage()]);
         throw $e;
      }
   }

   public function insertDepositSendZNS($customerId, $receiptId, $totalAmount, $branchId, $type)
   {
      try {

         $receipt = Receipt::find($receiptId);
         $receiptCode = $receipt->ReceiptCode;

         $branch = Branch::find($branchId);

         $customer = CustomerPhoneNumber::from('CustomerPhoneNumber as cp')
               ->join('Customer as c', 'c.CustomerId', '=', 'cp.CustomerId')
               ->where('cp.CustomerId', $customerId)
               ->where('cp.IsMain', 1)
               ->select('cp.PhoneNumber', 'c.FullName', 'c.CustomerCode')
               ->first();

         $url = API_SEND_DEPOSIT_CUSTOMER;
         if($type == 'Add'){
            $url = API_SEND_DEPOSIT_CUSTOMER;
         } else {
            $url = API_SEND_EDIT_DEPOSIT_CUSTOMER;
         }

         $header = ['Authorization'=>'Bearer ' . JWT_APP_TOKEN];
         $remote = Factory::getRemote();
         $remote->request('module')
               ->from($url)
               ->where([
                  'CustomerName' => $customer->FullName ?? '',
                  'CustomerCode' => $customer->CustomerCode ?? '',
                  'PhoneNumber' => $customer->PhoneNumber,
                  'Date' => date('H:i d/m/Y'),
                  'BranchAddress' => $branch->Address ?? '',
                  'Amount' => number_format($totalAmount ?? 0, 0, ',', '.'),
                  'InvoiceCode' => $receiptCode ?? '']
               )->execute(true, $header);

         $response = $remote->loadVar(false);
         Log::info("SEND_DEPOSIT_CUSTOMER response", [$response]);

         return true;

      } catch (\Exception $e) {
         Log::error("insertDepositSendZNS errors", [$e->getMessage()]);
         return false;
      }
      return false;
   }

   public function insertReceiptService($receiptId, $treatmentId, $staffId, $totalAmount, $receiptType, $customerId, $orderDetails, $receiptDetail)
   {
      try {

         // Thông tin khách hàng
         $infoCustomer = Customer::where('CustomerId', $customerId)->first();
         $consultingStaffId = $infoCustomer->ConsultingStaffId;
         $orderDetailTemporaryId = 0;
         $orderDetailTemporary = OrderDetail::where('TreatmentId', $treatmentId)->where('IsOverPaymentAmount', 1)->lockForUpdate()->first();
         if ($orderDetailTemporary) {
            // update ConsultingStaffId
            $orderDetailTemporary->update([
               'ConsultingStaffId' => $consultingStaffId ?? NULL
            ]);
            $orderDetailTemporaryId = $orderDetailTemporary->OrderDetailId;
         } else {
            $orderDetailIdTemporary = OrderDetail::insertGetId([
               'OrderId'               => 0,
               'TreatmentId'           => $treatmentId,
               'ServiceId'             => 0,
               'ServiceName'           => 'Tạm ứng',
               'Status'                => -1,
               'Quantity'              => 1,
               'Amount'                => 0,
               'TaxAmount'             => 0,
               'TaxPercent'            => 0,
               'DiscountPercent'       => 0,
               'DiscountAmount'        => 0,
               'ProcessState'          => 0,
               'AmountNotAllocated'    => 0,
               'IsPayInstallments'     => 0,
               'ConsultingStaffId'     => $consultingStaffId ?? NULL,
               'IsOverPaymentAmount'   => 1
            ]);
            $orderDetailTemporaryId = $orderDetailIdTemporary;
         }
         Log::info('insertReceiptService', [
               'CustomerId'    => $customerId,
               'ReceiptId'    => $receiptId,
               'TreatmentId' => $treatmentId,
               'OrderDetails'   => $orderDetails,
         ]);
         if ((empty($orderDetails) || !is_array($orderDetails)) && $receiptType == 1) {

            $receiptServiceId = ReceiptService::insertGetId([
               'ReceiptId'      => $receiptId,
               'TreatmentId'    => $treatmentId,
               'OrderDetailId'  => $orderDetailTemporaryId,
               'ServiceId'      => 0,
               'Amount'         => $totalAmount,
               'CreatedStaffId' => $staffId,
               'UpdatedStaffId' => $staffId
            ]);

            OrderDetailFinancialTrans::insert([
               'TreatmentId'       => $treatmentId,
               'CustomerId'        => $customerId,
               'OrderDetailId'     => $orderDetailTemporaryId,
               'OrderChangingId'   => 0,
               'ServiceId'         => 0,
               'ObjectType'        => 'Receipt',
               'ObjectId'          => $receiptId,
               'ObjectDetailType'  => 'ReceiptService',
               'ObjectDetailId'    => $receiptServiceId,
               'ReceiptAmount'     => $totalAmount,
               'Note'              => 'Phiếu thu dịch vụ bỏ vào dịch vụ tạm ứng',
               'CreatedStaffId'    => $staffId,
               'CreatedDate'       => Carbon::now()->toDateTimeString()
            ]);

            return true;
         }

         switch ($receiptType) {
            case 1: // Thu tiền điều trị nha khoa

               $orderDetailIds = array_column($orderDetails, 'OrderDetailId');
               // Lấy các ID có IsPayInstallments = 1
               $installmentIds = OrderDetail::whereIn('OrderDetailId', $orderDetailIds)
                  ->where('IsPayInstallments', 1)
                  ->pluck('OrderDetailId')
                  ->toArray();

               // Loại bỏ khỏi mảng ban đầu
               $orderDetailIds = array_values(
                  array_diff($orderDetailIds, $installmentIds)
               );

               $financialMap = OrderDetailFinancial::whereIn('OrderDetailId', $orderDetailIds)
                  ->selectRaw('
                     OrderDetailId,
                     COALESCE(SUM(InvoiceAmount),0)  as InvoiceAmount,
                     COALESCE(SUM(TotalAmount),0)    as TotalAmount
                  ')
                  ->groupBy('OrderDetailId')
                  ->get()
                  ->keyBy('OrderDetailId');

               $remainDetails = [];
               $detailMap     = [];

               foreach ($orderDetails as $detail) {

                  $f = $financialMap[$detail['OrderDetailId']] ?? null;

                  $invoiceAmount = (int) ($f->InvoiceAmount ?? 0);
                  $receiptAmount = (int) ($f->TotalAmount ?? 0);

                  $invoiceRemain = max($invoiceAmount - $receiptAmount, 0);
                  $serviceRemain = (int) $detail['OrderDetailAmount'] - $receiptAmount;

                  if ($serviceRemain <= 0) {
                     continue;
                  }

                  $detail['InvoiceRemain'] = min($invoiceRemain, $serviceRemain);
                  $detail['ServiceRemain'] = $serviceRemain;

                  $remainDetails[] = $detail;
                  $detailMap[$detail['OrderDetailId']] = $detail;
               }

               if (empty($remainDetails)) {
                  break;
               }

               $remainAmount = (int) $totalAmount;
               $allocatedMap = [];

               /**
                * ======================================================
               * RULE 1 – Ưu tiên Invoice
               * ======================================================
               */
               foreach ($remainDetails as $d) {

                  if ($remainAmount <= 0) {
                     break;
                  }

                  if ($d['InvoiceRemain'] <= 0) {
                     continue;
                  }

                  $allocate = min($d['InvoiceRemain'], $remainAmount);

                  $allocatedMap[$d['OrderDetailId']] =
                     ($allocatedMap[$d['OrderDetailId']] ?? 0) + $allocate;

                  $remainAmount -= $allocate;
               }

               /**
                * ======================================================
               * RULE 2 – Chia đều theo avg
               * ======================================================
               */
               if ($remainAmount > 0) {

                  $services = [];

                  foreach ($remainDetails as $d) {

                     $id   = $d['OrderDetailId'];
                     $used = $allocatedMap[$id] ?? 0;
                     $can  = max($d['ServiceRemain'] - $used, 0);

                     if ($can > 0) {
                        $services[] = [
                           'OrderDetailId' => $id,
                           'Remain'        => $can
                        ];
                     }
                  }

                  while ($remainAmount > 0 && !empty($services)) {

                     $count = count($services);
                     $avg   = intdiv($remainAmount, $count);

                     // Không chia được đều nữa → sang RULE 3
                     if ($avg <= 0) {
                        break;
                     }

                     $next = [];

                     foreach ($services as $s) {

                        if ($remainAmount <= 0) break;

                        $give = min($avg, $s['Remain']);

                        if ($give > 0) {
                           $allocatedMap[$s['OrderDetailId']] =
                              ($allocatedMap[$s['OrderDetailId']] ?? 0) + $give;

                           $remainAmount -= $give;
                           $s['Remain']  -= $give;
                        }

                        if ($s['Remain'] > 0) {
                           $next[] = $s;
                        }
                     }

                     $services = $next;
                  }
               }

               /**
                * ======================================================
               * RULE 3 – Tiền còn dư → dồn vào 1 DV tạm ứng
               * ======================================================
               */
               if ($remainAmount > 0) {

                  $receiptServiceId = ReceiptService::insertGetId([
                     'ReceiptId'      => $receiptId,
                     'TreatmentId'    => $treatmentId,
                     'OrderDetailId'  => $orderDetailTemporaryId,
                     'ServiceId'      => 0,
                     'Amount'         => $remainAmount,
                     'CreatedStaffId' => $staffId,
                     'UpdatedStaffId' => $staffId
                  ]);

                  OrderDetailFinancialTrans::insert([
                     'TreatmentId'       => $treatmentId,
                     'CustomerId'        => $customerId,
                     'OrderDetailId'     => $orderDetailTemporaryId,
                     'OrderChangingId'   => 0,
                     'ServiceId'         => 0,
                     'ObjectType'        => 'Receipt',
                     'ObjectId'          => $receiptId,
                     'ObjectDetailType'  => 'ReceiptService',
                     'ObjectDetailId'    => $receiptServiceId,
                     'ReceiptAmount'     => $remainAmount,
                     'Note'              => 'Phiếu thu dịch vụ dư tiền bỏ vào dịch vụ tạm ứng',
                     'CreatedStaffId'    => $staffId,
                     'CreatedDate'       => Carbon::now()->toDateTimeString()
                  ]);
               }

               /**
                * ======================================================
               * INSERT DATA
               * ======================================================
               */
               foreach ($allocatedMap as $orderDetailId => $amount) {

                  if ($amount <= 0) {
                     continue;
                  }

                  $d = $detailMap[$orderDetailId];

                  $receiptServiceId = ReceiptService::insertGetId([
                     'ReceiptId'      => $receiptId,
                     'TreatmentId'    => $treatmentId,
                     'OrderDetailId'  => $orderDetailId,
                     'ServiceId'      => $d['ServiceId'],
                     'Amount'         => $amount,
                     'CreatedStaffId' => $staffId,
                     'UpdatedStaffId' => $staffId
                  ]);
                  $infoOrderDetail = OrderDetail::where('OrderDetailId', $orderDetailId)->where('TreatmentId', $treatmentId)->first(); 
                  OrderDetailFinancialTrans::insert([
                     'TreatmentId'       => $treatmentId,
                     'CustomerId'        => $customerId,
                     'OrderDetailId'     => $orderDetailId,
                     'OrderChangingId'   => $d['OrderChangingId'],
                     'ServiceId'         => $d['ServiceId'],
                     'ObjectType'        => 'Receipt',
                     'ObjectId'          => $receiptId,
                     'ObjectDetailType'  => 'ReceiptService',
                     'ObjectDetailId'    => $receiptServiceId,
                     'ReceiptAmount'     => $amount,
                     'Note'              => 'Phiếu thu dịch vụ',
                     'CreatedStaffId'    => $staffId,
                     'CreatedDate'       => Carbon::now()->toDateTimeString(),
                     'ConsultingStaffId' => isset($infoOrderDetail->ConsultingStaffId) ? $infoOrderDetail->ConsultingStaffId : NULL,
                     'ConsultedBranchId' => isset($infoOrderDetail->ConsultedBranchId) ? $infoOrderDetail->ConsultedBranchId : NULL
                  ]);
               }

               break;

            case 2: // Phiếu công nợ

               // Trả lại tiền vào ví khách hàng nếu tiền phân bổ vào dịch vụ bảo hiểm khi xác nhận dịch vụ
               $totalAmountRefund = 0;
               $now = Carbon::now()->toDateTimeString();
               $orderDetailIds = array_column($receiptDetail, 'OrderDetailId');
               // Lấy các ID có IsPayInstallments = 1
               $installmentIds = OrderDetail::whereIn('OrderDetailId', $orderDetailIds)
                  ->where('IsPayInstallments', 1)
                  ->pluck('OrderDetailId')
                  ->toArray();

               // Loại bỏ khỏi mảng ban đầu
               $orderDetailIds = array_values(
                  array_diff($orderDetailIds, $installmentIds)
               );
               $orderDetailFinancial = OrderDetailFinancial::whereIn('OrderDetailId',$orderDetailIds)->where('TotalAmount','>',0)->get();
               if ($orderDetailFinancial->isNotEmpty()) {
                  foreach($orderDetailFinancial as $financial){
                     $totalAmountRefund += $financial->TotalAmount;
                  }

                  $transferId = OrderTransferAmount::insertGetId([
                     'TreatmentId'           => $treatmentId,
                     'FromOrderChangingId'   => 0,
                     'FromServiceId'         => 0,
                     'TotalAmount'           => $totalAmountRefund,
                     'CreatedDate'           => $now,
                     'CreatedStaffId'        => $staffId,
                     'UpdatedStaffId'        => $staffId,
                     'UpdatedDate'           => $now
                  ]);
                  foreach ($orderDetailFinancial as $item) {
                     $detailId = OrderTransferAmountDetail::insertGetId([
                        'OrderTransferAmountId' => $transferId,
                        'FromOrderDetailId'     => $item['OrderDetailId'],
                        'ToOrderDetailId'       => $orderDetailTemporaryId,
                        'Amount'                => $item['TotalAmount'],
                        'CreatedDate'           => $now,
                        'CreatedStaffId'        => $staffId,
                        'UpdatedStaffId'        => $staffId,
                        'UpdatedDate'           => $now,
                     ]);

                     $infoOrderDetail = OrderDetail::where('OrderDetailId', $item['OrderDetailId'])->where('TreatmentId', $treatmentId)->first();
                     OrderDetailFinancialTrans::insert([
                        'TreatmentId'        => $treatmentId,
                        'CustomerId'         => $customerId,
                        'OrderDetailId'      => $item['OrderDetailId'],
                        'ObjectType'         => 'TransferAmountService',
                        'ObjectId'           => $transferId,
                        'ObjectDetailType'   => 'OrderTransferAmountDetail',
                        'ObjectDetailId'     => $detailId,
                        'TransferAmount'     => -$item['TotalAmount'],
                        'CreatedStaffId'     => $staffId,
                        'CreatedDate'        => $now,
                        'ConsultingStaffId' => isset($infoOrderDetail->ConsultingStaffId) ? $infoOrderDetail->ConsultingStaffId : NULL,
                        'ConsultedBranchId' => isset($infoOrderDetail->ConsultedBranchId) ? $infoOrderDetail->ConsultedBranchId : NULL
                     ]);

                     // Ghi dương Order nhận
                     OrderDetailFinancialTrans::insert([
                        'TreatmentId'        => $treatmentId,
                        'CustomerId'         => $customerId,
                        'OrderDetailId'      => $orderDetailTemporaryId,
                        'ObjectType'         => 'TransferAmountService',
                        'ObjectId'           => $transferId,
                        'ObjectDetailType'   => 'OrderTransferAmountDetail',
                        'ObjectDetailId'     => $detailId,
                        'TransferAmount'     => $item['TotalAmount'],
                        'CreatedStaffId'     => $staffId,
                        'CreatedDate'        => $now,
                     ]);
                  }
               }

               $remainAmount = 0;
               // Tiền từ phiếu công nợ phân bổ vào dịch vụ
               foreach ($receiptDetail as $detail) {
                  if (empty($detail['OrderDetailId']) || $detail['OrderDetailId'] <= 0) {
                     continue;
                  }

                  $financial = OrderDetailFinancial::where('OrderDetailId',$detail['OrderDetailId'])->first();

                  if (!$financial) {
                     continue;
                  }

                  $receiptAmount = $financial ? (float)($financial->OrderDetailAmount - $financial->TotalAmount) : 0;
                  $amount = $detail['Amount'] - $receiptAmount;

                  $remainAmount += $amount > 0 ? $amount : 0;

                  $receiptServiceId = ReceiptService::insertGetId([
                     'ReceiptId'       => $receiptId,
                     'TreatmentId'     => $treatmentId,
                     'OrderDetailId'   => $detail['OrderDetailId'],
                     'ServiceId'       => $detail['ServiceId'],
                     'Amount'          => $amount <= 0 ? $detail['Amount'] : $receiptAmount,
                     'CreatedStaffId'  => $staffId,
                     'UpdatedStaffId'  => $staffId
                  ]);

                  if ($receiptServiceId) {
                     $infoOrderDetail = OrderDetail::where('OrderDetailId', $detail['OrderDetailId'])->where('TreatmentId', $treatmentId)->first(); 
                     OrderDetailFinancialTrans::insert([
                        'TreatmentId'        => $treatmentId,
                        'CustomerId'         => $customerId,
                        'OrderDetailId'      => $detail['OrderDetailId'],
                        'OrderChangingId'    => $detail['OrderChangingId'],
                        'ServiceId'          => $detail['ServiceId'],
                        'ObjectType'         => 'Receipt',
                        'ObjectId'           => $receiptId,
                        'ObjectDetailType'   => 'ReceiptService',
                        'ObjectDetailId'     => $receiptServiceId,
                        'ReceiptAmount'      => $amount <= 0 ? $detail['Amount'] : $receiptAmount,
                        'InsuranceAmount'    => $amount <= 0 ? $detail['Amount'] : $receiptAmount,
                        'Note'               => 'Phiếu công nợ',
                        'CreatedStaffId'     => $staffId,
                        'CreatedDate'        => Carbon::now()->toDateTimeString(),
                        'ConsultingStaffId' => isset($infoOrderDetail->ConsultingStaffId) ? $infoOrderDetail->ConsultingStaffId : NULL,
                        'ConsultedBranchId' => isset($infoOrderDetail->ConsultedBranchId) ? $infoOrderDetail->ConsultedBranchId : NULL
                     ]);
                  }
               }

               // Tiền dư từ phiếu công nợ đổ về ví khách hàng
               if ($remainAmount > 0) {
                  $orderDetailTemporary = OrderDetail::where('TreatmentId', $treatmentId)->where('IsOverPaymentAmount', 1)->lockForUpdate()->first();
                  if ($orderDetailTemporary) {
                     // update ConsultingStaffId
                     $orderDetailTemporary->update([
                        'ConsultingStaffId' => $consultingStaffId ?? NULL
                     ]);
                     $orderDetailId = $orderDetailTemporary->OrderDetailId;
                  } else {
                     $orderDetailIdTemporary = OrderDetail::insertGetId([
                        'OrderId'               => 0,
                        'TreatmentId'           => $treatmentId,
                        'ServiceId'             => 0,
                        'ServiceName'           => 'Tạm ứng',
                        'Status'                => -1,
                        'Quantity'              => 1,
                        'Amount'                => 0,
                        'TaxAmount'             => 0,
                        'TaxPercent'            => 0,
                        'DiscountPercent'       => 0,
                        'DiscountAmount'        => 0,
                        'ProcessState'          => 0,
                        'AmountNotAllocated'    => 0,
                        'IsPayInstallments'     => 0,
                        'ConsultingStaffId'     => $consultingStaffId ?? NULL,
                        'IsOverPaymentAmount'   => 1
                     ]);
                     $orderDetailId = $orderDetailIdTemporary;
                  }

                  $receiptServiceId = ReceiptService::insertGetId([
                     'ReceiptId'      => $receiptId,
                     'TreatmentId'    => $treatmentId,
                     'OrderDetailId'  => $orderDetailId,
                     'ServiceId'      => 0,
                     'Amount'         => $remainAmount,
                     'CreatedStaffId' => $staffId,
                     'UpdatedStaffId' => $staffId
                  ]);

                  OrderDetailFinancialTrans::insert([
                     'TreatmentId'       => $treatmentId,
                     'CustomerId'        => $customerId,
                     'OrderDetailId'     => $orderDetailId,
                     'OrderChangingId'   => 0,
                     'ServiceId'         => 0,
                     'ObjectType'        => 'Receipt',
                     'ObjectId'          => $receiptId,
                     'ObjectDetailType'  => 'ReceiptService',
                     'ObjectDetailId'    => $receiptServiceId,
                     'ReceiptAmount'     => $remainAmount,
                     'Note'              => 'Dư tiền phiếu công nợ bỏ vào dịch vụ tạm ứng',
                     'CreatedStaffId'    => $staffId,
                     'CreatedDate'       => Carbon::now()->toDateTimeString()
                  ]);

               }

               // Nếu tiền ví dư và tiền phân bổ vào dịch vụ bảo hiểm chưa đủ thì phân bổ từ ví vào dịch vụ tiếp
               $infoOrderDetail = OrderDetail::where('OrderDetail.TreatmentId', $treatmentId)->where('OrderDetail.IsOverPaymentAmount', 1)
                ->join('OrderDetailFinancial as odf', 'odf.OrderDetailId', '=', 'OrderDetail.OrderDetailId')
                ->lockForUpdate()->first();
               
               if ($infoOrderDetail) {
                  $availableAmount = $infoOrderDetail->AvailableAmount ?? 0;
                  if ($availableAmount > 0) {
                     $this->transferDepositToOrderDetail($availableAmount, $orderDetailIds, $treatmentId, $customerId, $staffId);
                  }
               }
               break;
            case 3: // Phiếu thu trả góp

               $orderDetailId   = $orderDetails[0]['OrderDetailId'];
               $serviceId       = $orderDetails[0]['ServiceId'];
               $orderChangingId = $orderDetails[0]['OrderChangingId'];

               $plan = OrderInstallmentPlan::where('OrderDetailId', $orderDetailId)
                  ->lockForUpdate()
                  ->first();

               if (!$plan) {
                   $plan = $this->createPayInstallmentsByOrderDetail_v2($orderDetailId);

                  if (!$plan) {
                     throw new \Exception('Không tạo được kế hoạch trả góp');
                  }
               }
               $remainAmount = $totalAmount;

               $receiptServiceId = ReceiptService::insertGetId([
                  'ReceiptId'      => $receiptId,
                  'TreatmentId'    => $treatmentId,
                  'OrderDetailId'  => $orderDetailId,
                  'ServiceId'      => $serviceId,
                  'Amount'         => $totalAmount,
                  'CreatedStaffId' => $staffId,
                  'UpdatedStaffId' => $staffId
               ]);
               $infoOrderDetail = OrderDetail::where('OrderDetailId', $orderDetailId)->where('TreatmentId', $treatmentId)->first();

               OrderDetailFinancialTrans::insert([
                  'TreatmentId'       => $treatmentId,
                  'CustomerId'        => $customerId,
                  'OrderDetailId'     => $orderDetailId,
                  'OrderChangingId'   => $orderChangingId,
                  'ServiceId'         => $serviceId,
                  'ObjectType'        => 'Receipt',
                  'ObjectId'          => $receiptId,
                  'ObjectDetailType'  => 'ReceiptService',
                  'ObjectDetailId'    => $receiptServiceId,
                  'ReceiptAmount'     => $totalAmount,
                  'Note'              => 'Phiếu thu dịch vụ trả góp',
                  'CreatedStaffId'    => $staffId,
                  'CreatedDate'       => Carbon::now()->toDateTimeString(),
                  'ConsultingStaffId' => isset($infoOrderDetail->ConsultingStaffId) ? $infoOrderDetail->ConsultingStaffId : NULL,
                  'ConsultedBranchId' => isset($infoOrderDetail->ConsultedBranchId) ? $infoOrderDetail->ConsultedBranchId : NULL
               ]);

               /*
               |--------------------------------------------------------------------------
               | 1. Thanh toán DownPayment nếu chưa đủ
               |--------------------------------------------------------------------------
               */

               if ($remainAmount > 0 && $plan->PaidAmount < $plan->DownPaymentRequired) {

                  $needDownPayment = $plan->DownPaymentRequired - $plan->PaidAmount;

                  $payDown = min($remainAmount, $needDownPayment);

                  $remainAmount -= $payDown;
               }

               /*
               |--------------------------------------------------------------------------
               | 2. Phân bổ vào các kỳ
               |--------------------------------------------------------------------------
               */

               if ($remainAmount > 0) {

                  $schedules = OrderInstallmentSchedule::where('OrderDetailId', $orderDetailId)
                     ->whereRaw('PaidAmount < DueAmount')
                     ->orderBy('PeriodNumber')
                     ->lockForUpdate()
                     ->get();
                  
                  foreach ($schedules as $schedule) {
                     if ($remainAmount <= 0) {
                        break;
                     }

                     $need = $schedule->DueAmount - $schedule->PaidAmount;

                     if ($need <= 0) {
                        continue;
                     }

                     // Số tiền thực sự trả cho kỳ này
                     $pay = min($remainAmount, $need);

                     // Cập nhật PaidAmount
                     $schedule->PaidAmount += $pay;

                     // Nếu đã trả đủ kỳ này thì set status = 100
                     if ($schedule->PaidAmount >= $schedule->DueAmount) {
                        $schedule->OrderInstallmentScheduleStatus = 100;
                     }

                     $schedule->save();

                     // Giảm số tiền còn lại
                     $remainAmount -= $pay;

                     // Ghi nhận transaction
                     OrderInstallmentTrans::insert([
                        'OrderDetailId' => $orderDetailId,
                        'OrderInstallmentScheduleId' => $schedule->OrderInstallmentScheduleId,
                        'PeriodNumber' => $schedule->PeriodNumber,
                        'ObjectType' => 'Receipt',
                        'ObjectId' => $receiptId,
                        'ObjectDetailType' => 'ReceiptService',
                        'ObjectDetailId' => $receiptServiceId,
                        'Amount' => $pay,
                        'CreatedDate' => Carbon::now()->toDateTimeString(),
                        'CreatedBy' => $staffId
                     ]);
                  }
               }

               /*
               |--------------------------------------------------------------------------
               | 3. Update Plan (tổng tiền)
               |--------------------------------------------------------------------------
               */

               if ($totalAmount > 0) {

                  $plan->PaidAmount += $totalAmount;

                  $plan->OutstandingAmount -= $totalAmount;

                  if ($plan->OutstandingAmount < 0) {
                     $plan->OutstandingAmount = 0;
                  }

                  $remainingPeriods = OrderInstallmentSchedule::where('OrderDetailId', $orderDetailId)
                     ->whereColumn('PaidAmount', '<', 'DueAmount')
                     ->count();

                  $plan->RemainingPeriods = $remainingPeriods;

                  if ($plan->OutstandingAmount == 0) {
                     $plan->OrderInstallmentPlanStatus = 100;
                  }

                  $plan->save();
               }

               /*
               |--------------------------------------------------------------------------
               | 4. Update Status Plan (xử lý overdue)
               |--------------------------------------------------------------------------
               */

               // Reload plan từ DB để lấy status mới nhất
               $plan = $plan->fresh();

               // Nếu plan đang ở trạng thái overdue (50), kiểm tra xem các kỳ đã thanh toán chưa
               if ($plan->OrderInstallmentPlanStatus == 50) {
                  
                  // Lấy tất cả các kỳ đã đến hạn (DueDate <= now)
                  $currentDay = Carbon::now()->format('Y-m-d');
                  $overdueSchedules = OrderInstallmentSchedule::where('OrderDetailId', $orderDetailId)
                     ->where('DueDate', '<=', $currentDay)
                     ->get();

                  // Kiểm tra xem tất cả các kỳ đã đến hạn đã được thanh toán đủ chưa
                  $allDuePaid = true;
                  foreach ($overdueSchedules as $schedule) {
                     if ($schedule->PaidAmount < $schedule->DueAmount) {
                        $allDuePaid = false;
                        break;
                     }
                  }

                  // Nếu tất cả các kỳ đã đến hạn đã được thanh toán đủ
                  if ($allDuePaid && $overdueSchedules->count() > 0) {
                     // Kiểm tra xem còn kỳ nào chưa thanh toán không
                     if ($plan->OutstandingAmount > 0) {
                        // Còn nợ nhưng các kỳ đã đến hạn đã thanh toán đủ -> chuyển về active (10)
                        $plan->OrderInstallmentPlanStatus = 10;
                        $plan->OrderInstallmentPlanStatusTime = Carbon::now()->toDateTimeString();
                        $plan->save();
                     }
                     // Nếu OutstandingAmount = 0 thì đã được set status = 100 ở trên
                  }
               }

               break;
            default:
               return true;
         }
         return true;
      } catch (\Exception $e) {
         Log::error("insertReceiptService errors", [$e->getMessage()]);
         throw $e;
      }
   }

   public function createPayInstallmentsByOrderDetail($orderDetailId)
   {
      try {

         if (empty($orderDetailId)) {
            throw new \Exception('OrderDetailId NULL');
         }
         
         $staffId = Auth::user()['StaffId'] ?? 0;
         $query = OrderDetail::where('OrderDetailId', $orderDetailId)
               ->join('ServiceInstallmentConfig as sic', 'OrderDetail.ServiceId', '=', 'sic.ServiceId')
               ->where('sic.StartDate', '<=', Carbon::now()->toDateString())
               ->where('sic.EndDate', '>=', Carbon::now()->toDateString())
               ->select('sic.*', 'OrderDetail.OrderDetailId', 'OrderDetail.Amount as OrderDetailAmount');
         $infoOrderDetails = $query->get()->toArray();

         if (empty($infoOrderDetails)) {
            throw new \Exception('Không tìm thấy dịch vụ nào có cấu hình trả góp hoặc dịch vụ đã hết thời gian cho trả góp');
         }
         

         if (!empty($infoOrderDetails)) {

            $now = Carbon::now();
            $today = $now->toDateString();
            $nowDateTime = $now->toDateTimeString();

            foreach ($infoOrderDetails as $v) {

               $exists = OrderInstallmentPlan::where('OrderDetailId', $v['OrderDetailId'])->exists();
               if ($exists) {
                  continue;
               }

               $downPaymentRequired = $v['MinDownPaymentPercent']
                  * $v['OrderDetailAmount'] / 100;

               $principal = $v['OrderDetailAmount'] - $downPaymentRequired;

               $monthlyAmount = (float) $v['DefaultMonthlyAmount'];

               if ($monthlyAmount <= 0) {
                  throw new \Exception("MonthlyAmount invalid");
               }

               $totalPeriods = ceil($principal / $monthlyAmount);

               OrderInstallmentPlan::create([
                  'OrderDetailId' => $v['OrderDetailId'],
                  'OrderInstallmentPlanStatus' => 10,
                  'OrderInstallmentPlanStatusTime' => $today,
                  'ServiceInstallmentConfigId' => $v['ServiceInstallmentConfigId'],
                  'DownPaymentRequired' => $downPaymentRequired,
                  'MonthlyAmount' => $monthlyAmount,
                  'TotalPeriods' => $totalPeriods,
                  'RemainingPeriods' => $totalPeriods,
                  'PaidAmount' => 0,
                  'OutstandingAmount' => $v['OrderDetailAmount'],
                  'StartInstallmentDate' => $today,
                  'CreatedDate' => $nowDateTime,
                  'CreatedBy' => $staffId,
                  'UpdatedDate' => $nowDateTime,
                  'UpdatedBy' => $staffId
               ]);

               $remainingAmount = $principal;

               for ($i = 1; $i <= $totalPeriods; $i++) {

                  $dueAmount = ($i == $totalPeriods)
                        ? $remainingAmount
                        : min($monthlyAmount, $remainingAmount);

                  OrderInstallmentSchedule::create([
                        'OrderDetailId' => $v['OrderDetailId'],
                        'PeriodNumber' => $i,
                        'OrderInstallmentScheduleStatus' => 10,
                        'DueDate' => $now->copy()->addMonths($i),
                        'DueAmount' => $dueAmount,
                        'PaidAmount' => 0,
                        'CreatedDate' => $nowDateTime,
                        'CreatedBy' => $staffId,
                        'UpdatedDate' => $nowDateTime,
                        'UpdatedBy' => $staffId
                  ]);

                  $remainingAmount -= $dueAmount;
               }
            }
         }
         return true;

      } catch (\Exception $e) {
         Log::error("createPayInstallmentsByOrderDetail errors", [$e->getMessage()]);
         throw $e;
      }
   }


    public function createPayInstallmentsByOrderDetail_v2($orderDetailId)
    {
        try {
            if (empty($orderDetailId)) {
                throw new \Exception('OrderDetailId NULL');
            }

            $staffId     = Auth::user()['StaffId'] ?? 0;
            $now         = Carbon::now();
            $today       = $now->toDateString();
            $nowDateTime = $now->toDateTimeString();

            $info = OrderDetail::where('OrderDetailId', $orderDetailId)
                ->join('ServiceInstallmentConfig as sic', 'OrderDetail.ServiceId', '=', 'sic.ServiceId')
                ->where('sic.StartDate', '<=', $today)
                ->where('sic.EndDate', '>=', $today)
                ->select('sic.*', 'OrderDetail.OrderDetailId', 'OrderDetail.Amount as OrderDetailAmount')
                ->first();

            if (!$info) {
                throw new \Exception('Không tìm thấy dịch vụ nào có cấu hình trả góp hoặc dịch vụ đã hết thời gian cho trả góp');
            }

            $downPaymentRequired = $info->MinDownPaymentPercent * $info->OrderDetailAmount / 100;
            $principal           = $info->OrderDetailAmount - $downPaymentRequired;
            $monthlyAmount       = (float) $info->DefaultMonthlyAmount;

            if ($monthlyAmount <= 0) {
                throw new \Exception("MonthlyAmount invalid");
            }

            $totalPeriods = (int) ceil($principal / $monthlyAmount);

            // Insert Plan → trả về model
            $plan = OrderInstallmentPlan::create([
                'OrderDetailId'                  => $orderDetailId,
                'OrderInstallmentPlanStatus'     => 10,
                'OrderInstallmentPlanStatusTime' => $today,
                'ServiceInstallmentConfigId'     => $info->ServiceInstallmentConfigId,
                'DownPaymentRequired'            => $downPaymentRequired,
                'MonthlyAmount'                  => $monthlyAmount,
                'TotalPeriods'                   => $totalPeriods,
                'RemainingPeriods'               => $totalPeriods,
                'PaidAmount'                     => 0,
                'OutstandingAmount'              => $info->OrderDetailAmount,
                'StartInstallmentDate'           => $today,
                'CreatedDate'                    => $nowDateTime,
                'CreatedBy'                      => $staffId,
                'UpdatedDate'                    => $nowDateTime,
                'UpdatedBy'                      => $staffId,
            ]);

            $schedules       = [];
            $remainingAmount = $principal;

            for ($i = 1; $i <= $totalPeriods; $i++) {
                $dueAmount = ($i === $totalPeriods)
                    ? $remainingAmount
                    : min($monthlyAmount, $remainingAmount);

                $schedules[] = [
                    'OrderDetailId'                  => $orderDetailId,
                    'PeriodNumber'                   => $i,
                    'OrderInstallmentScheduleStatus' => 10,
                    'DueDate'                        => $now->copy()->addMonths($i)->toDateString(),
                    'DueAmount'                      => $dueAmount,
                    'PaidAmount'                     => 0,
                    'CreatedDate'                    => $nowDateTime,
                    'CreatedBy'                      => $staffId,
                    'UpdatedDate'                    => $nowDateTime,
                    'UpdatedBy'                      => $staffId,
                ];

                $remainingAmount -= $dueAmount;
            }

            OrderInstallmentSchedule::insert($schedules);

            return $plan;

        } catch (\Exception $e) {
            Log::error("createPayInstallmentsByOrderDetail errors", [$e->getMessage()]);
            throw $e;
        }
    }

   public function transferDepositToOrderDetail($overPaymentAmount, $orderDetailAgree, $treatmentId, $customerId, $staffId) {
      try {
         $now = Carbon::now();
         $orderDetailTemporary = OrderDetail::where('TreatmentId', $treatmentId)->where('IsOverPaymentAmount', 1)->lockForUpdate()->first();
         if ($orderDetailTemporary) {
               $orderDetailId = $orderDetailTemporary->OrderDetailId;
         } else {
               $orderDetailIdTemporary = OrderDetail::insertGetId([
               'OrderId'               => 0,
               'TreatmentId'           => $treatmentId,
               'ServiceId'             => 0,
               'ServiceName'           => 'Tạm ứng',
               'Status'                => -1,
               'Quantity'              => 1,
               'Amount'                => 0,
               'TaxAmount'             => 0,
               'TaxPercent'            => 0,
               'DiscountPercent'       => 0,
               'DiscountAmount'        => 0,
               'ProcessState'          => 0,
               'AmountNotAllocated'    => 0,
               'IsPayInstallments'     => 0,
               'IsOverPaymentAmount'   => 1
               ]);
               $orderDetailId = $orderDetailIdTemporary;
         }

         $toOrderDetail = OrderDetailFinancial::select( // Danh sách OrderDetail nhận tiền
                  'OrderDetailFinancial.OrderDetailId',
                  'OrderDetailFinancial.OrderDetailAmount',
                  'OrderDetailFinancial.TotalAmount'
               )
               ->join('OrderDetail as od', 'od.OrderDetailId', '=', 'OrderDetailFinancial.OrderDetailId')
               ->where('OrderDetailFinancial.CustomerId', $customerId)
               ->where('OrderDetailFinancial.TreatmentId', $treatmentId)
               ->where('od.IsPayInstallments', 0)
               ->whereIn('OrderDetailFinancial.OrderDetailId', $orderDetailAgree)->get()->toArray();

         if (!$toOrderDetail) {
               throw new \Exception('Không tìm thấy OrderDetail chuyển tiền phù hợp');
         }

         $totalAmountOrderDetail = array_sum(array_column($toOrderDetail, 'OrderDetailAmount'));
         $totalAmount = array_sum(array_column($toOrderDetail, 'TotalAmount'));
         $remainAmount = (int) $totalAmountOrderDetail - (int) $totalAmount;
         $transferId = OrderTransferAmount::insertGetId([
               'TreatmentId'           => $treatmentId,
               'FromOrderChangingId'   => 0,
               'FromServiceId'         => 0,
               'TotalAmount'           => $overPaymentAmount > $remainAmount ? $remainAmount : $overPaymentAmount,
               'CreatedDate'           => $now,
               'CreatedStaffId'        => $staffId,
               'UpdatedStaffId'        => $staffId,
               'UpdatedDate'           => $now
         ]);

         $remainOverPaymentAmount = $overPaymentAmount;
         foreach ($toOrderDetail as $item) {

               $transferAmount = min($remainOverPaymentAmount, (int) $item['OrderDetailAmount']);

               $remainOverPaymentAmount -= $transferAmount;
               $detailId = OrderTransferAmountDetail::insertGetId([
                  'OrderTransferAmountId' => $transferId,
                  'FromOrderDetailId'     => $orderDetailId,
                  'ToOrderDetailId'       => $item['OrderDetailId'],
                  'Amount'                => $transferAmount,
                  'CreatedDate'           => $now,
                  'CreatedStaffId'        => $staffId,
                  'UpdatedStaffId'        => $staffId,
                  'UpdatedDate'           => $now,
               ]);

               // Ghi âm nguồn
               OrderDetailFinancialTrans::insert([
                  'TreatmentId'        => $treatmentId,
                  'CustomerId'         => $customerId,
                  'OrderDetailId'      => $orderDetailId,
                  'ObjectType'         => 'TransferAmountService',
                  'ObjectId'           => $transferId,
                  'ObjectDetailType'   => 'OrderTransferAmountDetail',
                  'ObjectDetailId'     => $detailId,
                  'TransferAmount'     => -$transferAmount,
                  'CreatedStaffId'     => $staffId,
                  'CreatedDate'        => $now,
               ]);

               // Ghi dương Order nhận
               OrderDetailFinancialTrans::insert([
                  'TreatmentId'        => $treatmentId,
                  'CustomerId'         => $customerId,
                  'OrderDetailId'      => $item['OrderDetailId'],
                  'ObjectType'         => 'TransferAmountService',
                  'ObjectId'           => $transferId,
                  'ObjectDetailType'   => 'OrderTransferAmountDetail',
                  'ObjectDetailId'     => $detailId,
                  'TransferAmount'     => $transferAmount,
                  'CreatedStaffId'     => $staffId,
                  'CreatedDate'        => $now,
               ]);

               if ($remainOverPaymentAmount <= 0) {
                  break;
               }
         }

         return true;
      } catch (\Throwable $e) {
         Log::error('transferDepositToOrderDetail error', [
               'message' => $e->getMessage()
         ]);
         throw $e;
      }
   }

   public function updateStatusOrderDetail(array $orderDetails, $receiptId)
   {
      try {

         $orderDetails = array_filter($orderDetails);
         if (empty($orderDetails)) {
               return 0;
         }

         foreach ($orderDetails as $orderDetailId) {

            $financial = OrderDetailFinancial::where('OrderDetailId', $orderDetailId)->where('IsOverPaymentAmount','<>',1)->first();

            $status = 50;
            $totalAmount = 0;
            if ($financial) {
               $orderDetailAmount = (float) ($financial->OrderDetailAmount ?? 0);
               $totalAmount       = (float) ($financial->TotalAmount ?? 0);

               if ($orderDetailAmount === $totalAmount) {
                  $status = 100;
               }

               if ($totalAmount == 0) {
                  $status = 2;
               }
            }

            $orderDetail = OrderDetail::select('OrderDetailId', 'FirstReceiptTime', 'FirstReceiptId')
               ->where('OrderDetailId', $orderDetailId)
               ->first();

            if (!$orderDetail) {
               return false;
            }

            $dataUpdate = [
               'Status' => $status
            ];

            if (is_null($orderDetail->FirstReceiptTime)) {
               $dataUpdate['FirstReceiptTime'] = Carbon::now()->toDateTimeString();
               $dataUpdate['FirstReceiptId'] = $receiptId;
            }
            if ($totalAmount == 0) {
               $dataUpdate['FirstReceiptTime'] = NULL;
               $dataUpdate['FirstReceiptId'] = NULL;
               $dataUpdate['Status'] = 2;
            }

            OrderDetail::where('OrderDetailId', $orderDetailId)->where('IsOverPaymentAmount','<>',1)->update($dataUpdate);
         }

         return true;

      } catch (\Exception $e) {
         Log::error('updateStatusOrderDetail error', [
               'orderDetails' => $orderDetails,
               'message'      => $e->getMessage(),
         ]);

         throw $e;
      }
   }

   public function updateReceipt($data, $totalAmountBeforeUpdate)
   {
      try {

         DB::beginTransaction();

         $receiptId = $data['ReceiptId'];
         $customerId = $data['CustomerId'];
         $arrReceipt = $data['Receipt'];
         $branchId = $data['BranchId'];
         $note = $data['Note'];
         $receiptType = $data['ReceiptType'];
         $staffId = Auth::user()['StaffId'];

         if ($receiptType == 1) {
            $note = "Thu tiền điều trị nha khoa";
         } else {
            $note = "Phiếu thu công nợ";
         }

         $receiptDetail = [];
         $totalAmount = 0;
         $insuranceAmount = 0;
         foreach ($arrReceipt as $v) {
            $totalAmount += $v['Amount'];
            if (isset($v['PartnerCompanyId']) && $v['PartnerCompanyId'] > 0) {
               $result = PartnerCompany::select('pct.PartnerCompanyTypeId')
                  ->join('pos.PartnerCompanyType as pct', function ($join) {
                     $join->on(
                           'PartnerCompany.PartnerCompanyTypeId',
                           '=',
                           'pct.PartnerCompanyTypeId'
                     );
                  })->where('PartnerCompany.PartnerCompanyId', $v['PartnerCompanyId'])->first();
               $result = $result ? $result->PartnerCompanyTypeId : null;
               if ($result && in_array($result, [1, 2])) {
                  $insuranceAmount += $v['Amount'];
               }
            }
            switch (true) {
               case $v['PaymentMethodId'] == 1 && !empty($v['Amount']):
                  $receiptDetail[] = [
                     'BankId' => NULL,
                     'Amount' => $v['Amount'],
                     'PartnerCompanyId' => NULL,
                     'PaymentMethodId' => $v['PaymentMethodId'],
                     'OrderDetailId' => NULL,
                     'OrderDetailAmount' => NULL,
                     'PrepayCardId' => NULL,
                     'InstallmentPaymentPartnerId' => NULL,
                     'MCC' => NULL,
                     'ForControlCode' => isset($v['ForControlCode']) ? $v['ForControlCode'] : NULL,
                     'CreatedStaffId' => $staffId,
                     'UpdatedStaffId' => $staffId,
                     'ServiceId' => NULL
                  ];
                  break;
               case $v['PaymentMethodId'] == 2 && !empty($v['Amount']):
                  if (!isset($v['BankId']) || !is_numeric($v['BankId']) || $v['BankId'] < 1) {
                     Log::error('Hệ thống không xác định được ngân hàng khi tạo phiếu thu.');
                     return false;
                  }
                  $receiptDetail[] = [
                     'BankId' => $v['BankId'],
                     'Amount' => $v['Amount'],
                     'PartnerCompanyId' => NULL,
                     'PaymentMethodId' => $v['PaymentMethodId'],
                     'OrderDetailId' => NULL,
                     'OrderDetailAmount' => NULL,
                     'PrepayCardId' => NULL,
                     'InstallmentPaymentPartnerId' => NULL,
                     'MCC' => NULL,
                     'ForControlCode' => isset($v['ForControlCode']) ? $v['ForControlCode'] : NULL,
                     'CreatedStaffId' => $staffId,
                     'UpdatedStaffId' => $staffId,
                     'ServiceId' => NULL
                  ];
                  break;
               case $v['PaymentMethodId'] == 3 && !empty($v['Amount']):
                  if (!isset($v['BankId']) || !is_numeric($v['BankId']) || $v['BankId'] < 1) {
                     Log::error('Hệ thống không xác định được ngân hàng khi tạo phiếu thu.');
                     return false;
                  }
                  $receiptDetail[] = [
                     'BankId' => $v['BankId'],
                     'Amount' => $v['Amount'],
                     'PartnerCompanyId' => NULL,
                     'PaymentMethodId' => $v['PaymentMethodId'],
                     'OrderDetailId' => NULL,
                     'OrderDetailAmount' => NULL,
                     'PrepayCardId' => NULL,
                     'InstallmentPaymentPartnerId' => NULL,
                     'MCC' => NULL,
                     'ForControlCode' => isset($v['ForControlCode']) ? $v['ForControlCode'] : NULL,
                     'CreatedStaffId' => $staffId,
                     'UpdatedStaffId' => $staffId,
                     'ServiceId' => NULL
                  ];
                  break;
               case $v['PartnerCompanyId'] > 0 && $v['OrderDetailId'] > 0 && !empty($v['Amount']):
                  $receiptDetail[] = [
                     'BankId' => NUll,
                     'Amount' => $v['Amount'],
                     'PartnerCompanyId' => $v['PartnerCompanyId'],
                     'PaymentMethodId' => $v['PaymentMethodId'],
                     'OrderDetailId' => $v['OrderDetailId'],
                     'OrderDetailAmount' => $v['OrderDetailAmount'],
                     'OrderChangingId' => $v['OrderChangingId'],
                     'PrepayCardId' => NULL,
                     'InstallmentPaymentPartnerId' => NULL,
                     'MCC' => NULL,
                     'ForControlCode' => isset($v['ForControlCode']) ? $v['ForControlCode'] : NULL,
                     'CreatedStaffId' => $staffId,
                     'UpdatedStaffId' => $staffId,
                     'ServiceId' => $v['ServiceId']
                  ];
                  break;
               case $v['PartnerCompanyId'] > 0 && $v['InstallmentPaymentPartnerId'] > 0 && !empty($v['Amount']):
                  $receiptDetail[] = [
                     'BankId' => $v['BankId'],
                     'Amount' => $v['Amount'],
                     'PartnerCompanyId' => $v['PartnerCompanyId'],
                     'PaymentMethodId' => $v['PaymentMethodId'],
                     'OrderDetailId' => $v['OrderDetailId'],
                     'OrderDetailAmount' => $v['OrderDetailAmount'],
                     'OrderChangingId' => $v['OrderChangingId'],
                     'PrepayCardId' => NULL,
                     'InstallmentPaymentPartnerId' => $v['InstallmentPaymentPartnerId'],
                     'MCC' => NULL,
                     'ForControlCode' => isset($v['ForControlCode']) ? $v['ForControlCode'] : NULL,
                     'CreatedStaffId' => $staffId,
                     'UpdatedStaffId' => $staffId,
                     'ServiceId' => $v['ServiceId']
                  ];
                  break;
               case $v['PrepayCardId'] > 0 && !empty($v['Amount']):
                  // Ví trả trước
                  break;
               case $v['EpaymentCompanyId'] > 0 && !empty($v['Amount']):
                  // Thanh toán điện tử
                  break;
               default:
                  Log::error('Thông tin phiếu thu không chính xác.');
                  return false;
            }
         }
         if (empty($totalAmount) || $totalAmount < 0) {
            Log::error('Tổng số tiền phải lớn hơn không');
            return false;
         }

         $dataUpdateReceipt = [
            'TotalAmount' => $totalAmount,
            'State' => 1,
            'Note' => $note,
            'UpdatedBy' => $staffId,
            'UpdatedAt' => Carbon::now()->timestamp,
            'InsuranceAmount' => $insuranceAmount,
            'InsuranceUnPaidAmount' => $insuranceAmount,
         ];

         $dataBeforeUpdate['Receipt'] = Receipt::where('ReceiptId', $receiptId)->get()->toArray();
         $dataBeforeUpdate['ReceiptDetail'] = ReceiptDetail::where('ReceiptId', $receiptId)->get()->toArray();

         $this->saveReceiptTracking($receiptId, $dataUpdateReceipt, $receiptDetail, json_encode($dataBeforeUpdate), 20);
         $this->updateDataReceipt($receiptId, $dataUpdateReceipt);
         // Xoá chi tiết phiếu thu cũ
         $this->deleteOldReceiptData($receiptId);
         $this->rollbackPaymentAllocate($customerId, $receiptId);
         // Lưu chi tiết phiếu thu mới
         $this->saveReceiptDetail($receiptDetail, $receiptId);
         $depositId = Deposit::where('CustomerId', $customerId)->value('DepositId');
         $this->saveDepositTransaction($depositId, $totalAmount, $branchId, $customerId, $receiptId, $staffId);

         $this->updateBalanceDeposit($depositId, ($totalAmount - $totalAmountBeforeUpdate));
         $this->updateReceiptService($receiptId, $staffId, $totalAmount, $customerId, $receiptDetail);

         DB::statement('CALL sp_Report_UpdateTargetRevenue_Edit(?)', [$receiptId]);

         dispatch(new UpdateCRMDepositJob($customerId, $receiptId, 0, $totalAmount));
//         $this->updateCRMDeposit($customerId, $receiptId, 0, $totalAmount);

         // Ghi log phiếu thu
         $this->insertReceiptLog($receiptId, $staffId, $totalAmount);
         // Gửi ZNS thông báo phiếu thu
         if ($totalAmountBeforeUpdate != $totalAmount) {
             dispatch(new InsertDepositSendZNSJob($customerId, $receiptId, $totalAmount, $branchId, 'Edit'));
//            $this->insertDepositSendZNS($customerId, $receiptId, $totalAmount, $branchId, 'Edit');
         }
         $orderDetailIds = [];
         if (!empty($arrReceipt) && is_array($arrReceipt)) {
            foreach ($arrReceipt as $d) {
               if (!empty($d['OrderDetailId'])) {
                     $orderDetailIds[] = $d['OrderDetailId'];
               }
            }
            $this->updateStatusOrderDetail($orderDetailIds, $receiptId);
         }
         DB::commit();
         return true;
      } catch (\Exception $e) {
         Log::error("updateReceipt errors", [$e->getMessage()]);
         DB::rollBack();
         return false;
      }
   }

   public function updateDataReceipt($receiptId, $dataUpdateReceipt)
   {
      try {

         Receipt::where('ReceiptId', $receiptId)->update($dataUpdateReceipt);

         return true;
      } catch (\Exception $e) {
         Log::error("updateDataReceipt errors", [$e->getMessage()]);
         throw $e;
      }
   }

   public function checkDepositCustomer($customerId)
   {
      try {

         $deposit = Deposit::where('CustomerId', $customerId)->select('*')->first();

         if ($deposit) {
            return $deposit;
         }

         return null;
      } catch (\Exception $e) {
         Log::error("checkDepositCustomer errors", [$e->getMessage()]);
         return null;
      }
   }

   public function checkReceiptUpdate($receiptId)
   {
      try {

         $receipt = $this->getDetail($receiptId);
         if (!$receipt) {
               return false;
         }

         $createdAt = Carbon::parse($receipt->AddedAt);
         $now       = Carbon::now();

         if (!$createdAt->isSameDay($now)) {
               return false;
         }

         if ($now->hour >= 22) {
               return false;
         }

         return $receipt;
      } catch (\Exception $e) {
         Log::error('checkReceiptUpdate error', [
               'receiptId' => $receiptId,
               'message'   => $e->getMessage()
         ]);
         return false;
      }
   }

   public function checkEditReceipt($customerId)
   {
      try {
         $treatment = Treatment::where('PersonId', $customerId)->whereNull('ClosedAt')->select('TreatmentId')->first();

         if (empty($treatment)) {
            return [];
         }
         $treatmentId = $treatment->TreatmentId;

         $data = TreatmentFinancial::where('TreatmentId', $treatmentId)->where('CustomerId', $customerId)->select('PaymentAmount', 'TotalAmount')->first();
         return $data;

      } catch (\Exception $e) {
         Log::error("checkEditReceipt errors", [$e->getMessage()]);
         throw $e;
      }
   }

   public function checkAllocateReceipt($receiptId)
   {
      try {

         $orderDetailIds = OrderDetailFinancialTrans::where('ObjectType', 'Receipt')
            ->where('ObjectId', $receiptId)
            ->where('ObjectDetailType', 'ReceiptService')
            ->pluck('OrderDetailId')
            ->unique()
            ->toArray();

         if (empty($orderDetailIds)) {
            return [];
         }

         $result = OrderDetailFinancialTrans::whereIn('OrderDetailId', $orderDetailIds)
            ->selectRaw('
               OrderDetailId,
               SUM(ReceiptAmount)     as ReceiptAmount,
               SUM(TransferAmount)    as TransferAmount,
               SUM(ExpenditureAmount) as ExpenditureAmount
            ')
            ->groupBy('OrderDetailId')
            ->get()
            ->keyBy('OrderDetailId')
            ->toArray();

         return $result;

      } catch (\Exception $e) {
         Log::error("checkAllocateReceipt errors", [
            'ReceiptId' => $receiptId,
            'Error'     => $e->getMessage()
         ]);
         throw $e;
      }
   }

   public function deleteOldReceiptData($receiptId)
   {
      try {

         ReceiptDetail::where('ReceiptId', $receiptId)->delete();
         ReceiptService::where('ReceiptId', $receiptId)->delete();
         DepositTransaction::where('ObjectType', 'Receipt')->where('ObjectId', $receiptId)->where('Type', 1)->delete();
         return true;
      } catch (\Exception $e) {
         Log::error("deleteOldReceiptData errors", [$e->getMessage()]);
         throw $e;
      }
   }

   public function rollbackPaymentAllocate($customerId, $receiptId)
   {
      $receipt = $this->getDetail($receiptId);
      if (!$receipt) {
         return false;
      }
      $rollbackAt = $receipt->AddedAt;
      if ($rollbackAt <= 0) {
         return true;
      }

      return DB::transaction(function () use ($customerId, $rollbackAt) {
         $listPayment = DB::table('Payment')
               ->select('PaymentId', 'TotalAmount')
               ->where('AddedAt', '>=', $rollbackAt)
               ->where('CustomerId', $customerId)
               ->whereNull('LockedAt')
               ->get();

         if ($listPayment->isEmpty()) {
               return true;
         }

         $skipPayment   = [];
         $removePayment = [];

         foreach ($listPayment as $lp) {

               $promotionCheck = DB::table('PaymentDetail as pd')
                  ->leftJoin('PromotionOrderDetail as pod', 'pd.OrderDetailId', '=', 'pod.OrderDetailId')
                  ->where('pd.PaymentId', $lp->PaymentId)
                  ->where('pod.PromotionType', 'PR005')
                  ->first();

               if ($promotionCheck) {
                  $skipPayment[] = $lp;
                  continue;
               }

               if ($lp->TotalAmount < 0) {

                  $paymentDetail = DB::table('PaymentDetail')
                     ->where('PaymentId', $lp->PaymentId)
                     ->first();

                  if (!$paymentDetail) {
                     $removePayment[] = $lp;
                     continue;
                  }

                  $existsBeforeRollback = DB::table('PaymentDetail as pd')
                     ->join('Payment as p', 'pd.PaymentId', '=', 'p.PaymentId')
                     ->where('pd.OrderDetailId', $paymentDetail->OrderDetailId)
                     ->where('pd.ProcedureProgressId', 'like', '%' . $paymentDetail->ProcedureProgressId . '%')
                     ->where('p.AddedAt', '<', $rollbackAt)
                     ->exists();

                  if (!$existsBeforeRollback) {
                     $removePayment[] = $lp;
                  } else {
                     $skipPayment[] = $lp;
                  }

               } else {
                  $removePayment[] = $lp;
               }
         }

         if (empty($removePayment)) {
               return true;
         }

         Log::info('RollbackPaymentAllocate', [
               'CustomerId'    => $customerId,
               'RollbackAt'    => $rollbackAt,
               'removePayment' => $removePayment,
               'skipPayment'   => $skipPayment,
         ]);

         $paymentIds = collect($removePayment)->pluck('PaymentId')->toArray();

         DB::table('PaymentDeposit')
               ->whereIn('PaymentId', $paymentIds)
               ->delete();

         $listTreatmentProgress = DB::table('PaymentDetail as pd')
               ->join('OrderDetail as od', 'pd.OrderDetailId', '=', 'od.OrderDetailId')
               ->select('od.TreatmentMedicalProcedureId', 'pd.OrderDetailId', 'pd.ProcedureProgressId')
               ->whereIn('pd.PaymentId', $paymentIds)
               ->whereNotNull('pd.ProcedureProgressId')
               ->get();

         foreach ($listTreatmentProgress as $v) {

               TreatmentProcedureProgress::where('TreatmentMedicalProcedureId', $v->TreatmentMedicalProcedureId)
                  ->whereIn('ProcedureProgressId', explode(',', $v->ProcedureProgressId))
                  ->update(['IsAllocated' => 0]);

               $from = min(explode(',', $v->ProcedureProgressId));
               $to   = max(explode(',', $v->ProcedureProgressId));

               DB::statement(
                  'CALL usp_AllocatedRevenueTracking_UpdateCoL(?,?,?,?,?)',
                  [$v->OrderDetailId, $from, $to, 1, 2267]
               );
         }

         $listPaymentDetailAll = DB::table('PaymentDetail')
               ->select('OrderDetailId', 'Amount', 'ProcedureProgressId')
               ->whereIn('PaymentId', $paymentIds)
               ->get();

         foreach ($listPaymentDetailAll as $pa) {
               if ($pa->ProcedureProgressId === null) {
                  DB::statement(
                     'CALL usp_AllocatedRevenueTracking_UpdateCoL(?,?,?,?,?)',
                     [$pa->OrderDetailId, null, null, 1, 2267]
                  );
               }
         }

         foreach ($listPaymentDetailAll as $ls) {

               if (!$ls->ProcedureProgressId) {
                  continue;
               }

               $ppIds = explode(',', $ls->ProcedureProgressId);

               $allocatedIds = DB::table('AllocatedRevenue')
                  ->where('OrderDetailId', $ls->OrderDetailId)
                  ->whereIn('ProcedureProgressId', $ppIds)
                  ->pluck('AllocatedRevenueId')
                  ->toArray();

               DB::table('AllocatedRevenue')
                  ->where('OrderDetailId', $ls->OrderDetailId)
                  ->whereIn('ProcedureProgressId', $ppIds)
                  ->update([
                     'BranchId'      => null,
                     'AllocatedDate'=> null
                  ]);

               if (!empty($allocatedIds)) {
                  DB::table('AllocatedRevenueCoL')
                     ->whereIn('AllocatedRevenueId', $allocatedIds)
                     ->update([
                           'ReceiverId'   => null,
                           'AllocatedDate'=> null
                     ]);
               }
         }

         DB::table('PaymentDetail')->whereIn('PaymentId', $paymentIds)->delete();
         DB::table('Payment')->whereIn('PaymentId', $paymentIds)->delete();
         DB::table('PaymentAllocatedRevenueTracking')->whereIn('PaymentId', $paymentIds)->delete();

         $depositTransactions = DepositTransaction::select('Amount', 'BalanceType', 'Type')
               ->whereIn('RefObjectId', $paymentIds)
               ->whereIn('Type', [2, 3])
               ->where('ObjectType', 'AllocatedRevenueTracking')
               ->get();

         $PaidAmount = 0;
         $PaidDVAmount = 0;

         foreach ($depositTransactions as $dt) {
               if ($dt->BalanceType === 'Current') {
                  $PaidAmount += ($dt->Type == 2 ? $dt->Amount : -$dt->Amount);
               }
               if ($dt->BalanceType === 'Debit') {
                  $PaidDVAmount += ($dt->Type == 2 ? $dt->Amount : -$dt->Amount);
               }
         }

         $KimRevenue = $listPaymentDetailAll->sum('Amount');
         $currentPaid = $KimRevenue - $PaidDVAmount;

         Deposit::where('CustomerId', $customerId)
               ->update([
                  'CurrentBalance'        => DB::raw("CurrentBalance + {$currentPaid}"),
                  'PaidAmount'            => DB::raw("PaidAmount - {$currentPaid}"),
                  'CurrentDebitVoucher'   => DB::raw("CurrentDebitVoucher + {$PaidDVAmount}"),
                  'PaidDebitVoucher'      => DB::raw("PaidDebitVoucher - {$PaidDVAmount}"),
               ]);

         DepositTransaction::whereIn('RefObjectId', $paymentIds)
               ->whereIn('Type', [2, 3])
               ->where('ObjectType', 'AllocatedRevenueTracking')
               ->delete();

         return true;
      });
   }

   public function updateReceiptService($receiptId, $staffId, $totalAmount, $customerId, $receiptDetail)
   {
      try {

         $infoReceipt = $this->getDetail($receiptId);
         if (!$infoReceipt) {
            return false;
         }
         Log::info('updateReceiptService receiptId', [$receiptId]);
         Log::info('updateReceiptService totalAmount', [$totalAmount]);
         Log::info('updateReceiptService receiptDetail', [$receiptDetail]);
         $receiptType = $infoReceipt->ReceiptType;
         $treatmentId = $infoReceipt->TreatmentId;

         switch ($receiptType) {
            case 1: // Sửa phiếu thu tiền điều trị nha khoa

               $now = Carbon::now()->toDateTimeString();

               $list = OrderDetailFinancialTrans::where('OrderDetailFinancialTrans.ObjectType', 'Receipt')
                  ->join('pos.OrderDetail as od', 'OrderDetailFinancialTrans.OrderDetailId', '=', 'od.OrderDetailId')
                  ->where('OrderDetailFinancialTrans.ObjectId', $receiptId)
                  ->where('OrderDetailFinancialTrans.ObjectDetailType', 'ReceiptService')
                  ->select(
                        'OrderDetailFinancialTrans.TreatmentId',
                        'OrderDetailFinancialTrans.CustomerId',
                        'OrderDetailFinancialTrans.OrderDetailId',
                        'OrderDetailFinancialTrans.OrderChangingId',
                        'OrderDetailFinancialTrans.ServiceId',
                        'od.Amount as OrderDetailAmount',
                        'OrderDetailFinancialTrans.ObjectDetailId',
                        'OrderDetailFinancialTrans.ReceiptAmount'
                  )
                  ->get();

               if ($list->isEmpty()) {
                  break;
               }

               $orderDetails = $list->toArray();

               /**
                * 1. Reverse receipt cũ
               */
               $reverseData = [];

               foreach ($orderDetails as $item) {
                  $reverseData[] = [
                        'TreatmentId'       => $treatmentId,
                        'CustomerId'        => $customerId,
                        'OrderDetailId'     => $item['OrderDetailId'],
                        'OrderChangingId'   => $item['OrderChangingId'],
                        'ServiceId'         => $item['ServiceId'],
                        'ObjectType'        => 'Receipt',
                        'ObjectId'          => $receiptId,
                        'ObjectDetailType'  => 'ReceiptService',
                        'ObjectDetailId'    => $item['ObjectDetailId'],
                        'ReceiptAmount'     => -1 * (int)$item['ReceiptAmount'],
                        'Note'              => 'Sửa phiếu thu => ghi âm số tiền phiếu thu gốc',
                        'CreatedStaffId'    => $staffId,
                        'CreatedDate'       => $now,
                  ];
               }

               OrderDetailFinancialTrans::insert($reverseData);

               /**
                * 2. Tính lại financial map
               */
               $financialMap = OrderDetailFinancialTrans::whereIn(
                        'OrderDetailId',
                        array_column($orderDetails, 'OrderDetailId')
                  )
                  ->groupBy('OrderDetailId')
                  ->selectRaw('
                        OrderDetailId,
                        COALESCE(SUM(InvoiceAmount),0)     as InvoiceAmount,
                        COALESCE(SUM(ReceiptAmount),0)     as ReceiptAmount,
                        COALESCE(SUM(TransferAmount),0)    as TransferAmount,
                        COALESCE(SUM(ExpenditureAmount),0) as ExpenditureAmount
                  ')
                  ->get()
                  ->keyBy('OrderDetailId');

               /**
                * 3. Build remainDetails
               */
               $remainDetails = [];

               foreach ($orderDetails as $detail) {

                  if (!isset($financialMap[$detail['OrderDetailId']])) {
                        continue;
                  }

                  $financial = $financialMap[$detail['OrderDetailId']];

                  $usedAmount =
                        (int)$financial->ReceiptAmount +
                        (int)$financial->TransferAmount -
                        (int)$financial->ExpenditureAmount;

                  $invoiceRemain = max(
                        (int)$financial->InvoiceAmount - $usedAmount,
                        0
                  );

                  $serviceRemain = max(
                        (int)$detail['OrderDetailAmount'] - $usedAmount,
                        0
                  );

                  if ($serviceRemain <= 0) {
                        continue;
                  }

                  $detail['InvoiceRemain'] = min($invoiceRemain, $serviceRemain);
                  $detail['ServiceRemain'] = $serviceRemain;

                  $remainDetails[] = $detail;
               }

               if (empty($remainDetails)) {
                  break;
               }

               /**
                * 4. PHÂN BỔ TIỀN
               */
               $remainAmount = (int)$totalAmount;
               $allocatedMap = [];

               // 4.1 Ưu tiên phân bổ theo InvoiceRemain
               foreach ($remainDetails as $d) {

                  if ($remainAmount <= 0) break;

                  $allocate = min($d['InvoiceRemain'], $remainAmount);

                  if ($allocate <= 0) continue;

                  $allocatedMap[$d['OrderDetailId']] = $allocate;
                  $remainAmount -= $allocate;
               }

               // 4.2 Phân bổ tiếp để LẤP ĐẦY ServiceRemain cho TẤT CẢ dịch vụ
               if ($remainAmount > 0) {

                  foreach ($remainDetails as $d) {

                        if ($remainAmount <= 0) break;

                        $used = $allocatedMap[$d['OrderDetailId']] ?? 0;
                        $need = $d['ServiceRemain'] - $used;

                        if ($need <= 0) continue;

                        $add = min($need, $remainAmount);

                        $allocatedMap[$d['OrderDetailId']] =
                           ($allocatedMap[$d['OrderDetailId']] ?? 0) + $add;

                        $remainAmount -= $add;
                  }
               }

               // 4.3 Chỉ khi còn dư → Kiểm tra có dịch vụ nào chưa thu đủ tiền thì tiếp tục phân bổ, nếu không có thì phân bổ vào dịch vụ tạm OrderDetail có IsOverPaymentAmount = 1
               if ($remainAmount > 0) {

                  /**
                   * 4.4. Dịch vụ mới thêm (chưa có trong receipt cũ)
                  */
                  $listOrderDetailFinancial = OrderDetailFinancial::whereNotIn('OrderDetailId',array_column($orderDetails, 'OrderDetailId'))->where('TreatmentId', $treatmentId)->get();
                  
                  if($listOrderDetailFinancial->isNotEmpty()){
                     // Cũng ưu tiên phân bổ vào các dịch vụ này nếu có InvoiceAmount > 0 như ở trên
                     foreach ($listOrderDetailFinancial as $odf) {

                        if ($remainAmount <= 0) break;

                        $invoiceRemain = max(
                              (int)$odf->InvoiceAmount -
                              (
                                 (int)$odf->ReceiptAmount +
                                 (int)$odf->TransferAmount -
                                 (int)$odf->ExpenditureAmount
                              ),
                              0
                        );

                        if ($invoiceRemain <= 0) continue;

                        $allocate = min($invoiceRemain, $remainAmount);

                        $allocatedMap[$odf->OrderDetailId] =
                              ($allocatedMap[$odf->OrderDetailId] ?? 0) + $allocate;

                        $remainAmount -= $allocate;
                     }
                  }

                  /**
                   * 4.5. Nếu vẫn còn dư → chia đều cho các dịch vụ còn thiếu
                  */
                  if ($remainAmount > 0 && $listOrderDetailFinancial->isNotEmpty()) {

                     $eligible = [];

                     foreach ($listOrderDetailFinancial as $odf) {
                        if ((int)$odf->InvoiceAmount > 0) {
                              $eligible[] = $odf->OrderDetailId;
                        }
                     }

                     if (!empty($eligible)) {

                        $count = count($eligible);
                        $each = floor($remainAmount / $count);

                        foreach ($eligible as $orderDetailId) {

                              if ($remainAmount <= 0) break;

                              $add = min($each, $remainAmount);

                              $allocatedMap[$orderDetailId] =
                                 ($allocatedMap[$orderDetailId] ?? 0) + $add;

                              $remainAmount -= $add;
                        }
                     }
                  }

                  /**
                   * 4.6. Phân bổ vào dịch vụ tạm
                  */
                  if ($remainAmount > 0) {

                     $orderDetail = OrderDetail::where('TreatmentId', $treatmentId)
                        ->where('IsOverPaymentAmount', 1)
                        ->first();

                     if (!$orderDetail) {

                        $temporaryId = OrderDetail::insertGetId([
                              'OrderId'               => 0,
                              'TreatmentId'           => $treatmentId,
                              'ServiceId'             => 0,
                              'ServiceName'           => 'Tạm ứng',
                              'Status'                => -1,
                              'Quantity'              => 1,
                              'Amount'                => 0,
                              'TaxAmount'             => 0,
                              'TaxPercent'            => 0,
                              'DiscountPercent'       => 0,
                              'DiscountAmount'        => 0,
                              'ProcessState'          => 0,
                              'AmountNotAllocated'    => 0,
                              'IsPayInstallments'     => 0,
                              'IsOverPaymentAmount'   => 1
                        ]);

                        $orderDetailId = $temporaryId;

                     } else {
                        $orderDetailId = $orderDetail->OrderDetailId;
                     }

                     $allocatedMap[$orderDetailId] =
                        ($allocatedMap[$orderDetailId] ?? 0) + $remainAmount;

                     $remainAmount = 0;
                  }
               }

               /**
                * 5. Insert ReceiptService + FinancialTrans
               */
               $remainMap = collect($remainDetails)->keyBy('OrderDetailId');

               foreach ($allocatedMap as $orderDetailId => $amount) {

                  if ($amount <= 0 || !isset($remainMap[$orderDetailId])) {
                        continue;
                  }

                  $d = $remainMap[$orderDetailId];

                  $receiptServiceId = ReceiptService::insertGetId([
                        'ReceiptId'      => $receiptId,
                        'TreatmentId'    => $treatmentId,
                        'OrderDetailId'  => $d['OrderDetailId'],
                        'ServiceId'      => $d['ServiceId'],
                        'Amount'         => $amount,
                        'CreatedStaffId' => $staffId,
                        'UpdatedStaffId' => $staffId
                  ]);

                  OrderDetailFinancialTrans::insert([
                        'TreatmentId'       => $treatmentId,
                        'CustomerId'        => $customerId,
                        'OrderDetailId'     => $d['OrderDetailId'],
                        'OrderChangingId'   => $d['OrderChangingId'],
                        'ServiceId'         => $d['ServiceId'],
                        'ObjectType'        => 'Receipt',
                        'ObjectId'          => $receiptId,
                        'ObjectDetailType'  => 'ReceiptService',
                        'ObjectDetailId'    => $receiptServiceId,
                        'ReceiptAmount'     => $amount,
                        'Note'              => 'DEBT_COLLECTION_VOUCHER',
                        'CreatedStaffId'    => $staffId,
                        'CreatedDate'       => $now
                  ]);
               }

               /**
                * 6. Update status OrderDetail
               */
               $orderDetailIds = array_column($orderDetails, 'OrderDetailId');
               $this->updateStatusOrderDetail($orderDetailIds, $receiptId);

               break;

            case 2: // Sửa phiếu công nợ

               $list = OrderDetailFinancialTrans::where('ObjectType', 'Receipt')
                  ->where('ObjectId', $receiptId)
                  ->where('ObjectDetailType', 'ReceiptService')
                  ->get();

               if ($list->isEmpty()) {
                  return;
               }

               $insertData = [];

               $now = Carbon::now()->toDateTimeString();

               foreach ($list as $item) {
                  $insertData[] = [
                     'TreatmentId'        => $treatmentId,
                     'CustomerId'         => $customerId,
                     'OrderDetailId'      => $item->OrderDetailId,
                     'OrderChangingId'    => $item->OrderChangingId,
                     'ServiceId'          => $item->ServiceId,
                     'ObjectType'         => 'Receipt',
                     'ObjectId'           => $receiptId,
                     'ObjectDetailType'   => 'ReceiptService',
                     'ObjectDetailId'     => $item->ObjectDetailId,
                     'ReceiptAmount'      => -1 * (float) $item->ReceiptAmount,
                     'InsuranceAmount'    => -1 * (float) $item->InsuranceAmount,
                     'Note'               => 'Sửa phiếu công nợ => ghi âm số tiền phiếu gốc',
                     'CreatedStaffId'     => $staffId,
                     'CreatedDate'        => $now,
                  ];
               }

               OrderDetailFinancialTrans::insert($insertData);


               foreach ($receiptDetail as $detail) {
                  if (empty($detail['OrderDetailId']) || $detail['OrderDetailId'] <= 0) {
                     continue;
                  }
                  $financial = OrderDetailFinancial::where('OrderDetailId',$detail['OrderDetailId'])->first();

                  if (!$financial) {
                     continue;
                  }
                  $amount = $detail['Amount'];

                  $receiptServiceId = ReceiptService::insertGetId([
                     'ReceiptId'       => $receiptId,
                     'TreatmentId'     => $treatmentId,
                     'OrderDetailId'   => $detail['OrderDetailId'],
                     'ServiceId'       => $detail['ServiceId'],
                     'Amount'          => $amount,
                     'CreatedStaffId'  => $staffId,
                     'UpdatedStaffId'  => $staffId
                  ]);

                  if ($receiptServiceId) {
                     OrderDetailFinancialTrans::insert([
                        'TreatmentId'        => $treatmentId,
                        'CustomerId'         => $customerId,
                        'OrderDetailId'      => $detail['OrderDetailId'],
                        'OrderChangingId'    => $detail['OrderChangingId'],
                        'ServiceId'          => $detail['ServiceId'],
                        'ObjectType'         => 'Receipt',
                        'ObjectId'           => $receiptId,
                        'ObjectDetailType'   => 'ReceiptService',
                        'ObjectDetailId'     => $receiptServiceId,
                        'ReceiptAmount'      => $amount,
                        'InsuranceAmount'    => $amount,
                        'Note'               => 'Sửa phiếu công nợ => phân bổ tiền phiếu công nợ mới',
                        'CreatedStaffId'     => $staffId,
                        'CreatedDate'        => Carbon::now()->toDateTimeString()
                     ]);
                  }
               }

               break;
            default:
               return true;
         }
         return true;
      } catch (\Exception $e) {
         Log::error("updateReceiptService errors", [$e->getMessage()]);
         throw $e;
      }
   }

   public function createPayInstallments($data)
   {
      try {

         if (empty($data['OrderDetailIds'])) {
            Log::info("createPayInstallments errors", ['message' => 'OrderDetailIds NULL']);
            return false;
         }
         DB::beginTransaction();
         $staffId = Auth::user()['StaffId'] ?? 0;
         $query = OrderDetail::whereIn('OrderDetailId', $data['OrderDetailIds'])
               ->join('ServiceInstallmentConfig as sic', 'OrderDetail.ServiceId', '=', 'sic.ServiceId')
               ->where('sic.StartDate', '<=', Carbon::now()->toDateString())
               ->where('sic.EndDate', '>=', Carbon::now()->toDateString())
               ->select('sic.*', 'OrderDetail.OrderDetailId', 'OrderDetail.Amount as OrderDetailAmount');
         $infoOrderDetails = $query->get()->toArray();

         if (empty($infoOrderDetails)) {
            Log::info("createPayInstallments errors", ['message' => 'Không tìm thấy dịch vụ nào có cấu hình trả góp hoặc dịch vụ đã hết thời gian cho trả góp']);
            DB::rollBack();
            return false;
         }

         OrderDetail::whereIn('OrderDetailId', $data['OrderDetailIds'])->update(['IsPayInstallments' => $data['Status']]);

         DB::commit();
         return true;

      } catch (\Exception $e) {
         Log::error("createPayInstallments errors", [$e->getMessage()]);
         DB::rollBack();
         return false;
      }
   }

   public function paymentMPOSQR($amount = 0, $receiptPendingId = 0, $receiptPendingCode = '', $branchId = 0, $description = '')
   {
      //Validation input
      if (!$amount || empty($amount)) {
         return false;
      }
      if (!$receiptPendingId || empty($receiptPendingId)) {
         return false;
      }
      if (!$receiptPendingCode || empty($receiptPendingCode)) {
         return false;
      }
      if (!$branchId || empty($branchId)) {
         return false;
      }

      //Get Pay Provider
      $payProvider = PayProvider::where('ProviderCode', 'MPOS')->where('State', 1)->first();

      if (!$payProvider || empty($payProvider)) {
         return false;
      }

      //Get Pay Pos Terminal
      $posTerminals = $payProvider->posTerminals ?? collect([]);

      if (!$posTerminals || empty($posTerminals)) {
         false;
      }

      $posTerminal = $posTerminals->first();

      if (!$posTerminal || empty($posTerminal)) {
         return false;
      }

      $data = [
         'type' => 'POS-QR',
         'providerId' => $posTerminal->PayProviderId ?? 0,
         'receiptPendingId' => $receiptPendingId,
         'receiptPendingCode' => $receiptPendingCode,
         'branchId' => $branchId,
         'posTerminalId' => $posTerminal->PosTerminalId ?? 0,
         'posTerminalCode' => $posTerminal->PosTerminalCode ?? '',
         'description' => $description,
         'amount' => $amount
      ];
      Log::info("Send payment MPOS QR", $data);
      try {

         //Remote payment MPOS
         $header = ['Authorization' => 'Bearer ' . JWT_APP_TOKEN];

         $remote = Factory::getRemote();
         $remote->request('status')
            ->from(API_PAYMENT_MPOS_EDC)
            ->where($data)
            ->execute(true, $header);
         $response = $remote->loadVar(false);
         $statusCode = $remote->getResponseCode();

         if ($statusCode == 200 && $response == 'success') {
            return true;
         }
      } catch (\Exception $ex) {
         Log::error("Payment MPOS errors", [$ex->getMessage()]);
         return false;
      }

      return false;
   }

   public function paymentMPOSCard($amount = 0, $receiptPendingId = 0, $receiptPendingCode = '', $branchId = 0, $description = '')
   {
      //Validation input
      if (!$amount || empty($amount)) {
         return false;
      }
      if (!$receiptPendingId || empty($receiptPendingId)) {
         return false;
      }
      if (!$receiptPendingCode || empty($receiptPendingCode)) {
         return false;
      }
      if (!$branchId || empty($branchId)) {
         return false;
      }

      //Get Pay Provider
      $payProvider = PayProvider::where('ProviderCode', 'MPOS')->where('State', 1)->first();

      if (!$payProvider || empty($payProvider)) {
         return false;
      }

      //Get Pay Pos Terminal
      $posTerminals = $payProvider->posTerminals ?? collect([]);

      if (!$posTerminals || empty($posTerminals)) {
         false;
      }

      $posTerminal = $posTerminals->first();

      if (!$posTerminal || empty($posTerminal)) {
         return false;
      }

      $data = [
         'type' => 'POS-CARD',
         'providerId' => $posTerminal->PayProviderId ?? 0,
         'receiptPendingId' => $receiptPendingId,
         'receiptPendingCode' => $receiptPendingCode,
         'branchId' => $branchId,
         'posTerminalId' => $posTerminal->PosTerminalId ?? 0,
         'posTerminalCode' => $posTerminal->PosTerminalCode ?? '',
         'description' => $description,
         'amount' => $amount
      ];
      Log::info("Send payment MPOS Card", $data);
      try {

         //Remote payment MPOS
         $header = ['Authorization' => 'Bearer ' . JWT_APP_TOKEN];

         $remote = Factory::getRemote();
         $remote->request('status')
            ->from(API_PAYMENT_MPOS_EDC)
            ->where($data)
            ->execute(true, $header);
         $response = $remote->loadVar(false);
         $statusCode = $remote->getResponseCode();

         if ($statusCode == 200 && $response == 'success') {
            return true;
         }
      } catch (\Exception $ex) {
         Log::error("Payment MPOS errors", [$ex->getMessage()]);
         return false;
      }

      return false;
   }

   public function webhookFinishReceiptPending($receiptPendingId = 0)
   {
      if (!$receiptPendingId || empty($receiptPendingId)) {
         return false;
      }

      $receiptPending = ReceiptPending::where('ReceiptPendingId', $receiptPendingId)
         ->where('State', 10)
         ->first();

      if (!$receiptPending || empty($receiptPending)) {
         Log::error("Finish receipt pending fail: receipt empty - $receiptPendingId");
         return false;
      }

      //Send progress create receipt
      $receiptId = $this->progressCreateReceipt(
         $receiptPending->Receipt ?? [],
         $receiptPending->ReceiptDetail ?? [],
         $receiptPending->ReceiptType ?? 0,
         $receiptPending->TotalAmount ?? 0.0,
         $receiptPending->BranchId ?? 0,
         $receiptPending->CustomerId ?? 0,
         $receiptPending->TreatmentId ?? 0,
         $receiptPending->DepositId ?? 0,
         $receiptPending->CreatedBy ?? 0,
         $receiptPending->AppointmentId ?? 0,
         $receiptPending->OrderDetail ?? []
      );

      if (!$receiptId || empty($receiptId)) {
         return false;
      }
      //Update ReceiptId in ReceiptPending
      $updateReceiptPending = ReceiptPending::where('ReceiptPendingId', $receiptPendingId)
         ->update([
            'State' => 99, //finished
            'ReceiptId' => $receiptId,
            'UpdatedDate' => Carbon::now()->toDateTimeString(),
            'UpdatedBy' => Auth::user()['StaffId'] ?? 0
         ]);
      
      //Prepare data send noti
      $senderId = Auth::user()['UserId'] ?? 0;
      $customer = Customer::find($receiptPending->CustomerId ?? 0);
      $amountFormat = number_format($receiptPending->TotalAmount ?? 0,0,",",".");

      //Receive user id
      $receiveIds = [];
      //Get UserId in Branch
      $userIds = DB::table('in.Org')
         ->join('in.OrgWorkProfile', 'OrgWorkProfile.OrgId', '=', 'Org.OrgId')
         ->where('Org.BranchId', '=', $receiptPending->BranchId ?? 0)
         ->where('OrgWorkProfile.Status', '=', 1)
         ->where('OrgWorkProfile.FromDate', '<=', date('Y-m-d 23:59:59'))
         ->where('OrgWorkProfile.ToDate', '>=', date('Y-m-d 00:00:00'))
         ->select('OrgWorkProfile.UserId')
         ->get();
      if ($userIds && !empty($userIds)) {
         $receiveIds = $userIds->pluck('UserId')->toArray();
      }
      //User created
      $createdByStaff = Staff::find($receiptPending->CreatedBy ?? 0);
      if ($createdByStaff && !empty($createdByStaff)) {
         $receiveIds[] = $createdByStaff->UserId ?? 0;
      }
      $receiveIds = array_values(array_unique($receiveIds));
      
      if (!$customer || empty($customer)) {
         return false;
      }
      //Send notification
      try {
         $dataNoti = [];
         $dataNoti['notification'] = [
            'title' => "Khách hàng ".($customer->FullName ?? 'Chưa xác định')." đã thanh toán",
            'message' => "Khách hàng ".($customer->FullName ?? 'Chưa xác định')." - ".($customer->CustomerCode)." đã thanh toán thành công số tiền ".$amountFormat."đ.",
            'exprire_date' => date('Y-m-d 23:59:59'),
            'message_type' => 'normal',
            'link' => '',
            'important' => 0,
            'hasStaff' => true,
            'user_list' => implode(",", $receiveIds),
            'sender' => $senderId,
            'type' => 'RequestAdvice',
            'redirect_link' => "/pos/Customer/".($receiptPending->CustomerId ?? 0)."/Profile/CustomerFinancial?ReceiptId=".$receiptId,
            'noti_type' => 'Success'
         ];
         //Remote Noti
         $header = ['Authorization' => 'Bearer ' . JWT_APP_TOKEN];

         $remote = Factory::getRemote();
         $remote->request('module')
            ->from(API_SEND_NOTIFICATION)
            ->where($dataNoti)
            ->execute(true, $header);
         $response = $remote->loadVar(false);
         $statusCode = $remote->getResponseCode();

         if ($statusCode == 200 && $response == 'success') {
            return $receiptId;
         }
      } catch (\Exception $ex) {
         Log::error("Send notification fail", [$ex->getMessage()]);
      }
      
      return $receiptId;

   }

   public function webhookCancelReceiptPending($receiptPendingId = 0)
   {
      if (!$receiptPendingId || empty($receiptPendingId)) {
         return false;
      }

      $receiptPending = ReceiptPending::where('ReceiptPendingId', $receiptPendingId)
         ->where('State', 10)
         ->first();

      if (!$receiptPending || empty($receiptPending)) {
         Log::error("Cancel receipt pending fail: receipt empty - $receiptPendingId");
         return false;
      }
      //Update Status in ReceiptPending
      $updateReceiptPending = ReceiptPending::where('ReceiptPendingId', $receiptPendingId)
         ->update([
            'State' => 0, //cancel,
            'UpdatedDate' => Carbon::now()->toDateTimeString(),
            'UpdatedBy' => Auth::user()['StaffId'] ?? 0
         ]);

      return $updateReceiptPending;
   }

   public function cancelReceiptPending($receiptPendingId = 0)
   {
      if (!$receiptPendingId || empty($receiptPendingId)) {
         return false;
      }

      $receiptPending = ReceiptPending::where('ReceiptPendingId', $receiptPendingId)
         ->where('State', 10)
         ->first();

      if (!$receiptPending || empty($receiptPending)) {
         Log::error("Cancel receipt pending fail: receipt empty - $receiptPendingId");
         return false;
      }
      //Update Status in ReceiptPending
      $updateReceiptPending = ReceiptPending::where('ReceiptPendingId', $receiptPendingId)
         ->update([
            'State' => 0, //cancel,
            'UpdatedDate' => Carbon::now()->toDateTimeString(),
            'UpdatedBy' => Auth::user()['StaffId'] ?? 0
         ]);
      
      //Send request cancel to MPOS
      try {
         $data = [
            'receiptPendingId' => $receiptPendingId
         ];

         $header = ['Authorization' => 'Bearer ' . JWT_APP_TOKEN];

         $remote = Factory::getRemote();
         $remote->request('status')
            ->from(API_PAYMENT_MPOS_EDC_CANCEL)
            ->where($data)
            ->execute(true, $header);
         $response = $remote->loadVar(false);
         $statusCode = $remote->getResponseCode();

         if ($statusCode == 200 && $response == 'success') {
            return true;
         }
      } catch (\Exception $ex) {
         Log::error("Payment MPOS errors", [$ex->getMessage()]);
         return false;
      }
      return $updateReceiptPending;
   }

   public function checkReceiptPending($receiptPendingId = 0)
   {
      if (!$receiptPendingId || empty($receiptPendingId)) {
         return false;
      }

      $receiptPending = ReceiptPending::where('ReceiptPendingId', $receiptPendingId)
         ->where('State', 10)
         ->first();

      if (!$receiptPending || empty($receiptPending)) {
         Log::error("Check receipt pending fail: receipt empty - $receiptPendingId");
         return false;
      }
      //Send request check to MPOS
      try {
         $data = [
            'receiptPendingId' => $receiptPendingId
         ];

         $header = ['Authorization' => 'Bearer ' . JWT_APP_TOKEN];

         $remote = Factory::getRemote();
         $remote->request('status')
            ->from(API_PAYMENT_MPOS_EDC_CHECK)
            ->where($data)
            ->execute(true, $header);
         $response = $remote->loadVar(false);
         $statusCode = $remote->getResponseCode();

         if ($statusCode == 200 && $response == 'success') {
            return true;
         }
      } catch (\Exception $ex) {
         Log::error("Payment MPOS errors", [$ex->getMessage()]);
         return false;
      }
      return false;
   }

   public function findReceiptPending($receiptPendingId = 0, $updateDate = null)
   {
      $query = ReceiptPending::where('ReceiptPendingId', $receiptPendingId);

      if ($updateDate && !empty($updateDate)) {
         $query->where('UpdatedDate', $updateDate);
      }
      
      return $query->first();
   }

   public function listCustomerInstallment($data)
   {
      $branchId = $data['BranchId'] ?? 0;
      $keyword = $data['Keyword'] ?? '';
      $orderInstallmentPlanStatus = $data['OrderInstallmentPlanStatus'] ?? 0;
      $limit = $data['limit'] ?? 20;
      $lmstart = $data['lmstart'] ?? 0;

      $query = OrderInstallmentPlan::join('OrderDetail as od', 'od.OrderDetailId', '=', 'OrderInstallmentPlan.OrderDetailId')
         ->join('Service as s', 's.ServiceId', '=', 'od.ServiceId')
         ->join('OrderInstallmentPlanStatus as oi', 'oi.OrderInstallmentPlanId', '=', 'OrderInstallmentPlan.OrderInstallmentPlanStatus')
         ->join('Treatment as t', 't.TreatmentId', '=', 'od.TreatmentId')
         ->join('Customer as c', 'c.CustomerId', '=', 't.PersonId')
         ->join('OrderChanging as oc', 'oc.OrderChangingId', '=', 'od.OrderChangingId')
         ->join('in.Branch as b', 'b.BranchId', '=', 'oc.BranchId')
         ->leftJoin('in.Staff as st', 'st.StaffId', '=', 'od.ConsultingStaffId')
         ->select(
            'od.OrderDetailId',
            'c.CustomerId',
            'c.FullName as CustomerName',
            'c.CustomerCode',
            'b.BranchCode',
            DB::raw("
               CASE
                     WHEN s.WarrantyType = 'I' THEN 'Implant'
                     WHEN s.WarrantyType = 'P' THEN 'Răng sứ'
                     WHEN s.WarrantyType = 'O' THEN 'Niềng răng'
                     ELSE 'Tổng quát'
               END as WarrantyTypeName
            "),
            'OrderInstallmentPlan.StartInstallmentDate',
            'od.Amount as OrderDetailAmount',
            'OrderInstallmentPlan.OutstandingAmount',
            'oi.OrderInstallmentPlanNameVi',
            'OrderInstallmentPlan.RemainingPeriods',
            'OrderInstallmentPlan.TotalPeriods',
            'od.ConsultingStaffId',
            'st.FullName as ConsultingStaffName',
            'st.StaffCode as ConsultingStaffCode',
            's.Name as ServiceName'
            
         );
         $query->when($branchId > 0, function ($q) use ($branchId) {
            $q->where('oc.BranchId', $branchId);
         });
         // Tìm kiếm theo mã khách hàng hoặc tên khách hàng
         $query->when($keyword, function ($q) use ($keyword) {
            $keyword = trim($keyword);
            $q->where(function ($sub) use ($keyword) {
               $sub->where('c.FullName', 'like', "%{$keyword}%")
                     ->orWhere('c.CustomerCode', 'like', "%{$keyword}%");
            });
         });
         if ($orderInstallmentPlanStatus && $orderInstallmentPlanStatus > 0) {
            $query->where('OrderInstallmentPlan.OrderInstallmentPlanStatus', $orderInstallmentPlanStatus);
         } else {
            $query->whereBetween('OrderInstallmentPlan.OrderInstallmentPlanStatus', [10, 50]);
         }
         $query->addSelect(DB::raw("
            (
               SELECT DueDate
               FROM OrderInstallmentSchedule ois
               WHERE ois.OrderDetailId = OrderInstallmentPlan.OrderDetailId
               AND ois.OrderInstallmentScheduleStatus = 10
               ORDER BY ois.PeriodNumber
               LIMIT 1
            ) as NextDueDate
         "));

         $installments = $query->paginate($limit,['*'],'page', round((int) $lmstart/ (int) $limit) + 1);
         $orderDetailIds = $installments->pluck('OrderDetailId')->toArray();

         $schedules = OrderInstallmentSchedule::whereIn('OrderDetailId', $orderDetailIds)
            ->join('OrderInstallmentScheduleStatus as oiss', 'oiss.OrderInstallmentScheduleId', '=', 'OrderInstallmentSchedule.OrderInstallmentScheduleStatus')
            ->orderBy('OrderInstallmentSchedule.PeriodNumber')
            ->get([
               'OrderInstallmentSchedule.OrderInstallmentScheduleId',
               'OrderInstallmentSchedule.OrderDetailId',
               'OrderInstallmentSchedule.PeriodNumber',
               'OrderInstallmentSchedule.DueDate',
               'OrderInstallmentSchedule.DueAmount',
               'OrderInstallmentSchedule.PaidAmount',
               'OrderInstallmentSchedule.OrderInstallmentScheduleStatus',
               'oiss.OrderInstallmentScheduleNameVi'
            ])
            ->groupBy('OrderDetailId');

         $installments->getCollection()->transform(function ($item) use ($schedules) {

            $item->Schedules = isset($schedules[$item->OrderDetailId])
               ? $schedules[$item->OrderDetailId]->values()
               : [];

            return $item;
         });

      return $installments;
   }

   public function totalCustomerInstallment($data)
   {
      $branchId = $data['BranchId'] ?? 0;
      $keyword = $data['Keyword'] ?? '';
      $orderInstallmentPlanStatus = $data['OrderInstallmentPlanStatus'] ?? 0;

      $query = OrderInstallmentPlan::join('OrderDetail as od', 'od.OrderDetailId', '=', 'OrderInstallmentPlan.OrderDetailId')
         ->join('Service as s', 's.ServiceId', '=', 'od.ServiceId')
         ->join('Treatment as t', 't.TreatmentId', '=', 'od.TreatmentId')
         ->join('Customer as c', 'c.CustomerId', '=', 't.PersonId')
         ->join('OrderChanging as oc', 'oc.OrderChangingId', '=', 'od.OrderChangingId')

         ->when($branchId > 0, function ($q) use ($branchId) {
            $q->where('oc.BranchId', $branchId);
         })

         ->when($keyword, function ($q) use ($keyword) {
               $keyword = trim($keyword);
               $q->where(function ($sub) use ($keyword) {
                  $sub->where('c.FullName', 'like', "%{$keyword}%")
                     ->orWhere('c.CustomerCode', 'like', "%{$keyword}%");
               });
         })

         ->when($orderInstallmentPlanStatus > 0,
            function ($q) use ($orderInstallmentPlanStatus) {
               $q->where('OrderInstallmentPlan.OrderInstallmentPlanStatus', $orderInstallmentPlanStatus);
            },
            function ($q) {
               $q->whereBetween('OrderInstallmentPlan.OrderInstallmentPlanStatus', [10, 50]);
            }
         );

         $result = $query->selectRaw("
            COUNT(DISTINCT c.CustomerId) as TotalCustomer,
            COUNT(OrderInstallmentPlan.OrderDetailId) as TotalPlan,
            SUM(od.Amount) as TotalInstallmentAmount,
            SUM(OrderInstallmentPlan.OutstandingAmount) as TotalOutstandingAmount,
            SUM(CASE WHEN OrderInstallmentPlan.OrderInstallmentPlanStatus = 50 THEN 1 ELSE 0 END) as OverdueCount
         ")->first();

      return [
         'TotalCustomer' => (int) $result->TotalCustomer ?? 0,
         'TotalInstallmentAmount' => (float) $result->TotalInstallmentAmount ?? 0,
         'TotalOutstandingAmount' => (float) $result->TotalOutstandingAmount ?? 0,
         'OverdueCount' => (int) $result->OverdueCount ?? 0,
         'TotalPlan' => (int) $result->TotalPlan ?? 0,
      ];
   }

}