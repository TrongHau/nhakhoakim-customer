<?php

namespace App\Repositories;

use App\Repositories\Abstracts\EloquentRepository;
use App\Appointment;
use App\ImplantOrder;
use App\ImplantOrderDetail;
use App\ImplantOrderTracking;
use App\ImplantSupplies;
use App\ImplantMapping;
use App\ImplantTechnicalSpecification;
use App\Doctor;
use App\Staff;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Libs\Helper;
use App\Libs\Factory;

class ImplantOrderRepository extends EloquentRepository
{
    protected function getModel()
    {
        return ImplantOrder::class;
    }

    public function listImplantOrderAll($customerId, $lmstart, $limit, $fromDate, $toDate, $keyword, $branchId, $staffId) {
        $query = $this->_model->newQuery();
        $query->select([
            'ImplantOrderId',
            'CreatedDate',
            'CreatedBy',
            'ImplantOrderCode',
            'UsingDate',
            'OrderType',
            'UpdatedDate',
            'UpdatedBy',
            'Note',
            'CustomerId',
            'Status',
            'ExpectedDeliveryDate',
            'ActualDeliveryDate',
            'BranchId',
            'ResponsibleStaffId',
            'LatestComment',
            'LatestCommentBy',
            'LatestCommentDate'
        ]);

        $query->with(['createdByStaff' => function ($subQuery) {
            $subQuery->select('StaffId', 'FullName', 'StaffCode');
        }]);
        $query->with(['commentByStaff' => function ($subQuery) {
            $subQuery->select('StaffId', 'FullName', 'StaffCode');
        }]);
        $query->with(['updatedByStaff' => function ($subQuery) {
            $subQuery->select('StaffId', 'FullName', 'StaffCode');
        }]);
        $query->with(['orderByCustomer' => function ($subQuery) {
            $subQuery->select('CustomerId', 'CustomerCode', 'FullName');
        }]);
        $query->with(['byBranch' => function ($subQuery) {
            $subQuery->select('BranchId', 'BranchCode', 'Name');
        }]);
        if($customerId > 0){
            $query->where('CustomerId', '=', $customerId);
            $query->whereIn('Status', [1,20,30,40,50]);
        }else{
            if($fromDate){
                $query->where('CreatedDate', '>=', $fromDate)->whereIn('Status', [30,40,50]);
            }else{
                $query->whereIn('Status', [1,20]);
            }
        }
        if($toDate){
            $query->where('CreatedDate', '<=', $toDate);
        }
        // Tìm kiếm theo chi nhánh
        if($branchId){
            $query->where('BranchId', '=', $branchId);
        }
        // Tìm kiếm theo mã khách hàng hoặc tên khách hàng
        if($keyword){
            $query->whereHas('customerSearch', function ($subQuery) use ($keyword) {
                return $subQuery->where('FullName', 'like', '%' . $keyword . '%')
                    ->orWhere('CustomerCode', 'like', '%' . $keyword . '%');
            });
        }
        if($staffId){
            $query->where('CreatedBy', '=', $staffId);
        }
        if($fromDate){
            $query->orderByDesc('UpdatedDate');
        }else{
            $query->orderByDesc('ImplantOrderId');
        }
        $results = $query->paginate($limit,['*'],'page', round((int) $lmstart/ (int) $limit) + 1);

        if (!$results || empty($results)) {
            return [];
        }
        
        foreach ($results as $result) {
            if (Helper::isJSON($result->Note ?? '')) {
                $result->Note = json_decode($result->Note, true);
            }
            $result->ImplantOrderDetail = ImplantOrderDetail::select([
                'ImplantOrderDetail.TechnicalSpecificationId',
                'ImplantOrderDetail.ImplantOrderDetailId',
                'ImplantOrderDetail.Quantity',
                'its.Name as ImplantTechnicalSpecificationName',
                'its.ImplantSuppliesId',
                'its.ImplantSupplierId',
                'its.Length',
                'its.Width',
                'its.Height',
                'its.Radius',
                'its.Unit',
                'isr.Name as ImplantSupplierName',
                'iss.Name as ImplantSuppliesName',
                'its.ImplantTechnicalSpecificationCode'
                ])
                ->join('pos.ImplantTechnicalSpecification as its', 'its.ImplantTechnicalSpecificationId', '=', 'ImplantOrderDetail.TechnicalSpecificationId')
                ->join('pos.ImplantSupplier as isr', 'isr.ImplantSupplierId', '=', 'its.ImplantSupplierId')
                ->join('pos.ImplantSupplies as iss', 'iss.ImplantSuppliesId', '=', 'its.ImplantSuppliesId')
                ->where('ImplantOrderDetail.ImplantOrderId', $result->ImplantOrderId)
                ->where('isr.Status', 1)
                ->where('iss.Status', 1)->get();
            $result->ImplantOrderCommunication = ImplantOrderTracking::select([
                'ImplantOrderTracking.ImplantOrderTrackingId',
                'ImplantOrderTracking.ImplantOrderId',
                'ImplantOrderTracking.Content',
                'ImplantOrderTracking.Type',
                'ImplantOrderTracking.CreatedBy',
                'ImplantOrderTracking.CreatedDate',
                's.StaffCode',
                's.FullName',
                's.PrimaryEmail'
                ])
                ->join('in.Staff as s', 's.StaffId', '=', 'ImplantOrderTracking.CreatedBy')
                ->where('ImplantOrderTracking.ImplantOrderId', $result->ImplantOrderId)->get();
            if(!empty($result->ImplantOrderCommunication)){
                foreach($result->ImplantOrderCommunication as $v){
                    if (Helper::isJSON($v->Content ?? '')) {
                        $v->Content = json_decode($v->Content, true);
                    }
                }
            }
            $result->DoctorRecently = $this->getDoctorById($result->ResponsibleStaffId);
        }
        return $results;
    }

    public function createImplantOrder($customerId, $usingDate, $orderType, $implantOrderDetail, $branchId, $note, $responsibleStaffId) {

        try {

            DB::beginTransaction();
            $staffId = Auth::user()['StaffId'] ?? 0;
            $data = [];
            if($usingDate){
                $data = [
                    'CustomerId' => $customerId,
                    'UsingDate' => $usingDate,
                    'OrderType' => $orderType,
                    'Status' => 1,
                    'BranchId' => $branchId,
                    'Note' => json_encode($note, JSON_UNESCAPED_UNICODE),
                    'CreatedDate' => Carbon::now(),
                    'CreatedBy' => $staffId,
                    'UpdatedDate' => Carbon::now(),
                    'UpdatedBy' => $staffId,
                    'ResponsibleStaffId' => $responsibleStaffId
                ];
            }else{
                $data = [
                    'CustomerId' => $customerId,
                    'OrderType' => $orderType,
                    'Status' => 1,
                    'BranchId' => $branchId,
                    'Note' => json_encode($note, JSON_UNESCAPED_UNICODE),
                    'CreatedDate' => Carbon::now(),
                    'CreatedBy' => $staffId,
                    'UpdatedDate' => Carbon::now(),
                    'UpdatedBy' => $staffId,
                    'ResponsibleStaffId' => $responsibleStaffId
                ];
            }
            $implantOrderId = DB::table('pos.ImplantOrder')->insertGetId($data);
    
            if($implantOrderId){
                $dataDetail = [];
                if($implantOrderDetail){
                    foreach($implantOrderDetail as $value){
                        $dataDetail[] = [
                            'ImplantOrderId' => $implantOrderId,
                            'TechnicalSpecificationId' => $value['ImplantTechnicalSpecificationId'],
                            'Quantity' => $value['Quantity'],
                            'CreatedDate' => Carbon::now(),
                            'CreatedBy' => $staffId,
                            'UpdatedDate' => Carbon::now(),
                            'UpdatedBy' => $staffId
                        ];
                    }
                }
                $save = DB::table('pos.ImplantOrderDetail')->insert($dataDetail);
                $implantOrderUpdate = DB::table('pos.ImplantOrder')->where('ImplantOrderId', '=', $implantOrderId)->update([
                    'ImplantOrderCode' => 'IO'.date('Y').date('m').$implantOrderId
                ]);

                if ($save && $implantOrderUpdate) {
                    DB::table('pos.ImplantOrderTracking')->insert([
                        'ImplantOrderId' => $implantOrderId,
                        'Type' => 1,
                        'CreatedDate' => Carbon::now()->ToDatetimeString(),
                        'CreatedBy' => $staffId
                    ]);
                }
            }

            DB::commit();

        } catch(\Exception $e) {

            DB::rollback();
            Log::error("createImplantOrder error", [$e->getMessage()]);
            return false;

        }

        return true;
    }

    public function updateImplantOrder($status, $expectedDeliveryDate, $implantOrderId, $note, $currentUpdatedDate) {

        $order = ImplantOrder::select('*')->where('ImplantOrderId', $implantOrderId)->where('UpdatedDate', '=', $currentUpdatedDate)->first();
        if((!empty($order) && $status <= $order['Status'] || !$order && empty($order)) && $status > 0){
            return false;
        }
        $data = [];
        $staffId = Auth::user()['StaffId'] ?? 0;
        $staff = Staff::select('FullName')->where('StaffId', $staffId)->first();
        $userName = $staff['FullName'];
        $userId = Auth::user()['UserId'] ?? 0;
        $tracking = [
            'ImplantOrderId' => $implantOrderId,
            'CreatedDate' => Carbon::now(),
            'CreatedBy' => $staffId,
            'Type' => 60,
        ];
        $title = 'Thông báo đơn hàng Implant';
        $content = 'Đơn hàng của bạn có sự thay đổi!';

        switch ($status) {
            case 20:
                $title = "Tiếp nhận đơn hàng!";
                $content = "Đơn hàng được tiếp nhận bởi $userName";
                break;
            
            case 30:
                $title = "Đơn hàng đã được giao!";
                $content = "Đơn hàng đã được giao bởi $userName";
                break;
        
            case 40:
                $title = "Từ chối đơn hàng!";
                $content = "Đơn hàng đã bị từ chối bởi $userName";
                break;
    
            case 50:
                $title = "Đơn hàng đã bị từ chối!";
                $content = "Đơn hàng đã bị từ chối bởi $userName";
                break;
                                                    
            default:
                break;
        }
        if($expectedDeliveryDate){
            $data = [
                'Status' => $status,
                'ExpectedDeliveryDate' => $expectedDeliveryDate,
                'UpdatedDate' => Carbon::now(),
                'UpdatedBy' => $staffId
            ];
            $tracking['Type'] = 20;
        }else if($expectedDeliveryDate == '' && $status > 0){
            $data = [
                'Status' => $status,
                'ActualDeliveryDate' => Carbon::now(),
                'UpdatedDate' => Carbon::now(),
                'UpdatedBy' => $staffId
            ];
            $tracking['Type'] = $status;
        }
        try {

            DB::beginTransaction();
            if ($note) {
                $tracking['Content'] = json_encode($note, JSON_UNESCAPED_UNICODE);
            }
            DB::table('pos.ImplantOrderTracking')->insert($tracking);
            
            if(!empty($data)){
                DB::table('pos.ImplantOrder')->where('ImplantOrderId', '=', $implantOrderId)->update($data);
            }
            Log::info("==== START Send Notification Order Implant ===");
            $header = ['Authorization'=>'Bearer ' . JWT_APP_TOKEN];
            $remote = Factory::getRemote();
            $remote->post([
                'form_params' => [
                    "notification[title]" => $title,
                    "notification[message]" => $content,
                    "notification[exprire_date]" => Carbon::now(),
                    "notification[message_type]" => 'normal',
                    "notification[link]" => '',
                    "notification[important]" => 0,
                    "notification[hasStaff]" => true,
                    "notification[user_list]" => $userId,
                    "notification[sender]" => $userId,
                    "notification[type]" => 'OrderImplant',
                    "notification[redirect_link]" => '/pos/ImplantOrderManagement/Detail/' . $implantOrderId
                ]
            ])->to(API_SEND_NOTIFICATION)
               ->execute(true, $header);
            
            $response = $remote->loadVar(true);
            // Log::info("Remote Send Notification Order Implant url:", [API_SEND_NOTIFICATION]);
            // Log::info("Remote Send Notification Order Implant header:", $header);
            Log::info("Remote Send Notification Order Implant data:", [$remote->getResponseMessages()]);
            Log::info("Remote Send Notification Order Implant response", [$response]);
            Log::info("==== END Send Notification Order Implant ===");

            DB::commit();

        } catch(\Exception $e) {

            DB::rollback();
            Log::error("updateImplantOrder error", [$e->getMessage()]);
            return false;

        }

        return true;
    }

    public function listImplantTechnicalSpecification($implantSupplierId, $implantSuppliesId) {

        $results = ImplantTechnicalSpecification::select(['ImplantTechnicalSpecificationId','ImplantTechnicalSpecificationCode','Name','ImplantSuppliesId','ImplantSupplierId','Length','Width','Height','Radius','Unit'])->where('ImplantSuppliesId', $implantSuppliesId)->where('ImplantSupplierId', $implantSupplierId)->where('State', 1)->get();
        return $results ?? [];

    }

    public function listImplantSupplies() {

        $results = ImplantSupplies::select(['ImplantSuppliesId','Name','Status'])->where('Status', 1)->get();
        return $results ?? [];

    }

    public function listImplantSupplier($implantSuppliesId) {

        $results = ImplantMapping::select(['ImplantMapping.ImplantSuppliesId','ImplantMapping.ImplantSupplierId','ir.Name'])->join('pos.ImplantSupplier as ir', 'ir.ImplantSupplierId', '=', 'ImplantMapping.ImplantSupplierId')->where('ir.Status', 1)->where('ImplantMapping.ImplantSuppliesId', $implantSuppliesId)->get();
        return $results ?? [];
    
    }

    public function getDoctorById($id)
    {
        return Staff::select('StaffId', 'FullName', 'StaffCode')->where('StaffId', $id)->first();
    }

    public function getDoctorRecently($customerId)
    {
        $orderId = [];
        $doctorId = 0;
        $order = DB::table('pos.Order')->select('OrderId')
            ->where('CustomerId', $customerId)
            ->orderBy('UpdatedAt', 'DESC')
            ->get();
        if ($order && !empty($order)) {
            $orderId = $order->pluck('OrderId')->toArray();
        }
        if ($orderId && !empty($orderId)) {
            $orderChanging = DB::table('pos.OrderChanging')->select('ChangedBy')->where('OrderId', $orderId)->orderBy('ChangedAt', 'DESC')->limit(1)->get();
            if ($orderChanging && !empty($orderChanging)) {
                $doctorId = $orderChanging->pluck('ChangedBy')->toArray();
            }
            $results = Doctor::select([
                'Doctor.StaffId',
                's.StaffCode',
                's.FullName'
            ])
            ->join('in.Staff as s', 's.StaffId', '=', 'Doctor.StaffId')
            ->where('s.State', 1)
            ->where('Doctor.State', 1)
            ->where('Doctor.StaffId', $doctorId)
            ->first();
            return $results;
        }
        return [];
    }

    public function detailImplantOrder ($implantOrderId)
    {
        $query = $this->_model->newQuery();
        $query->select([
            'ImplantOrderId',
            'CreatedDate',
            'CreatedBy',
            'ImplantOrderCode',
            'UsingDate',
            'OrderType',
            'UpdatedDate',
            'UpdatedBy',
            'Note',
            'CustomerId',
            'Status',
            'ExpectedDeliveryDate',
            'ActualDeliveryDate',
            'BranchId',
            'ResponsibleStaffId'
        ]);

        $query->with(['createdByStaff' => function ($subQuery) {
            $subQuery->select('StaffId', 'FullName', 'StaffCode');
        }]);
        $query->with(['updatedByStaff' => function ($subQuery) {
            $subQuery->select('StaffId', 'FullName', 'StaffCode');
        }]);
        $query->with(['orderByCustomer' => function ($subQuery) {
            $subQuery->select('CustomerId', 'CustomerCode', 'FullName', 'Gender', 'Birthday');
        }]);
        $query->with(['byBranch' => function ($subQuery) {
            $subQuery->select('BranchId', 'BranchCode', 'Name');
        }]);
        $query->where('ImplantOrderId', '=', $implantOrderId);

        $result = $query->first();
        $result->DoctorRecently = $this->getDoctorById($result->ResponsibleStaffId);
        if (!$result || empty($result)) {
            return [];
        }

        if (Helper::isJSON($result->Note ?? '')) {
            $result->Note = json_decode($result->Note, true);
        }
        $result->ImplantOrderDetail = ImplantOrderDetail::select([
            'ImplantOrderDetail.TechnicalSpecificationId',
            'ImplantOrderDetail.ImplantOrderDetailId',
            'ImplantOrderDetail.Quantity',
            'its.Name as ImplantTechnicalSpecificationName',
            'its.ImplantSuppliesId',
            'its.ImplantSupplierId',
            'its.Length',
            'its.Width',
            'its.Height',
            'its.Radius',
            'its.Unit',
            'isr.Name as ImplantSupplierName',
            'iss.Name as ImplantSuppliesName',
            'its.ImplantTechnicalSpecificationCode'
            ])
            ->join('pos.ImplantTechnicalSpecification as its', 'its.ImplantTechnicalSpecificationId', '=', 'ImplantOrderDetail.TechnicalSpecificationId')
            ->join('pos.ImplantSupplier as isr', 'isr.ImplantSupplierId', '=', 'its.ImplantSupplierId')
            ->join('pos.ImplantSupplies as iss', 'iss.ImplantSuppliesId', '=', 'its.ImplantSuppliesId')
            ->where('ImplantOrderDetail.ImplantOrderId', $implantOrderId)
            ->where('isr.Status', 1)
            ->where('iss.Status', 1)->get();
        $result->ImplantOrderTracking = ImplantOrderTracking::select([
            'ImplantOrderTracking.ImplantOrderTrackingId',
            'ImplantOrderTracking.ImplantOrderId',
            'ImplantOrderTracking.Content',
            'ImplantOrderTracking.Type',
            'ImplantOrderTracking.CreatedBy',
            'ImplantOrderTracking.CreatedDate',
            's.StaffCode',
            's.FullName',
            's.PrimaryEmail'
            ])
            ->join('in.Staff as s', 's.StaffId', '=', 'ImplantOrderTracking.CreatedBy')
            ->where('ImplantOrderTracking.ImplantOrderId', $implantOrderId)->get();
        if(!empty($result->ImplantOrderTracking)){
            foreach($result->ImplantOrderTracking as $v){
                if (Helper::isJSON($v->Content ?? '')) {
                    $v->Content = json_decode($v->Content, true);
                }
            }
        }
        return $result;
    }

    public function getBranchDefault ($customerId)
    {
        $result = Appointment::select(['Appointment.AtBranchId'])->join('in.Branch as b', 'b.BranchId', '=', 'Appointment.AtBranchId')->where('Appointment.CustomerId', $customerId)->where('b.State', 1)->orderBy('Appointment.AppointmentId', 'DESC')->first();

        $branchId = NULL;
        if($result){
            $branchId = $result->AtBranchId;
        }
        return $branchId;
    }
}