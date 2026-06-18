<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\ImplantOrderRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ImplantOrderController extends Controller
{
    public function listByCustomer(Request $request) {
        $validator = Validator::make($request->all(), [
            'CustomerId'    => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $customerId = $request->input('CustomerId');
        $lmstart = $request->input('lmstart', 0);
        $limit = $request->input('limit', 20);

        try {
            
            $implantOrderRepository = new ImplantOrderRepository;
            $result = $implantOrderRepository->listImplantOrderAll($customerId, $lmstart, $limit, '', '', '', '', '');

            return $this->json([$this->formatPagination('ListImplantOrderByCustomer',$result)]);

        } catch (\Exception $e) {

            return $this->json([$this->formatPagination('ListImplantOrderByCustomer',[])]);

        }
    }

    public function create(Request $request) {
        $validator = Validator::make($request->all(), [
            'CustomerId'                            => 'required|numeric',
            'OrderType'                             => 'required|numeric',
            'ImplantOrderDetail'                    => 'required|array',
            'ResponsibleStaffId'                    => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'CIOR0004', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $customerId = $request->input('CustomerId');
        $usingDate = $request->input('UsingDate');
        $orderType = $request->input('OrderType');
        $implantOrderDetail = $request->input('ImplantOrderDetail');
        $branchId = $request->input('BranchId');
        $responsibleStaffId = $request->input('ResponsibleStaffId');
        $note = $request->input('Note');
        if(!$branchId){
            $branchId = 0;
        }

        try {
            
            $implantOrderRepository = new ImplantOrderRepository;
            $result = $implantOrderRepository->createImplantOrder($customerId, $usingDate, $orderType, $implantOrderDetail, $branchId, $note, $responsibleStaffId);

            if ($result) {
                $this->addMessage("Tạo đơn hàng implant thành công.", 'CIOR0001', self::$SUCCESS);
                return $this->json(true, 'bool');
            }
            $this->addMessage("Tạo đơn hàng implant không thành công.", 'CIOR0002', self::$ERROR);
            return $this->json(false, 'bool');

        } catch (\Exception $e) {

            $this->addMessage("Tạo đơn hàng implant không thành công.", 'CIOR0003', self::$ERROR);
            return $this->json(false, 'bool');

        }
    }

    public function update(Request $request) {
        $validator = Validator::make($request->all(), [
            'ImplantOrderId'    => 'required|numeric',
            'Status'              => 'required|numeric',
            'CurrentUpdatedDate'              => 'required|date',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $implantOrderId = $request->input('ImplantOrderId');
        $status = $request->input('Status');
        $note = $request->input('Note');
        $expectedDeliveryDate = $request->input('ExpectedDeliveryDate');
        $currentUpdatedDate = $request->input('CurrentUpdatedDate');

        try {
            
            $implantOrderRepository = new ImplantOrderRepository;
            $result = $implantOrderRepository->updateImplantOrder($status, $expectedDeliveryDate, $implantOrderId, $note, $currentUpdatedDate);

            if ($result) {
                $this->addMessage("Cập nhật đơn hàng implant thành công.", 'UIOR0001', self::$SUCCESS);
                return $this->json(true, 'bool');
            }
            $this->addMessage("Cập nhật đơn hàng implant không thành công hoặc trạng thái đã được người khác thay đổi.", 'UIOR0002', self::$ERROR);
            return $this->json(false, 'bool');

        } catch (\Exception $e) {

            $this->addMessage("Cập nhật đơn hàng implant không thành công hoặc trạng thái đã được người khác thay đổi.", 'UIOR0003', self::$ERROR);
            return $this->json(false, 'bool');

        }
    }

    public function list(Request $request) {

        $customerId = $request->input('CustomerId');
        $lmstart = $request->input('lmstart', 0);
        $limit = $request->input('limit', 20);
        $staffId = $request->input('StaffId');
        $branchId = $request->input('BranchId');
        $keyword = $request->input('Keyword');

        try {
            
            $implantOrderRepository = new ImplantOrderRepository;
            $result = $implantOrderRepository->listImplantOrderAll($customerId, $lmstart, $limit, '', '', $keyword, $branchId, $staffId);

            return $this->json([$this->formatPagination('ListImplantOrder',$result)]);

        } catch (\Exception $e) {
            Log::error("ListOrderHistory errors", [$e->getMessage()]);
            return $this->json([$this->formatPagination('ListImplantOrder',[])]);

        }
    }

    public function listHistory(Request $request) {

        $validator = Validator::make($request->all(), [
            'FromDate'    => 'required',
            'ToDate'              => 'required',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $customerId = $request->input('CustomerId');
        $lmstart = $request->input('lmstart', 0);
        $limit = $request->input('limit', 20);
        $staffId = $request->input('StaffId');
        $branchId = $request->input('BranchId');
        $keyword = $request->input('Keyword');
        $fromDate = $request->input('FromDate');
        $toDate = $request->input('ToDate');

        try {
            
            $implantOrderRepository = new ImplantOrderRepository;
            $result = $implantOrderRepository->listImplantOrderAll($customerId, $lmstart, $limit, $fromDate, $toDate, $keyword, $branchId, $staffId);

            return $this->json([$this->formatPagination('ListOrderHistory',$result)]);

        } catch (\Exception $e) {
            Log::error("ListOrderHistory errors", [$e->getMessage()]);
            return $this->json([$this->formatPagination('ListOrderHistory',[])]);

        }
    }

    public function listImplantTechnicalSpecification(Request $request) {

        $implantSupplierId = $request->input('ImplantSupplierId');
        $implantSuppliesId = $request->input('ImplantSuppliesId');
        $customerId = $request->input('CustomerId');

        $data = (object) [];
        try {
            
            $implantOrderRepository = new ImplantOrderRepository;

            $data->ListImplantSupplies = $implantOrderRepository->listImplantSupplies();
            $data->ListImplantTechnicalSpecification = $implantOrderRepository->listImplantTechnicalSpecification($implantSupplierId, $implantSuppliesId);
            $data->ListImplantSupplier = $implantOrderRepository->listImplantSupplier($implantSuppliesId);
            $data->DoctorRecently = $implantOrderRepository->getDoctorRecently($customerId);
            $data->BranchId = $implantOrderRepository->getBranchDefault($customerId);

            return $this->json([$this->formatData('ListImplantTechnicalSpecification',$data)]);

        } catch (\Exception $e) {
            Log::error("ListImplantOrderByCustomer errors", [$e->getMessage()]);
            return $this->json([$this->formatData('ListImplantTechnicalSpecification',[])]);

        }
    }

    public function detailImplantOrder (Request $request)
    {
        $implantOrderId = $request->input('ImplantOrderId');

        try {
            
            $implantOrderRepository = new ImplantOrderRepository;
            $result = $implantOrderRepository->detailImplantOrder($implantOrderId);

            return $this->json([$this->formatData('DetailImplantOrder',$result)]);

        } catch (\Exception $e) {

            return $this->json([$this->formatData('DetailImplantOrder',[])]);

        }
    }
    
}