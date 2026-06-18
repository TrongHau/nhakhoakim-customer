<?php

namespace App\Repositories;

use App\Repositories\Abstracts\EloquentRepository;
use App\Customer;
use App\CustomerPhoneNumber;
use App\TimeKeeper;
use App\Appointment;
use App\DentalChairBooking;
use App\Treatment;
use App\OrderDetail;
use App\OrderChanging;
use App\OrderDetailTracking;
use App\AnatomyBodyPartItem;
use App\CustomerDoctor;
use App\CustomerFollowing;
use App\OrderMeasuringConsulting;
use App\Service;
use App\OrderMeasuringConsultingDoctor;
use App\LuckyDrawSpins;
use App\LuckyDrawCampaign;
use App\CustomerInvisalign;
use App\CustomerInvisalignTracking;
use App\Deposit;
use App\Doctor;
use App\OrderDetailFinancialTrans;
use App\OrderTransferAmount;
use App\OrderTransferAmountDetail;
use App\OrderDetailFinancial;
use App\Staff;
use App\CustomerInsurance;
use App\CustomerInsuranceImage;
use App\PartnerCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Composer\IO\NullIO;
use Illuminate\Support\Facades\Auth;
use App\Repositories\Traits\FilterLockedOrderDetailTrait;

class CustomerRepository extends EloquentRepository
{
    use FilterLockedOrderDetailTrait;

    protected function getModel()
    {
        return Customer::class;
    }

    public function getReceipts($customerId)
    {
        if(isset($customerId) && !empty($customerId) && is_numeric($customerId) && strlen($customerId) > 0){
            $query = DB::table('Customer as ct');
            $query->select(
                'ct.CustomerId as CustomerId',
                'dp.DepositId as DepositId',
                'rc.ReceiptId as ReceiptId',
                'rc.ReceiptCode as ReceiptCode',
                'rc.TotalAmount as TotalAmount'
            );
            $query->join('Deposit as dp','ct.CustomerId','=','dp.CustomerId');
            $query->join('Receipt as rc','dp.DepositId','=','rc.DepositId');
            $query->where('dp.State','=',1);
            $query->where('rc.State','=',1);
            $query->where('ct.CustomerId','=',$customerId);

            return $query->get();
        }
        return [];

    }

    public function getExpenditures($customerId)
    {
        if(isset($customerId) && !empty($customerId) && is_numeric($customerId) && strlen($customerId) > 0){
            $query = DB::table('Customer as ct');
            $query->select(
                'ct.CustomerId as CustomerId',
                'ep.ExpenditureId as Expenditure',
                'ep.ExpenditureCode as ExpenditureCode',
                'ep.Amount as Amount'
            );
            $query->join('Expenditure as ep','ct.CustomerId','=','ep.RefId');
            $query->where('ep.ExpenditureStatusId','=',5);
            $query->where('ep.ExpenditureCategoryId','=',44);
            $query->where('ct.CustomerId','=',$customerId);
            return $query->get();
        }
        return [];
    }

    public function listMoneyCollector($customerId) {
        if(isset($customerId) && !empty($customerId) && is_numeric($customerId) && strlen($customerId) > 0){
            $query = DB::table('pos.Deposit as d');
            $query->select('r.AddedBy as StaffId','s.FullName');
            $query->join('pos.Receipt as r','d.DepositId','=','r.DepositId');
            $query->join('in.Staff as s','r.AddedBy','=','s.StaffId');
            $query->where('d.State','=',1);
            $query->where('r.State','=',1);
            $query->where('d.CustomerId','=',$customerId)->groupBy('r.AddedBy');

            return $query->get();
        }
        return [];
    }

    public function changePhoneNumberFromZalo($oldPhoneNumber, $newPhoneNumber)
    {
        CustomerPhoneNumber::where('PhoneNumber', $oldPhoneNumber)->update(['PhoneNumber' => $newPhoneNumber]);
        return true;
    }

    public function listPhoneAreaCode()
    {
        $data = IOSCountry::where('State',1)->orderBy('Priority')->get();
        return $data;
    }
    
    public function getServiceDeleteHistory($customerId, $lmstart, $limit)
    {
        $data = DB::select(DB::raw("CALL pos.usp_GetInfoDeletedOrderDetail(".$customerId.",".$lmstart.",".$limit.")"));

        return $data;
    }
    
    public function getUrgentDetail($customerId)
    {
        if (!$customerId || empty($customerId)) {
            return null;
        }
        $query = $this->_model->newQuery();
        $query->select(
            'CustomerId',
            'UrgentContactName',
            'UrgentContactPhone',
            'UrgentContactAddress',
            'UrgentRelationshipId',
            'UrgentIsAdult'
        );
        $query->where('CustomerId', $customerId);
        $query->with('urgentRelationship');
        return $query->first();
    }

    public function updateUrgentContact($customerId, $data)
    {
        if (!$customerId || empty($customerId)) {
            return false;
        }
        $customer = $this->_model->find($customerId);
        if (!$customer || empty($customer)) {
            return false;
        }
        if (isset($data['UrgentContactName']) && !empty($data['UrgentContactName'])) {
            $customer->UrgentContactName = $data['UrgentContactName'];
        }
        if (isset($data['UrgentContactPhone']) && !empty($data['UrgentContactPhone'])) {
            $customer->UrgentContactPhone = $data['UrgentContactPhone'];
        }
        if (isset($data['UrgentContactAddress']) && !empty($data['UrgentContactAddress'])) {
            $customer->UrgentContactAddress = $data['UrgentContactAddress'];
        }
        if (isset($data['UrgentRelationshipId'])) {
            $customer->UrgentRelationshipId = $data['UrgentRelationshipId'];
        }
        if (isset($data['UrgentIsAdult'])) {
            $customer->UrgentIsAdult = $data['UrgentIsAdult'];
        }
        return $customer->save();
    }

    public function getCustomerDebtSummary($branchId)
    {
        $data = DB::select(DB::raw("CALL pos.usp_rptCustomerDebt_GetSummary(".$branchId.")"));
        return $data;
    }

    public function getCustomerDebtDetail($request)
    {
        $branchId = $request['BranchId'] ?? 0;
        $lmstart = $request['lmstart'] ?? 0;
        $limit = $request['limit'] ?? 20;
        
        $data = DB::select(DB::raw("CALL pos.usp_rptCustomerDebt_GetDetail(".$branchId.",'',".$lmstart.",".$limit.")"));

        return $data;
    }

    public function getReceiptAdjustTracking($request)
    {
        $fromDate = $request['FromDate'] ?? '';
        $toDate = $request['ToDate'] ?? '';
        $keyword = $request['Keyword'] ?? '';
        $lmstart = $request['lmstart'] ?? 0;
        $limit = $request['limit'] ?? 20;
        
        $query = DB::table('pos.ReceiptAdjustTracking as rat');
        $query->select(
            'rat.ReceiptAdjustTrackingId',
            'rat.ReceiptCode',
            'rat.Amount',
            'rat.OldAmount',
            'rat.CreatedBy',
            'rat.CreatedDate',
            'rat.CustomerId',
            'rat.TicketSupportId',
            'c.FullName',
            'c.CustomerCode',
            's.StaffCode',
            's.FullName as StaffName'
        );
        $query->leftJoin('pos.Customer as c','c.CustomerId','=','rat.CustomerId');
        $query->leftJoin('in.Staff as s','s.StaffId','=','rat.CreatedBy');
        if (!empty($fromDate)) {
            $query->where('rat.CreatedDate','>=',$fromDate.' 00:00:01');
        }
        if (!empty($toDate)) {
            $query->where('rat.CreatedDate','<=',$toDate.' 23:59:59');
        }
        if (!empty($keyword)) {
            $query->where(function($subQuery) use ($keyword) {
                $subQuery->where('c.FullName','like','%'.$keyword.'%')->orWhere('c.CustomerCode','like','%'.$keyword.'%');
            });
        }
        $query->orderByDesc('rat.CreatedDate');
        $data = $query->paginate($limit,['*'],'page', round((int) $lmstart/ (int) $limit) + 1);

        return $data;
    }

    public function createReceiptAdjustTracking($request)
    {
        $receiptCode = $request['ReceiptCode'] ?? '';
        $amount = $request['Amount'] ?? 0;
        $ticketSupportId = $request['TicketSupportId'] ?? 0;
        if(!$ticketSupportId) {
            $ticketSupportId = 0;
        }
        $staffId = 0;
        $userId = Auth::user()['UserId'];
        $staff = DB::table('in.Staff')->where('UserId', $userId)->first();
        if ($staff && !empty($staff)) {
            $staffId = $staff->StaffId ?? 0;
        }
        $data = DB::select(DB::raw("CALL pos.usp_ReceiptManualAdjust('".$receiptCode."',".$amount.",".$ticketSupportId.",".$staffId.")"));
        return $data;
    }

    public function getReceiptByReceiptCode($request)
    {
        $receiptCode = $request['ReceiptCode'] ?? '';
        $query = DB::table('pos.Receipt as r');
        $query->select(
            'r.ReceiptId',
            'r.ReceiptCode',
            'r.TotalAmount',
            'c.FullName as CustomerName',
            'c.CustomerCode'
        );
        $query->join('pos.Deposit as d','r.DepositId','=','d.DepositId');
        $query->join('pos.Customer as c','d.CustomerId','=','c.CustomerId');
        $query->where('r.State',1);
        $query->where('r.ReceiptCode',$receiptCode);

        $data = $query->get()->toArray();
        return $data;
    }
    public function getCustomerByIdNumber($customerIdNumber)
    {
        if (empty($customerIdNumber)) {
            return [];
        }
        $query = $this->_model->newQuery();
        $query->where('CustomerIdNumber', $customerIdNumber);
        return $query->first(); 
    }

    public function checkIpAddress($ipAddress)
    {
        try {

            $value = Redis::get('common:NetworkConfig_'.$ipAddress);
            if($value) {
                return json_decode($value);
            }else{
                $query = DB::table('in.NetworkConfig as nc')->select('bwl.BranchId');
                $query->join('in.BranchWorkLocation as bwl', 'bwl.WorkLocationId', '=', 'nc.WorkLocationId');
                $query->where('nc.WanIp', '=', $ipAddress);
                $query->whereIn('bwl.CompanyId', [1, 2, 3, 13]);
                $query->where('nc.State', '=', 1);
                $result = $query->first();

                if($result){
                    Redis::set('common:NetworkConfig_'.$ipAddress, json_encode($result->BranchId) );
                    return $result->BranchId;
                }
                return NULL;
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return NULL;
        }
    }

    public function checkTimeKeeper($staffId)
    {
        try {
            $value = Redis::get('common:TimeKeeper_'.$staffId);
            if($value) {
                return json_decode($value);
            }else{
                $query = TimeKeeper::where('StaffId', '=', $staffId);
                $query->where('Day', Carbon::now()->format('Y-m-d'));
                $result = $query->first();

                if($result){
                    Redis::set('common:TimeKeeper_'.$staffId, json_encode($result->TimeKeeperId) );
                    return $result->TimeKeeperId;
                }
                return NULL;
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return NULL;
        }
    }

    public function checkAppointmentCustomer($customerId)
    {
        if (empty($customerId)) {
            return NULL;
        }
        $startOfDay = Carbon::today()->timestamp;
        $endOfDay = Carbon::tomorrow()->timestamp - 1;
        $query = Appointment::where('CustomerId', $customerId);
        $query->select('AppointmentId', 'CustomerId', 'AppointmentStatusId');
        $query->where('AppointmentStatusId', '>', 1);
        $query->whereBetween('StartAt', [$startOfDay, $endOfDay]);
        return $query->first();
    }

    public function checkDentalChairBooking($appointmentId)
    {
        if (empty($appointmentId)) {
            return NULL;
        }
        $query = DentalChairBooking::where('AppointmentId', $appointmentId);

        return $query->first();
    }

    public function checkOrderDetails($orderDetails, $treatmentId)
    {
        if (empty($orderDetails)) {
            return false;
        }
        $query = DB::table('OrderDetail as od')
            ->select([
                'od.*',
                DB::raw('(SELECT COUNT(*) FROM PaymentDetail as pd WHERE pd.OrderDetailId = od.OrderDetailId) as numPd'),
                DB::raw('(SELECT COUNT(*) FROM PromotionOrderDetail as prod WHERE prod.OrderDetailId = od.OrderDetailId) as numProOd'),
                DB::raw('(SELECT TreatmentMedicalProcedureStatusId FROM TreatmentMedicalProcedure as tmp WHERE tmp.TreatmentMedicalProcedureId = od.TreatmentMedicalProcedureId) as TreatmentMedicalProcedureStatusId'),
            ])
            ->whereIn('od.OrderDetailId', $orderDetails)
            ->where('od.TreatmentId', $treatmentId);
        
        return $query->get()->toArray();
    }

    public function checkStatusOrderDetails($orderDetails)
    {
        try {
            if (empty($orderDetails)) {
                return [];
            }
            $query = OrderDetail::whereIn('OrderDetailId', $orderDetails)->select(['Amount', 'Status']);

            return $query->get()->toArray();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return [];
        }
    }

    public function checkServiceAndTooth($treatmentId, $addServices)
    {
        foreach ($addServices as $service) {
            $serviceId = $service['ServiceId'] ?? 0;
            $bodyPartId = $service['AnatomyBodyPartItemId'] ?? '';
            $query = OrderDetail::where('OrderDetail.TreatmentId', $treatmentId)->where('OrderDetail.ServiceId', $serviceId)
                ->join('TreatmentMedicalProcedure as tmp', 'tmp.TreatmentMedicalProcedureId', '=', 'OrderDetail.TreatmentMedicalProcedureId')
                ->select('OrderDetail.*', 'tmp.TreatmentMedicalProcedureStatusId')
                ->where('OrderDetail.AnatomyBodyPartItemId', $bodyPartId)
                ->whereIn('tmp.TreatmentMedicalProcedureStatusId', [1,2])
                ->where('OrderDetail.Status', '!=', 0); // Trạng thái khác đã xóa
            $result = $query->first();
            if($result){
                return $result;
            }
        }
    }

    public function createOrderDetail($treatment, $addServices, $orderId, $ipAddress, $branchId, $note = '')
    {
        try {
            $staffId = Auth::user()['StaffId'];
            $treatmentId = $treatment['TreatmentId'] ?? 0;
            $data = '';
            $dataArr = [];
            if (!empty($addServices)) {
                foreach ($addServices as $key => $service) {
                    $serviceId = $service['ServiceId'] ?? 0;
                    $bodyPartId = $service['AnatomyBodyPartItemId'] ?? '';
                    $exists = false;
                    foreach ($dataArr as $existing) {
                        if (
                            $existing['ServiceId'] == $serviceId &&
                            $existing['AnatomyBodyPartItemId'] == $bodyPartId
                        ) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $dataArr[] = [
                            'ServiceId' => $serviceId,
                            'AnatomyBodyPartItemId' => $bodyPartId,
                            'ServicePrice' => $service['BasePrice'] ?? 0,
                            'Type' => $service['Type'] ?? 1, // New: 1, Warranty: 2
                            'OrderDetailIdOld' => $service['OrderDetailId'] ?? 0,
                            'IsMainService' => $service['IsMainService'] ?? 0,
                            'Quantity' => $service['Quantity'] ?? 1,
                            'PromotionId' => $service['PromotionId'] ?? 0,
                            'VoucherCode' => $service['VoucherCode'] ?? NULL
                        ];
                    }
                }

                $data = json_encode($dataArr);
            }
            $result = DB::select(DB::raw("CALL pos.usp_AddOrderDetail(".$staffId.",".$treatmentId.",".$branchId.",".$orderId.",'".$ipAddress."','".$data."', '".$note."')"));

            return $result;
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return false;
        }
    }

    public function createOrderMeasuringConsulting($treatment, $addServices, $branchId)
    {
        try {
            $staffId = Auth::user()['StaffId'];
            $customerId = $treatment['CustomerId'] ?? 0;
            $infoOrderMeasuringConsulting = OrderMeasuringConsulting::where('CustomerId', $customerId)->first();
            if ($infoOrderMeasuringConsulting) { // Cập nhật thông tin tư vấn dịch vụ
                $generalityLevel = $infoOrderMeasuringConsulting->GeneralityLevel;
                $prostheticLevel = $infoOrderMeasuringConsulting->ProstheticLevel;
                $implantLevel = $infoOrderMeasuringConsulting->ImplantLevel;
                $orthodonticLevel = $infoOrderMeasuringConsulting->OrthodonticLevel;
                foreach ($addServices as $key => $service) {
                    $object = self::getServiceLevel($service['ServiceId']);
                    if($object){
                        $generalityLevel = $object->GeneralityLevel ?? $generalityLevel;
                        $prostheticLevel = $object->ProstheticLevel ?? $prostheticLevel;
                        $implantLevel = $object->ImplantLevel ?? $implantLevel;
                        $orthodonticLevel = $object->OrthodonticLevel ?? $orthodonticLevel;
                    }
                }

                $data = [
                    'GeneralityLevel' => $generalityLevel,
                    'ProstheticLevel' => $prostheticLevel,
                    'ImplantLevel' => $implantLevel,
                    'OrthodonticLevel' => $orthodonticLevel,
                    'UpdatedDate' => date('Y-m-d H:i:s'),
                    'UpdatedBy' => $staffId,
                    'ConsultingDate' => date('Y-m-d'),
                    'BranchId' => $branchId,
                    'DoctorStaffId' => $staffId,
                ];
                OrderMeasuringConsulting::where('OrderMeasuringConsultingId', $infoOrderMeasuringConsulting->OrderMeasuringConsultingId)->update($data);
                $infoOrderMeasuringConsultingDoctor = OrderMeasuringConsultingDoctor::where('OrderMeasuringConsultingId', $infoOrderMeasuringConsulting->OrderMeasuringConsultingId)->where('DoctorStaffId', $staffId)->first();
                if(!$infoOrderMeasuringConsultingDoctor){
                    $dataDoctor = [
                        'OrderMeasuringConsultingId' => $infoOrderMeasuringConsulting->OrderMeasuringConsultingId,
                        'DoctorStaffId' => $staffId,
                        'CreatedDate' => date('Y-m-d H:i:s')
                    ];
                    OrderMeasuringConsultingDoctor::create($dataDoctor);
                }
            } else { // Thêm mới thông tin tư vấn dịch vụ

                $generalityLevel = NULL;
                $prostheticLevel = NULL;
                $implantLevel = NULL;
                $orthodonticLevel = NULL;
                foreach ($addServices as $key => $service) {
                    $object = self::getServiceLevel($service['ServiceId']);
                    if($object){
                        $generalityLevel = $object->GeneralityLevel ?? $generalityLevel;
                        $prostheticLevel = $object->ProstheticLevel ?? $prostheticLevel;
                        $implantLevel = $object->ImplantLevel ?? $implantLevel;
                        $orthodonticLevel = $object->OrthodonticLevel ?? $orthodonticLevel;
                    }
                }
                $data = [
                    'CustomerId' => $customerId,
                    'ConsultingDate' => date('Y-m-d'),
                    'BranchId' => $branchId,
                    'DoctorStaffId' => $staffId,
                    'GeneralityLevel' => $generalityLevel,
                    'ProstheticLevel' => $prostheticLevel,
                    'ImplantLevel' => $implantLevel,
                    'OrthodonticLevel' => $orthodonticLevel,
                    'Status' => 1,
                    'CreatedDate' => date('Y-m-d H:i:s'),
                    'CreatedBy' => $staffId,
                ];
                $orderMeasuringConsultingId = OrderMeasuringConsulting::insertGetId($data);
                $dataDoctor = [
                        'OrderMeasuringConsultingId' => $orderMeasuringConsultingId,
                        'DoctorStaffId' => $staffId,
                        'CreatedDate' => date('Y-m-d H:i:s')
                    ];
                    OrderMeasuringConsultingDoctor::create($dataDoctor);
            }
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error createOrderMeasuringConsulting", [$e->getMessage()]);
            return false;
        }
    }

    public function getServiceLevel($serviceId)
    {
        $data = Service::where('ServiceId', '=', $serviceId)->select('GeneralityLevel','ProstheticLevel','ImplantLevel','OrthodonticLevel')->where('State', '=', 1)->first();
        return $data;
    }

    public function deleteOrderDetail($orderDetails, $branchId)
    {
        try {
            $staffId = Auth::user()['StaffId'];
            $data = implode(',', $orderDetails);
            $result = DB::select(DB::raw("CALL pos.usp_OrderDetail_Deleted('".$data."',".$staffId.",".$branchId.")"));

            return $result;
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return false;
        }
    }

    public function getTreatmentByCustomerId($customerId)
    {
        if (empty($customerId)) {
            return [];
        }
        $query = Treatment::where('PersonId', $customerId);
        $query->whereNull('ClosedAt');
        $query->orderByDesc('TreatmentId');
        
        return $query->first()  ?? [];
    }

    public function changeStatusOrderDetail($orderDetailAgree, $orderDetailUnAgree, $treatmentId, $customerId, $note = '', $branchId )
    {
        try {
            // 0: Đã xóa, 1: Đang tư vấn, 2: Đã đồng ý
            $orderDetails = !empty($orderDetailAgree) ? $orderDetailAgree : $orderDetailUnAgree;
            if (empty($orderDetails) || empty($treatmentId) || empty($customerId)) {
                return false;
            }
            $infoCustomer = Customer::find($customerId);
            $consultingStaffId = $infoCustomer->ConsultingStaffId;

            $orderChangingId = 0;
            $infoOrderDetail = OrderDetail::find($orderDetails[0]);
            $serviceId = NULL;
            if ($infoOrderDetail) {
                $orderChangingId = $infoOrderDetail->OrderChangingId;
                $serviceId = $infoOrderDetail->ServiceId;
            }
            $staffId = Auth::user()['StaffId'];
            $dataTracking = [];

            $anatomyBodyPartItemIdAgree = '';
            if (!empty($orderDetailAgree)) {
                $anatomyBodyPartItemIdAgree = OrderDetail::whereIn('OrderDetailId', $orderDetailAgree)
                    ->where('TreatmentId', $treatmentId)
                    ->pluck('AnatomyBodyPartItemId')
                    ->implode(',');
            }
            $anatomyBodyPartItemIdUnAgree = '';
            if (!empty($orderDetailUnAgree)) {
                $anatomyBodyPartItemIdUnAgree = OrderDetail::whereIn('OrderDetailId', $orderDetailUnAgree)
                    ->where('TreatmentId', $treatmentId)
                    ->pluck('AnatomyBodyPartItemId')
                    ->implode(',');
            }
            if(!empty($orderDetailAgree) && count($orderDetailAgree) > 0) {
                // Loại các dịch vụ bị khóa — không đổi trạng thái và không đổ tiền vào
                $orderDetailAgree = $this->filterLockedOrderDetailIds($orderDetailAgree);
                if(empty($orderDetailAgree)) {
                    return false;
                }
            }
            DB::beginTransaction();
            if(!empty($orderDetailAgree) && count($orderDetailAgree) > 0) {
                $status = 2; // Trạng thái đồng ý
                $dataUpdate['Status'] = $status;
                $dataUpdate['ConsultedBy'] = $staffId;
                $dataUpdate['ConsultedDate'] = Carbon::now();
                $dataUpdate['ConsultedBranchId'] = $branchId;
                $dataUpdate['ConsultingStaffId'] = $consultingStaffId ?? NULL;
                OrderDetail::whereIn('OrderDetailId', $orderDetailAgree)->where('TreatmentId', $treatmentId)->update($dataUpdate);
                $dataTracking = [
                    'OrderDetailIds' => implode(',', $orderDetailAgree),
                    'OrderChangingId' => $orderChangingId,
                    'ServiceId' => $serviceId,
                    'AnatomyBodyPartItemIds' => $anatomyBodyPartItemIdAgree,
                    'CustomerId' => $customerId,
                    'StaffId' => $staffId,
                    'ActionId' => 50,
                    'Action' => 'Thay đổi trạng thái',
                    'StatusId' => $status,
                    'Note' => $note,
                ];
                OrderDetailTracking::create($dataTracking);

                $listOrderDetailIdNotTreatment = OrderDetail::whereIn('OrderDetailId', $orderDetailAgree)
                    ->select('OrderDetailId')
                    ->where('TreatmentId', $treatmentId)
                    ->where('MedicalProcedureId', 0)
                    ->get()
                    ->toArray();

                if ($listOrderDetailIdNotTreatment) { // Đồng ý dịch vụ không có bước thì add vào AllocatedRevenueTracking
                    foreach ($listOrderDetailIdNotTreatment as $v) {
                        DB::statement('CALL usp_AllocatedRevenueTracking_Add(?, ?, NULL, NULL, NULL, NULL, NULL, ?)', [
                            $customerId,
                            $v['OrderDetailId'],
                            $staffId
                        ]);
                    }
                }

                // Tiền dư ví phân bổ vào dịch vụ đã xác nhận
                $infoOrderDetail = OrderDetail::where('OrderDetail.TreatmentId', $treatmentId)
                ->join('OrderDetailFinancial as odf', 'odf.OrderDetailId', '=', 'OrderDetail.OrderDetailId')
                ->where('OrderDetail.IsOverPaymentAmount', 1)->first();
                if ($infoOrderDetail) {
                    $availableAmount = $infoOrderDetail->AvailableAmount ?? 0;
                    if ($availableAmount > 0) {
                        $this->transferDepositToOrderDetail($availableAmount, $orderDetailAgree, $treatmentId, $customerId, $staffId);
                    }
                }
            }
            if(!empty($orderDetailUnAgree) && count($orderDetailUnAgree) > 0) {
                $status = 1; // Trạng thái không đồng ý
                $dataUpdateOrder['Status'] = $status;
                $dataUpdateOrder['ConsultedBy'] = NULL;
                $dataUpdateOrder['ConsultedDate'] = NULL;
                $dataUpdateOrder['ConsultedBranchId'] = NULL;
                $dataUpdateOrder['ConsultingStaffId'] = NULL;
                $dataUpdateOrder['FirstReceiptTime'] = NULL;
                $dataUpdateOrder['IsPayInstallments'] = 0;
                
                $dataTracking = [
                    'OrderDetailIds' => implode(',', $orderDetailUnAgree),
                    'OrderChangingId' => $orderChangingId,
                    'ServiceId' => $serviceId,
                    'AnatomyBodyPartItemIds' => $anatomyBodyPartItemIdUnAgree,
                    'CustomerId' => $customerId,
                    'StaffId' => $staffId,
                    'ActionId' => 50,
                    'Action' => 'Thay đổi trạng thái',
                    'StatusId' => $status,
                    'Note' => $note
                ];
                OrderDetailTracking::create($dataTracking);

                $listOrderDetailIdNotTreatment = OrderDetail::whereIn('OrderDetailId', $orderDetailUnAgree)
                    ->where('TreatmentId', $treatmentId)
                    ->where('MedicalProcedureId', 0)
                    ->pluck('OrderDetailId') // chỉ lấy giá trị OrderDetailId
                    ->toArray();

                if (!empty($listOrderDetailIdNotTreatment)) {
                    $listOrderDetailIdNotTreatmentStr = implode(',', $listOrderDetailIdNotTreatment);

                    DB::statement('CALL usp_OrderDetail_ShiftToDisagreement(?, ?, ?)', [
                        $listOrderDetailIdNotTreatmentStr,
                        $staffId,
                        $branchId
                    ]);
                }
                $data = [
                    'TreatmentId' => $treatmentId,
                    'CustomerId'    => $customerId,
                    'FromOrderChangingId' => $orderChangingId,
                    'FromServiceId' => $serviceId,
                    'FromOrderDetailId' => $orderDetailUnAgree,
                    'StaffId' => $staffId
                 ];
                $this->transferOrderDetailToDeposit($data); // Bỏ xác nhận dịch vụ chuyển vào ví phân bổ
                OrderDetail::whereIn('OrderDetailId', $orderDetailUnAgree)->where('TreatmentId', $treatmentId)->update($dataUpdateOrder);
            }
            Deposit::where('CustomerId', $customerId)->update(['LatestUpdated' => Carbon::now()]);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error changeStatusOrderDetail", [
                'message' => $e->getMessage(),
                'TreatmentId' => $treatmentId,
                'CustomerId' => $customerId,
            ]);
            return false;
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
                    'OrderDetailFinancial.InvoiceAmount',
                    'OrderDetailFinancial.TotalAmount'
                )
                ->join('OrderDetail as od', 'od.OrderDetailId', '=', 'OrderDetailFinancial.OrderDetailId')
                ->where('OrderDetailFinancial.CustomerId', $customerId)
                ->where('OrderDetailFinancial.TreatmentId', $treatmentId)
                ->where('od.IsPayInstallments', 0) // Không lấy dịch vụ trả góp
                ->whereIn('OrderDetailFinancial.OrderDetailId', $orderDetailAgree)->get()->toArray();

            if (!$toOrderDetail) {
                return true;
            }

            // Ưu tiên 1: dịch vụ đã làm (TotalAmount < InvoiceAmount), phân bổ phần đã làm trước = InvoiceAmount - TotalAmount
            // Sau đó nếu còn tiền, phân bổ tiếp phần chưa làm = OrderDetailAmount - InvoiceAmount
            $priority1 = array_filter($toOrderDetail, function($i) { return $i['TotalAmount'] < $i['InvoiceAmount']; });
            // Ưu tiên 2: dịch vụ chưa làm gì (TotalAmount >= InvoiceAmount), phân bổ = OrderDetailAmount - TotalAmount
            $priority2 = array_filter($toOrderDetail, function($i) { return !($i['TotalAmount'] < $i['InvoiceAmount']); });

            $totalAmountOrderDetail = 0;
            foreach ($priority1 as $i) {
                $totalAmountOrderDetail += $i['OrderDetailAmount'] - $i['TotalAmount']; // toàn bộ phần còn thiếu
            }
            foreach ($priority2 as $i) {
                $totalAmountOrderDetail += $i['OrderDetailAmount'] - $i['TotalAmount'];
            }

            $transferId = OrderTransferAmount::insertGetId([
                'TreatmentId'           => $treatmentId,
                'FromOrderChangingId'   => 0,
                'FromServiceId'         => 0,
                'TotalAmount'           => $overPaymentAmount > $totalAmountOrderDetail ? $totalAmountOrderDetail : $overPaymentAmount,
                'CreatedDate'           => $now,
                'CreatedStaffId'        => $staffId,
                'UpdatedStaffId'        => $staffId,
                'UpdatedDate'           => $now
            ]);

            $remainOverPaymentAmount = $overPaymentAmount;

            // Loop ưu tiên 1 trước: dịch vụ đã làm
            // Pass 1: phân bổ phần đã làm = InvoiceAmount - TotalAmount
            foreach ($priority1 as $item) {
                if ($remainOverPaymentAmount <= 0) break;

                $need = (int) $item['InvoiceAmount'] - (int) $item['TotalAmount'];
                if ($need <= 0) continue;
                $transferAmount = min($remainOverPaymentAmount, $need);
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
                $infoOrderDetail = OrderDetail::where('OrderDetailId', $item['OrderDetailId'])->where('TreatmentId', $treatmentId)->first();
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
                    'ConsultingStaffId'  => isset($infoOrderDetail->ConsultingStaffId) ? $infoOrderDetail->ConsultingStaffId : NULL,
                    'ConsultedBranchId'  => isset($infoOrderDetail->ConsultedBranchId) ? $infoOrderDetail->ConsultedBranchId : NULL
                ]);

                OrderDetail::where('OrderDetailId', $item['OrderDetailId'])->where('TreatmentId', $treatmentId)->update(['FirstReceiptTime' => $now]);

                if ($remainOverPaymentAmount <= 0) {
                    break;
                }
            }

            // Pass 2 cho priority1: nếu còn tiền, phân bổ tiếp phần chưa làm = OrderDetailAmount - InvoiceAmount
            foreach ($priority1 as $item) {
                if ($remainOverPaymentAmount <= 0) break;

                $infoOrderDetail = OrderDetail::where('OrderDetailId', $item['OrderDetailId'])->where('TreatmentId', $treatmentId)->first(); 
                $need = (int) $item['OrderDetailAmount'] - (int) $item['InvoiceAmount'];
                if ($need <= 0) continue;
                $transferAmount = min($remainOverPaymentAmount, $need);
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
                    'ConsultingStaffId'  => isset($infoOrderDetail->ConsultingStaffId) ? $infoOrderDetail->ConsultingStaffId : NULL,
                    'ConsultedBranchId'  => isset($infoOrderDetail->ConsultedBranchId) ? $infoOrderDetail->ConsultedBranchId : NULL
                ]);
            }

            // Loop ưu tiên 2: dịch vụ chưa làm, phân bổ = OrderDetailAmount - TotalAmount
            foreach ($priority2 as $item) {
                if ($remainOverPaymentAmount <= 0) break;

                $need = (int) $item['OrderDetailAmount'] - (int) $item['TotalAmount'];
                if ($need <= 0) continue;
                $transferAmount = min($remainOverPaymentAmount, $need);
                $remainOverPaymentAmount -= $transferAmount;

                $infoOrderDetail = OrderDetail::where('OrderDetailId', $item['OrderDetailId'])->where('TreatmentId', $treatmentId)->first();
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
                    'ConsultingStaffId'  => isset($infoOrderDetail->ConsultingStaffId) ? $infoOrderDetail->ConsultingStaffId : NULL,
                    'ConsultedBranchId'  => isset($infoOrderDetail->ConsultedBranchId) ? $infoOrderDetail->ConsultedBranchId : NULL
                ]);

                OrderDetail::where('OrderDetailId', $item['OrderDetailId'])->where('TreatmentId', $treatmentId)->update(['FirstReceiptTime' => $now]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('transferDepositToOrderDetail error', [
                'message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function checkTransferReceipt($orderDetailUnAgree, $treatmentId, $customerId){

        if (empty($orderDetailUnAgree)) {
            return true;
        }
        $baseQuery = OrderDetailFinancial::select(
                'OrderDetailFinancial.OrderDetailId',
                'OrderDetailFinancial.OrderDetailAmount',
                'OrderDetailFinancial.TotalAmount'
            )
            ->join('OrderDetail as od', 'od.OrderDetailId', '=', 'OrderDetailFinancial.OrderDetailId')
            ->where('OrderDetailFinancial.CustomerId', $customerId)
            ->where('OrderDetailFinancial.TreatmentId', $treatmentId)
            ->where('od.Status', '>', 1)
            ->whereNotIn('OrderDetailFinancial.OrderDetailId', $orderDetailUnAgree);

        $toOrderDetail = (clone $baseQuery)
            ->whereColumn('OrderDetailFinancial.OrderDetailAmount', '>', 'OrderDetailFinancial.TotalAmount')
            ->orderBy('OrderDetailFinancial.OrderDetailId')
            ->first();

        if (!$toOrderDetail) {
            $toOrderDetail = $baseQuery
                ->orderBy('OrderDetailFinancial.OrderDetailId')
                ->first();
        }

        if (!$toOrderDetail) {
            return false;
        }

        return true;
    }

    public function transferOrderDetailToDeposit($data)
    {
        try {
            $treatmentId           = (int) $data['TreatmentId'];
            $customerId            = (int) $data['CustomerId'];
            $fromOrderChangingId   = (int) $data['FromOrderChangingId'];
            $fromServiceId         = (int) $data['FromServiceId'];
            $fromIds               = $data['FromOrderDetailId'] ?? [];
            $staffId               = (int) $data['StaffId'];

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

            $fromOrderDetail = OrderDetailFinancial::select( // Danh sách OrderDetail chuyển tiền
                    'OrderDetailFinancial.OrderDetailId',
                    'OrderDetailFinancial.OrderDetailAmount',
                    'OrderDetailFinancial.TotalAmount'
                )
                ->join('OrderDetail as od', 'od.OrderDetailId', '=', 'OrderDetailFinancial.OrderDetailId')
                ->where('OrderDetailFinancial.CustomerId', $customerId)
                ->where('OrderDetailFinancial.TreatmentId', $treatmentId)
                // ->where('od.IsPayInstallments', 0) // Không lấy dịch vụ trả góp
                ->whereIn('OrderDetailFinancial.OrderDetailId', $fromIds)->get()->toArray();

            if (!$fromOrderDetail) {
                throw new \Exception('Không tìm thấy OrderDetail chuyển tiền phù hợp');
            }

            $totalAmountOrderDetail = array_sum(array_column($fromOrderDetail, 'TotalAmount'));

            $transferId = OrderTransferAmount::insertGetId([
                'TreatmentId'           => $treatmentId,
                'FromOrderChangingId'   => $fromOrderChangingId,
                'FromServiceId'         => $fromServiceId,
                'TotalAmount'           => $totalAmountOrderDetail,
                'CreatedDate'           => $now,
                'CreatedStaffId'        => $staffId,
                'UpdatedStaffId'        => $staffId,
                'UpdatedDate'           => $now
            ]);

            /**
             * 5. Ghi chi tiết & sổ tài chính
             */
            foreach ($fromOrderDetail as $item) {

                $detailId = OrderTransferAmountDetail::insertGetId([ 
                    'OrderTransferAmountId' => $transferId,
                    'FromOrderDetailId'     => $item['OrderDetailId'],
                    'ToOrderDetailId'       => $orderDetailId,
                    'Amount'                => $item['TotalAmount'],
                    'CreatedDate'           => $now,
                    'CreatedStaffId'        => $staffId,
                    'UpdatedStaffId'        => $staffId,
                    'UpdatedDate'           => $now
                ]);
                $infoOrderDetail = OrderDetail::where('OrderDetailId', $item['OrderDetailId'])->where('TreatmentId', $treatmentId)->first(); 

                // Ghi âm nguồn
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
                    'ConsultingStaffId'  => isset($infoOrderDetail->ConsultingStaffId) ? $infoOrderDetail->ConsultingStaffId : NULL,
                    'ConsultedBranchId'  => isset($infoOrderDetail->ConsultedBranchId) ? $infoOrderDetail->ConsultedBranchId : NULL
                ]);

                // Ghi dương Order nhận
                OrderDetailFinancialTrans::insert([
                    'TreatmentId'        => $treatmentId,
                    'CustomerId'         => $customerId,
                    'OrderDetailId'      => $orderDetailId,
                    'ObjectType'         => 'TransferAmountService',
                    'ObjectId'           => $transferId,
                    'ObjectDetailType'   => 'OrderTransferAmountDetail',
                    'ObjectDetailId'     => $detailId,
                    'TransferAmount'     => $item['TotalAmount'],
                    'CreatedStaffId'     => $staffId,
                    'CreatedDate'        => $now,
                ]);
            }

            $toOrderDetail = OrderDetailFinancial::select( // Danh sách OrderDetail đã xác nhận nhưng chưa thu đủ tiền
                    'OrderDetailFinancial.OrderDetailId',
                    'OrderDetailFinancial.OrderDetailAmount',
                    'OrderDetailFinancial.TotalAmount'
                )
                ->join('OrderDetail as od', 'od.OrderDetailId', '=', 'OrderDetailFinancial.OrderDetailId')
                ->where('OrderDetailFinancial.CustomerId', $customerId)
                ->where('OrderDetailFinancial.TreatmentId', $treatmentId)
                ->where('od.Status', '>', 1)
                ->where('od.IsPayInstallments', 0) // Không lấy dịch vụ trả góp
                ->whereNotIn('OrderDetailFinancial.OrderDetailId', $fromIds)
                ->whereColumn('OrderDetailFinancial.OrderDetailAmount', '>', 'OrderDetailFinancial.TotalAmount')
                ->get()->toArray();
            if(!empty($toOrderDetail) && is_array($toOrderDetail)){
                $orderDetailAgree = array_column($toOrderDetail, 'OrderDetailId');
                 // Tiền dư ví phân bổ vào dịch vụ đã xác nhận và thu thiếu tiền

                $infoOrderDetail = OrderDetail::where('OrderDetail.TreatmentId', $treatmentId)->where('OrderDetail.IsOverPaymentAmount', 1)
                ->join('OrderDetailFinancial as odf', 'odf.OrderDetailId', '=', 'OrderDetail.OrderDetailId')
                ->lockForUpdate()->first();

                $availableAmount = $infoOrderDetail->AvailableAmount ?? 0;
                if ($availableAmount > 0) {
                    $nonLockedIds = $this->filterLockedOrderDetailIds($orderDetailAgree);
                    if (!empty($nonLockedIds)) {
                        $this->transferDepositToOrderDetail($availableAmount, $nonLockedIds, $treatmentId, $customerId, $staffId);
                    }
                }
            }

            return true;

        } catch (\Throwable $e) {
            Log::error('transferOrderDetailToDeposit error', [
                'message' => $e->getMessage(),
                'data'    => $data
            ]);
            throw $e;
        }
    }


    public function getHistoryChangeOrderDetail($data)
    {
        $customerId = $data['CustomerId'];
        $actionId = $data['ActionId'];
        $keyword = $data['Keyword'];
        // $fromDate = $data['FromDate'];
        // $toDate = $data['ToDate'];
        $lmstart = $data['lmstart'] ?? 0;
        $limit = $data['limit'] ?? 10;
        if (empty($customerId)) {
            return [];
        }
        $query = OrderDetailTracking::where('OrderDetailTracking.CustomerId', $customerId);
        // $query->where('OrderDetailTracking.ActionTimestamp', '>=', $fromDate.' 00:00:01');
        // $query->where('OrderDetailTracking.ActionTimestamp', '<=', $toDate.' 23:59:59');
        $query->orderByDesc('OrderDetailTracking.ActionTimestamp');
        $query->with(['actionByStaff' => function ($subQuery) {
            $subQuery->select('StaffId', 'FullName', 'StaffCode');
        }]);
        $query->with(['createdOrderDetailByStaff' => function ($subQuery) {
            $subQuery->select('StaffId', 'FullName', 'StaffCode');
        }]);
        $query->with(['infoPromotion' => function ($subQuery) {
            $subQuery->select('ID', 'Code', 'Name');
        }]);
        $query->join('Service as s', 's.ServiceId', '=', 'OrderDetailTracking.ServiceId');
        $query->join('OrderChanging as oc', 'oc.OrderChangingId', '=', 'OrderDetailTracking.OrderChangingId');
        $query->addSelect(['OrderDetailTracking.*','s.Name as ServiceName', 's.ServiceCode', 'oc.ChangedAt as ServiceAddedAt']);
        if($keyword) {
            $keyword = trim($keyword);
            $query->join('in.Staff', 'Staff.StaffId', '=', 'OrderDetailTracking.StaffId');
            $query->where(function($subQuery) use ($keyword) {
                    $subQuery->where('s.Name', 'like', '%' . $keyword . '%')
                    ->orWhere('s.ServiceCode', 'like', '%' . $keyword . '%')
                    ->orWhere('Staff.FullName', 'like', '%' . $keyword . '%')
                    ->orWhere('Staff.StaffCode', 'like', '%' . $keyword . '%');
            });
        }
        if($actionId > 0) {
            $query->where('OrderDetailTracking.ActionId', $actionId);
        }

        $results = $query->paginate($limit,['*'],'page', round((int) $lmstart/ (int) $limit) + 1);

        return $results;
    }

    public function getAnatomyBodyPartItemName($anatomyBodyPartItemIds)
    {
        if (empty($anatomyBodyPartItemIds)) {
            return [];
        }
        $query = AnatomyBodyPartItem::whereIn('AnatomyBodyPartItemId', explode(',', $anatomyBodyPartItemIds))
            ->select('AnatomyBodyPartItemId', 'Name', 'Code')
            ->orderBy('AnatomyBodyPartItemId', 'asc');
        return $query->get();
    }

    public function spinWheel($branchId, $customerId, $typeId)
    {
        try {
            // ✅ 1. Kiểm tra đã quay trước đó
            // $data = $this->getSpinWheel($customerId);
            // if (!empty($data) && $data->count() > 0) {
            //     return $data;
            // }

            // ✅ 2. Lấy thông tin cần thiết
            $staffId        = Auth::user()['StaffId'] ?? null;
            $now            = Carbon::now();
            $maxAttempts    = 10;

            $customerInfo   = Customer::where('CustomerId', $customerId)->first();
            $customerPhone  = CustomerPhoneNumber::where('CustomerId', $customerId)->where('IsMain', 1)->first();

            if (!$customerInfo || !$customerPhone) {
                Log::warning("Không tìm thấy thông tin khách hàng {$customerId}");
                return [];
            }

            // ✅ 3. Retry tối đa 10 lần nếu lưu thất bại
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {

                // 3.1 Lấy phần thưởng còn hàng
                $prizes = LuckyDrawCampaign::where('LuckyDrawCampaign.State', 1)
                    ->join('LuckyDrawGifts as ldg', 'ldg.LuckyDrawCampaignId', '=', 'LuckyDrawCampaign.LuckyDrawCampaignId')
                    ->join('LuckyDrawGiftType as ldgt', 'ldgt.LuckyDrawGiftTypeId', '=', 'ldg.LuckyDrawGiftTypeId')
                    ->where('LuckyDrawCampaign.TypeId', $typeId)
                    ->where('LuckyDrawCampaign.StartDate', '<=', $now)
                    ->where('LuckyDrawCampaign.EndDate', '>=', $now)
                    ->where('ldg.RemainQuantity', '>', 0) // Chỉ lấy giải còn hàng
                    ->select(
                        'LuckyDrawCampaign.TotalSegments',
                        'LuckyDrawCampaign.LuckyDrawCampaignId',
                        'ldgt.LuckyDrawGiftTypeId',
                        'ldgt.Name',
                        'ldg.Probability',
                        'ldg.RemainQuantity'
                    )->get();
                if ($prizes->isEmpty()) {
                    Log::warning("Không có phần thưởng còn hàng.");
                    return [];
                }

                $dataPrizes = $prizes->toArray();

                // ✅ 3.2 Xác định tổng xác suất từ campaign
                $totalProbability = $dataPrizes[0]['TotalSegments'] ?? 300;

                // ✅ 3.3 Phân bổ xác suất hợp lệ
                $allPrizes = $this->distributeProbability($dataPrizes, $totalProbability);
                shuffle($allPrizes);
                // ✅ 3.4 Random có trọng số
                $selectedPrize = $this->pickPrizeByWeight($allPrizes);

                if (!$selectedPrize) {
                    Log::warning("Không chọn được phần thưởng, thử lại (lần $attempt)...");
                    continue;
                }

                // ✅ 3.5 Gọi store lưu kết quả (để xử lý race condition trong DB)
                $result = DB::select(DB::raw("CALL pos.usp_LuckyDrawSpins_UpdateResult(?, ?, ?, ?, ?, ?, ?)"), [
                    $customerId,
                    $customerInfo->FullName,
                    $customerPhone->PhoneNumber,
                    $branchId,
                    $selectedPrize['LuckyDrawCampaignId'],
                    $selectedPrize['LuckyDrawGiftTypeId'],
                    $staffId
                ]);

                if (!empty($result) && isset($result[0]->Result) && $result[0]->Result == 1) {
                    return $selectedPrize; // ✅ Thành công, trả về phần thưởng
                }

                Log::warning("Lần $attempt: Gọi store thất bại, thử lại...");
            }

            // ✅ 4. Nếu hết 10 lần vẫn fail
            Log::error("Gọi store 10 lần đều thất bại.");
            return [];

        } catch (\Exception $e) {
            Log::error("Lỗi spinWheel: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ✅ Phân bổ xác suất cho giải thưởng
     */
    public function distributeProbability($prizes, $totalProbability = 300)
    {
        $fixedPrizes     = [];
        $dynamicPrizes   = [];
        $totalFixed      = 0;

        // ✅ 1. Phân loại giải có xác suất cố định & động
        foreach ($prizes as $prize) {
            if ($prize['RemainQuantity'] <= 0) continue;

            if ($prize['Probability'] > 0) {
                $fixedPrizes[]  = $prize;
                $totalFixed    += $prize['Probability'];
            } else {
                $dynamicPrizes[] = $prize;
            }
        }

        // ✅ 2. Phân bổ xác suất cho giải chưa set
        $remainProbability = max($totalProbability - $totalFixed, 0);
        $countDynamic      = count($dynamicPrizes);

        if ($countDynamic > 0 && $remainProbability > 0) {
            $probPerPrize = floor($remainProbability / $countDynamic);
            $used         = 0;

            foreach ($dynamicPrizes as &$prize) {
                $prize['Probability'] = $probPerPrize;
                $used += $probPerPrize;
            }

            // ✅ Nếu còn dư, cộng dồn lần lượt
            $extra = $remainProbability - $used;
            for ($i = 0; $i < $extra; $i++) {
                $dynamicPrizes[$i]['Probability'] += 1;
            }
        }

        // ✅ 3. Nếu không có dynamic nhưng totalFixed < totalProbability → scale lên
        if ($countDynamic === 0 && $totalFixed < $totalProbability && $totalFixed > 0) {
            $scale = $totalProbability / $totalFixed;
            foreach ($fixedPrizes as &$prize) {
                $prize['Probability'] = floor($prize['Probability'] * $scale);
            }
        }

        return array_merge($fixedPrizes, $dynamicPrizes);
    }

    /**
     * ✅ Random chọn phần thưởng theo trọng số
     */
    private function pickPrizeByWeight($prizes)
    {
        if (empty($prizes)) return null;

        $totalWeight = array_sum(array_column($prizes, 'Probability'));
        if ($totalWeight <= 0) return null;

        $rand = random_int(1, $totalWeight);
        $acc  = 0;

        foreach ($prizes as $prize) {
            $acc += $prize['Probability'];
            if ($rand <= $acc) {
                return $prize;
            }
        }

        return null;
    }


    public function getCustomerPrize(int $typeId)
    {
        try {
            $query = LuckyDrawCampaign::where('State', 1)->where('StartDate', '<=', Carbon::now())->where('EndDate', '>=', Carbon::now());
            $query->join('LuckyDrawGifts as ldg', 'ldg.LuckyDrawCampaignId', '=', 'LuckyDrawCampaign.LuckyDrawCampaignId');
            $query->join('LuckyDrawGiftType as ldgt', 'ldgt.LuckyDrawGiftTypeId', '=', 'ldg.LuckyDrawGiftTypeId');
            $query->select('ldgt.LuckyDrawGiftTypeId', 'ldgt.Name');
            $query->where('LuckyDrawCampaign.TypeId', $typeId);
            return $query->get();
        } catch (\Exception $e) {
            Log::error("Error getCustomerPrize", [$e->getMessage()]);
            return [];
        }
    }

    public function getSpinWheel($customerId, $typeId)
    {
        try {
            $toDay = Carbon::now()->toDateString();
            $query = LuckyDrawSpins::where('CustomerId', $customerId);
            $query->join('LuckyDrawCampaign', 'LuckyDrawCampaign.LuckyDrawCampaignId', '=', 'LuckyDrawSpins.LuckyDrawCampaignId');
            $query->where('LuckyDrawCampaign.TypeId', $typeId);
            $query->with(['customerPrize' => function ($subQuery) {
                $subQuery->select('LuckyDrawGiftTypeId', 'Name', 'Priority');
            }]);
            $query->with(['createByStaff' => function ($subQuery) {
                $subQuery->select('StaffId', 'FullName', 'StaffCode');
            }]);
            $query->with(['createByBranch' => function ($subQuery) {
                $subQuery->select('BranchId', 'BranchCode', 'Name');
            }]);
            $query->where('LuckyDrawSpins.CreatedDate', '>=', $toDay);
            return $query->get();
        } catch (\Exception $e) {
            Log::error("Error getSpinWheel", [$e->getMessage()]);
            return [];
        }
    }

    public function getAppointmentCustomer($customerId)
    {
        if (empty($customerId)) {
            return NULL;
        }
        $startOfDay = Carbon::today()->timestamp;
        $endOfDay = Carbon::tomorrow()->timestamp - 1;
        
        $query = Appointment::where('CustomerId', $customerId)->where('Appointment.AppointmentStatusId', '>=', 21)->whereBetween('Appointment.StartAt', [$startOfDay, $endOfDay]);

        return $query->first();
    }

    public function checkIpAddressHCM($ipAddress)
    {
        try {

            $value = Redis::get('common:NetworkConfig_'.$ipAddress);
            if($value) {
                return json_decode($value);
            }else{
                $query = DB::table('in.NetworkConfig as nc')->select('bwl.BranchId');
                $query->join('in.BranchWorkLocation as bwl', 'bwl.WorkLocationId', '=', 'nc.WorkLocationId');
                $query->join('in.Branch as b', 'b.BranchId', '=', 'bwl.BranchId');
                $query->where('nc.WanIp', '=', $ipAddress);
                $query->whereIn('b.ProvinceId', [74,77,79]);
                $query->whereIn('bwl.CompanyId', [1, 2, 3, 13]);
                $query->where('nc.State', '=', 1);
                $result = $query->first();

                if($result){
                    Redis::set('common:NetworkConfig_'.$ipAddress, json_encode($result->BranchId) );
                    return $result->BranchId;
                }
                return NULL;
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return NULL;
        }
    }

    public function getTotalReceiptCustomer($customerId)
    {
        try {
            $toDay = Carbon::now()->startOfDay()->timestamp;

            $total = Deposit::where('Deposit.CustomerId', $customerId)
                ->join('Receipt as r', 'r.DepositId', '=', 'Deposit.DepositId')
                ->where('r.AddedAt', '>=', $toDay)
                ->sum('r.TotalAmount');

            return $total ?? 0;

        } catch (\Exception $e) {
            Log::error('Error getTotalReceiptCustomer', [$e->getMessage()]);
            return 0;
        }
    }

    public function checkConfirmSpinResults($customerId)
    {
        try {
            $toDay = Carbon::now()->toDateString();
            $query = LuckyDrawSpins::where('CustomerId', $customerId)
                ->whereNotNull('ReceivedTime')
                ->where('CreatedDate', '>=', $toDay);
            return $query->exists();
        } catch (\Exception $e) {
            Log::error("Error checkConfirmSpinResults", [$e->getMessage()]);
            return false;
        }
    }

    public function confirmSpinResults($customerId, $branchId, $luckyDrawCampaignId, $luckyDrawGiftTypeId)
    {
        try {
            $staffId = Auth::user()['StaffId'];
            $result = DB::select(DB::raw("CALL pos.usp_LuckyDrawSpins_UpdateReceivedTime(?, ?, ?, ?, ?)"), [
                $customerId,
                $branchId,
                $luckyDrawCampaignId,
                $luckyDrawGiftTypeId,
                $staffId
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error("Error confirmSpinResults", [$e->getMessage()]);
            return false;
        }
    }

    public function addOrUpdateInvisalign($customerId, $invisalignId)
    {
        try {
            $staffId = Auth::user()['StaffId'];
            $infoCustomerInvisalign = CustomerInvisalign::where('CustomerId', $customerId)->first();
            if($infoCustomerInvisalign){ // Cập nhật Invisalign
                if(!$invisalignId){
                    $invisalignId = NULL;
                }
                CustomerInvisalign::where('CustomerId', $customerId)->update(
                    [
                        'InvisalignId' => $invisalignId,
                        'UpdatedBy' => $staffId,
                        'UpdatedDate' => Carbon::now()
                    ]
                );
                $dataTracking = [
                    'CustomerInvisalignId' => $infoCustomerInvisalign->CustomerInvisalignId,
                    'OldCustomerId' => $infoCustomerInvisalign->CustomerId,
                    'OldInvisalignId' => $infoCustomerInvisalign->InvisalignId,
                    'UpdatedBy' => $staffId,
                    'UpdatedDate' => Carbon::now()
                ];
                CustomerInvisalignTracking::insert($dataTracking);
                
                return true;
            } else { // Tạo mới Invisalign
                $data = [
                    'InvisalignId' => $invisalignId,
                    'CustomerId'    => $customerId,
                    'CreatedBy' => $staffId,
                    'CreatedDate' => Carbon::now()
                ];
                CustomerInvisalign::insert($data);

                return true;
            }
        } catch (\Exception $e) {
            Log::error("Error addOrUpdateInvisalign", [$e->getMessage()]);
            return false;
        }
    }

    public function checkCustomerInvisalign($invisalignId)
    {
        $query = CustomerInvisalign::where('InvisalignId', $invisalignId);
        $query->join('Customer as c', 'c.CustomerId', '=', 'CustomerInvisalign.CustomerId');
        $query->select('c.CustomerId', 'c.FullName', 'c.CustomerCode');
        return $query->first();
    }

    public function listWarrantyServiceByCustomer($treatmentId)
    {
        try {
            if (empty($treatmentId)) {
                return [];
            }
            $query = OrderDetail::where('OrderDetail.TreatmentId', $treatmentId);
            $query->join('pos.TreatmentMedicalProcedure as tmp', 'tmp.TreatmentMedicalProcedureId', '=', 'OrderDetail.TreatmentMedicalProcedureId'); // Lấy dịch vụ có bước điều trị
            $query->select('OrderDetail.OrderDetailId', 'OrderDetail.OrderChangingId', 'OrderDetail.TreatmentMedicalProcedureId', 'OrderDetail.ServiceId', 'OrderDetail.AnatomyBodyPartItemId','OrderDetail.AnatomyBodyPartItemName','OrderDetail.ServiceName');
            $query->where('OrderDetail.Status', 2); // Đã đồng ý
            $query->whereNull('OrderDetail.DeletedDate');
            $query->where('tmp.TreatmentMedicalProcedureStatusId', 3); // Đã hoàn thành
            $query->orderByDesc('OrderDetail.LatestUpdated');

            $rows = $query->get();

            $seen = [];
            $result = [];
            // Nếu có nhiều dịch vụ giống nhau (ServiceId, AnatomyBodyPartItemId) thì chỉ lấy 1 dịch vụ cuối
            foreach ($rows as $row) {
                $key = $row->ServiceId . '|' . ($row->AnatomyBodyPartItemId ?? 'null');
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $result[] = $row->toArray();
                }
            }
            return $result;
        } catch (\Exception $e) {
            Log::error("Error listWarrantyServiceByCustomer", [$e->getMessage()]);
            return [];
        }
    }
    
    public function updatePhoto($customerId, $photo, $photoResize)
    {
        try {
            $userId = Auth::user()['UserId'];
            if (empty($customerId)) {
                return false;
            }
            if (!$photo) {
                $photo = NULL;
            }
            if (!$photoResize) {
                $photoResize = NULL;
            }
            Customer::where('CustomerId', $customerId)->update(
                [
                    'Photo' => $photo,
                    'PhotoResize' => $photoResize,
                    'UpdatedAt' => Carbon::now()->unix(),
                    'UpdatedBy' => $userId
                ]
            );
            return true;
        } catch (\Exception $e) {
            Log::error("Error updatePhoto", [$e->getMessage()]);
            return false;
        }
        return false;
    }

    public function saveCustomerDoctor($customerId = 0, $staffId = 0)
    {
        if (!$customerId || empty($customerId)) {
            return false;
        }
        if (!$staffId || empty($staffId)) {
            $staffId = Auth::user()['StaffId'] ?? 0;
        }
        if (!$staffId || empty($staffId)) {
            return false;
        }
        $doctor = Doctor::where('StaffId', $staffId)->where('State', 1)->first();
        if (!$doctor || empty($doctor)) {
            return false;
        }
        $exist = CustomerDoctor::where('CustomerId', $customerId)
            ->where('DoctorId', $doctor->DoctorId ?? 0)
            ->first();
        if ($exist && !empty($exist)) {
            return false;
        }
        return CustomerDoctor::create([
            'CustomerId' => $customerId,
            'DoctorId' => $doctor->DoctorId ?? 0,
            'StaffId' => $staffId,
            'CreatedBy' => Auth::user()['StaffId'] ?? 0,
            'CreatedDate' => Carbon::now()->toDateTimeString()
        ]);
    }

    public function checkStaffDoctor($staffId, $arrServices)
    {
        if (empty($staffId)) {
            return false;
        }
        $doctor = Doctor::where('StaffId', $staffId)
                        ->where('State', 1)
                        ->first();
        
        if (!$doctor) {
            $arrServiceId = array_column($arrServices, 'ServiceId');
            $serviceGroupIds = Service::whereIn('ServiceId', $arrServiceId)
                ->where('ServiceGroupId', '<>', 43) // Khác nhóm bán hàng
                ->get()
                ->toArray();
            if (!empty($serviceGroupIds)) {
                return false;
            }
        }
        return true;
    }

    public function listStaffByBranch($branchId)
    {
        if (empty($branchId)) {
            return [];
        }
        $query = Staff::where('wp.IsCurrentProfile', 1)->whereIn('wpp.Code',['Reception','GSVH'])->where('wp.CurrentBranchId', $branchId);
        $query->select('Staff.StaffId', 'Staff.FullName', 'Staff.StaffCode');
        $query->join('in.WorkProfile as wp', 'wp.StaffId', '=', 'Staff.StaffId');
        $query->join('in.WorkProfilePosition as wpp', 'wpp.WorkProfilePositionId', '=', 'wp.CurrentWorkProfilePositionId');
        return $query->get();
    }

    public function getCustomerByCode($customerCode)
    {
        if (empty($customerCode)) {
            return [];
        }
        $query = $this->_model->newQuery();
        $query->where('CustomerCode', $customerCode);
        return $query->first();
    }

    public function listStaffByBranchInDay($branchId, $data = [])
    {
        if (empty($branchId)) {
            return [];
        }
        $currentWorkProfilePositionId = $data['CurrentWorkProfilePositionId'] ?? 438;
        $currentStaffId = $data['CurrentStaffId'] ?? (Auth::user()['StaffId'] ?? 0);
        $dashboardRepo = new DashboardRepository();
        $today = Carbon::now()->toDateString();
        $staffs = $dashboardRepo->listStaffByBranchId($branchId, $today, $today, $currentWorkProfilePositionId, $currentStaffId, false);

        if (!$staffs || empty($staffs)) {
            return [];
        }
        //Result
        $staffFilters = [];

        foreach ($staffs as $staff) {
            if (!$staff || empty($staff)) {
                continue;
            }
            
            if (!isset($staff->StaffId) || empty($staff->StaffId)) {
                continue;
            }

            if (!isset($staff->WorkProfilePositions) || empty($staff->WorkProfilePositions)) {
                continue;
            }
            $workProfilePositions = $staff->WorkProfilePositions ?? [];
            foreach ($workProfilePositions as $workProfilePosition) {
                if (!$workProfilePosition || empty($workProfilePosition)) {
                    continue;
                }
                if (in_array($workProfilePosition, ['Tư vấn viên', 'Quản lý phòng khám', 'Quyền quản lý phòng khám'])) {
                    $staffFilters[$staff->StaffId ?? 0] = $staff;
                    continue;
                }
            }
        }

        return array_values($staffFilters);
    }

    public function infoInsurance($customerId, $treatmentId)
    {
        $today = date('Y-m-d');

        $query = DB::table('pos.OrderDetail as od')

            ->select(
                'od.ServiceId',
                'od.ServiceName',
                DB::raw('sm.Name as ServiceInvoiceName'),
                DB::raw('SUM(od.Amount) as TotalAmount'),

                DB::raw("
                    GROUP_CONCAT(
                        DISTINCT od.AnatomyBodyPartItemName
                        ORDER BY od.AnatomyBodyPartItemName
                        SEPARATOR ', '
                    ) as AnatomyBodyPartItemName
                "),

                DB::raw("
                    GROUP_CONCAT(
                        DISTINCT dg.DiagnosisName
                        ORDER BY dg.DiagnosisName
                        SEPARATOR ', '
                    ) as DiagnosisName
                "),

                DB::raw("
                    GROUP_CONCAT(
                        DISTINCT dg.ICD10Code
                        ORDER BY dg.ICD10Code
                        SEPARATOR ', '
                    ) as ICD10Code
                ")
            )

            ->leftJoin('invoice.ServiceMapping as sm', function ($join) {
                $join->on('sm.ServiceDomainId', '=', 'od.ServiceId');
            })

            ->leftJoin(DB::raw("
                (
                    SELECT 
                        abpi.AnatomyBodyPartItemId,

                        GROUP_CONCAT(
                            DISTINCT d.Name
                            ORDER BY d.Name
                            SEPARATOR ', '
                        ) AS DiagnosisName,

                        GROUP_CONCAT(
                            DISTINCT d.ICD10Code
                            ORDER BY d.ICD10Code
                            SEPARATOR ', '
                        ) AS ICD10Code

                    FROM pos.PersonDiagnosis AS pd

                    JOIN pos.Treatment AS t
                        ON t.PersonDiagnosisId = pd.PersonDiagnosisId

                    JOIN pos.PersonDiagnosisDetail AS pdd
                        ON pdd.PersonDiagnosisId = pd.PersonDiagnosisId

                    JOIN pos.Diagnosis AS d
                        ON d.DiagnosisId = pdd.DiagnosisId

                    JOIN pos.PersonDiagnosisDetailAnatomyBodyPartItem AS pdda
                        ON pdda.PersonDiagnosisDetailId = pdd.PersonDiagnosisDetailId

                    JOIN pos.AnatomyBodyPartItem AS abpi
                        ON abpi.AnatomyBodyPartItemId = pdda.AnatomyBodyPartItemId

                    WHERE pd.PersonId = {$customerId}

                    GROUP BY abpi.AnatomyBodyPartItemId
                ) as dg
            "), function ($join) {
                $join->on('dg.AnatomyBodyPartItemId', '=', 'od.AnatomyBodyPartItemId');
            })

            ->where('od.TreatmentId', $treatmentId)

            ->where(function ($q) use ($today) {
                $q->where(function ($sub) {
                    $sub->whereNotNull('od.ConsultedBy')
                        ->whereNull('od.FirstTreatmentTime');
                })
                ->orWhere('od.FirstTreatmentTime', '>=', $today);
            })

            ->where('od.Status', '>', 1)

            ->groupBy(
                'od.ServiceId',
                'od.ServiceName',
                'sm.Name'
            )

            ->orderBy('od.ServiceName')

            ->get();

        return $query->toArray();
    }

    public function getCustomerInsuranceById(int $id)
    {
        return \App\CustomerInsurance::find($id);
    }

    public function getCustomerBasicInfo(int $customerId)
    {
        return $this->_model->newQuery()
            ->leftJoin('CustomerPhoneNumber', function ($join) {
                $join->on('CustomerPhoneNumber.CustomerId', '=', 'Customer.CustomerId')
                     ->where('CustomerPhoneNumber.IsMain', '=', 1);
            })
            ->where('Customer.CustomerId', $customerId)
            ->select(
                'Customer.CustomerId',
                'Customer.FullName',
                'Customer.CustomerCode',
                'CustomerPhoneNumber.PhoneNumber'
            )
            ->first();
    }

    /**
     * Lấy danh sách dịch vụ theo serviceIds đã chọn, validate khớp với điều kiện infoInsurance.
     * Throw nếu serviceId nào không tồn tại hoặc không thỏa điều kiện.
     */
    public function getSelectedTreatmentServices(int $customerId, int $treatmentId, array $serviceIds): array
    {
        $all = $this->infoInsurance($customerId, $treatmentId);

        $indexed = [];
        foreach ($all as $item) {
            $indexed[(int) $item->ServiceId] = $item;
        }

        $selected = [];
        $notFound = [];
        foreach ($serviceIds as $sid) {
            $sid = (int) $sid;
            if (!isset($indexed[$sid])) {
                $notFound[] = $sid;
            } else {
                $selected[] = (array) $indexed[$sid];
            }
        }

        if (!empty($notFound)) {
            throw new \RuntimeException(
                'ServiceId không hợp lệ hoặc không thuộc buổi điều trị này: ' . implode(', ', $notFound)
            );
        }

        return $selected;
    }

    /**
     * Lấy tên bác sĩ từ TreatmentHistory cuối cùng của buổi điều trị.
     * UpdatedBy → Staff.FullName
     */
    public function getDoctorByTreatmentId(int $treatmentId): string
    {
        $history = DB::table('TreatmentHistory')
            ->where('TreatmentId', $treatmentId)
            ->orderByDesc('TreatmentHistoryId')
            ->select('UpdatedBy')
            ->first();

        if (!$history || !$history->UpdatedBy) {
            return '';
        }

        $staff = DB::table('in.Staff')
            ->where('UserId', $history->UpdatedBy)
            ->select('FullName')
            ->first();

        return $staff ? (string) $staff->FullName : '';
    }

    public function infoCustomer($customerId)
    {
        $customer = $this->_model->newQuery()
            ->leftJoin('in.Branch', 'Branch.BranchId', '=', 'Customer.LastCheckinBranchId')
            ->leftJoin('CustomerPhoneNumber', function($join) {
                $join->on('CustomerPhoneNumber.CustomerId', '=', 'Customer.CustomerId')
                     ->where('CustomerPhoneNumber.IsMain', '=', 1);
            })
            ->where('Customer.CustomerId', $customerId)
            ->select(
                'Customer.CustomerId',
                'Customer.CustomerIdNumber',
                'Customer.LastCheckinBranchId',
                'Customer.Birthday',
                'Branch.BranchCode',
                'CustomerPhoneNumber.PhoneNumber'
            )
            ->first();
        
        if (!$customer) {
            return null;
        }
        
        // Lấy thông tin bảo hiểm của khách hàng
        $insurances = CustomerInsurance::where('CustomerId', $customerId)
            ->selectRaw('CustomerInsurance.*, ipc.ProviderCode, IF(ipc.CompanyId IS NOT NULL, 1, 0) as IsProviderCredential')
            ->where('Status', 1)
            ->leftjoin('InsuranceProviderCredentials as ipc', 'ipc.CompanyId', '=', 'CustomerInsurance.CompanyId')
            ->orderBy('Priority', 'ASC')
            ->get();
        
        if ($insurances->isNotEmpty()) {
            // Lấy CompanyId để join với PartnerCompany
            $companyIds = $insurances->pluck('CompanyId')->unique()->filter()->toArray();
            
            // Lấy thông tin PartnerCompany
            $companies = [];
            if (!empty($companyIds)) {
                $companies = PartnerCompany::whereIn('PartnerCompanyId', $companyIds)
                    ->get()
                    ->keyBy('PartnerCompanyId');
            }
            
            // Lấy CustomerInsuranceId để join với CustomerInsuranceImage
            $insuranceIds = $insurances->pluck('id')->toArray();
            
            // Lấy images cho các insurance
            $images = [];
            if (!empty($insuranceIds)) {
                $images = CustomerInsuranceImage::whereIn('CustomerInsuranceId', $insuranceIds)
                    ->orderBy('ImageOrder', 'ASC')
                    ->get()
                    ->groupBy('CustomerInsuranceId');
            }
            
            // Gắn thông tin company và images vào từng insurance
            $insuranceData = [];
            foreach ($insurances as $insurance) {
                $company = $companies[$insurance->CompanyId] ?? null;
                $insuranceImages = $images[$insurance->id] ?? collect();
                
                $insuranceData[] = [
                    'id' => $insurance->id,
                    'CustomerId' => $insurance->CustomerId,
                    'CompanyId' => $insurance->CompanyId,
                    'CompanyName' => $company ? $company->Name : null,
                    'CompanyShortName' => $company ? $company->ShortName : null,
                    'InsuranceCode' => $insurance->InsuranceCode,
                    'InsuranceType' => $insurance->InsuranceType,
                    'FromDate' => $insurance->FromDate,
                    'ToDate' => $insurance->ToDate,
                    'Priority' => $insurance->Priority,
                    'Status' => $insurance->Status,
                    'ProviderCode' => $insurance->ProviderCode ?? '',
                    'IsProviderCredential' => (bool)$insurance->IsProviderCredential,
                    'Images' => $insuranceImages->map(function($img) {
                        return [
                            'Id' => $img->Id,
                            'ImageOrder' => $img->ImageOrder,
                            'File' => $img->File,
                            'FileUrl' => API_MEDIA . '/' . $img->File,
                        ];
                    })->toArray()
                ];
            }
            
            $customer->Insurances = $insuranceData;
        } else {
            $customer->Insurances = [];
        }
        
        return $customer;
    }
}
