<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\CustomerBankRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CustomerBankController extends Controller
{
    public function addCustomerBank(Request $request) {
        $validator = Validator::make($request->all(), [
            'CustomerId'    => 'required|numeric',
            'BankId'        => 'required|numeric',
            'BankAccNumber'    => 'required',
            'BankAccName'      => 'required|string'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $customerId = $request->input('CustomerId');
        $bankId = $request->input('BankId');
        $bankAccNumber = $request->input('BankAccNumber');
        $bankAccName = $request->input('BankAccName');

        try {
            
            $customerBankRepository = new CustomerBankRepository;
            $result = $customerBankRepository->addCustomerBank($customerId,$bankId,$bankAccNumber,$bankAccName);

            if($result){
                $this->addMessage("Tạo thông tin ngân hàng thành công!", 'ACB0001', self::$SUCCESS);
                return $this->json(true, 'bool');
            }else{
                $this->addMessage("Tạo thông tin ngân hàng không thành công!", 'ACB0003', self::$ERROR);
                return $this->json(false, 'bool');
            }

        } catch (\Exception $e) {

            DB::rollback();
            $this->addMessage("Tạo thông tin ngân hàng không thành công!", 'ACB0002', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    public function listCustomerBank(Request $request) {
        $validator = Validator::make($request->all(), [
            'CustomerId'    => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $customerId = $request->input('CustomerId');

        try {
            
            $customerBankRepository = new CustomerBankRepository;
            $result = $customerBankRepository->listCustomerBank($customerId);

            return $this->json([$this->formatData('ListCustomerBank',$result)]);

        } catch (\Exception $e) {

            return $this->json([$this->formatData('ListCustomerBank error',[])]);

        }
    }

    public function listBank() {
        try {
            
            $customerBankRepository = new CustomerBankRepository;
            $result = $customerBankRepository->listBank();

            return $this->json([$this->formatData('ListBank',$result)]);

        } catch (\Exception $e) {

            return $this->json([$this->formatData('ListBank error',[])]);

        }
    }

    public function deleteCustomerBank(Request $request) {
        $validator = Validator::make($request->all(), [
            'CustomerBankId'    => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $customerBankId = $request->input('CustomerBankId');

        try {
            
            $customerBankRepository = new CustomerBankRepository;
            $checkResult = $customerBankRepository->find($customerBankId);
            if(!$checkResult){
                $this->addMessage("Không có dữ liệu hoặc dữ liệu đã bị xoá!", 'DCB0004', self::$ERROR);
                return $this->json(false, 'bool');
            }
            $result = $customerBankRepository->deleteCustomerBank($customerBankId);

            if($result){
                $this->addMessage("Xoá thông tin ngân hàng thành công!", 'DCB0001', self::$SUCCESS);
                return $this->json(true, 'bool');
            }else{
                $this->addMessage("Xoá thông tin ngân hàng không thành công!", 'DCB0003', self::$ERROR);
                return $this->json(false, 'bool');
            }

        } catch (\Exception $e) {

            $this->addMessage("Xoá thông tin ngân hàng không thành công!", 'DCB0002', self::$ERROR);
            return $this->json(false, 'bool');

        }
    }

    public function updateCustomerBank(Request $request) {
        $validator = Validator::make($request->all(), [
            'CustomerBankId'    => 'required|numeric',
            'BankId'        => 'required|numeric',
            'BankAccNumber'    => 'required',
            'BankAccName'      => 'required|string'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $customerBankId = $request->input('CustomerBankId');
        $bankId = $request->input('BankId');
        $bankAccNumber = $request->input('BankAccNumber');
        $bankAccName = $request->input('BankAccName');

        try {
            
            $customerBankRepository = new CustomerBankRepository;
            $checkResult = $customerBankRepository->find($customerBankId);
            if(!$checkResult){
                $this->addMessage("Không có dữ liệu hoặc dữ liệu đã bị xoá!", 'UCB0004', self::$ERROR);
                return $this->json(false, 'bool');
            }
            $result = $customerBankRepository->updateCustomerBank($customerBankId,$bankId,$bankAccNumber,$bankAccName);

            if($result){
                $this->addMessage("Chỉnh sửa thông tin ngân hàng thành công!", 'UCB0001', self::$SUCCESS);
                return $this->json(true, 'bool');
            }else{
                $this->addMessage("Chỉnh sửa thông tin ngân hàng không thành công.", 'UCB0002', self::$ERROR);
                return $this->json(false, 'bool');
            }

        } catch (\Exception $e) {

            $this->addMessage("Chỉnh sửa thông tin ngân hàng không thành công.", 'UCB0003', self::$ERROR);
            return $this->json(false, 'bool');

        }
    }
}