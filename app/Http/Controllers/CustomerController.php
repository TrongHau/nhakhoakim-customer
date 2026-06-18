<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\CustomerRepository;
use App\Repositories\CustomerLevelRepository;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Libs\ApiProcess;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use App\Imports\InsuranceReceiptImport;
use App\Repositories\TreatmentRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Libs\Helper;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerController extends Controller
{
    /**
     * @var CustomerRepository
     */
    protected $customerRepo;

    public function __construct(CustomerRepository $customerRepo) {
        parent::__construct();
        $this->customerRepo = $customerRepo;
    }

    /**
     * Receive CustomerId
     * Check Custome is NKK's staff or NKK's relative staff.
     * Return value 0, 1 and 2
     */
    public function updateCustomerPattern(Request $request)
    {
        /**Define result Responses
        * Value 0, Customer isn't staff or relative staff NKK
        * Value 1, Customer is NKK's staff 
        * Value 2, Customer is NKK's relative staff
        */
        $result = [
            "code" => 400,
            "value" => Config::get('constants.response.checkCustomerIsStaff.not'),
            "message" => "CustomerId chưa đúng. Yêu cầu phải là các ký tự số"
        ];
        $customerId = $request->input('customerId');
        if(isset($customerId) && !empty($customerId) && \is_numeric($customerId) && $customerId > 0){
            $customerRepository = new CustomerRepository;
            $customer = $customerRepository->find($customerId);
            //Check Customer isEmpty
            if(!empty($customer)){
                //Get list phones
                $phoneNumbers = $customer->phones;
                
                //Set Flag staff NKK
                $isStaffNKK = false;

                if(!$phoneNumbers->isEmpty()){
                    /**
                     * Convert array Phone to string phone with comma
                     * Example: ['0974860889','0974860888','097486087'] to 0974860889, 0974860888, 0974860887
                     */
                    $arrPhone = [];
                    foreach ($phoneNumbers as $item) {
                        array_push($arrPhone,$item->PhoneNumber);
                    }
                    $strPhone = implode(",",$arrPhone);

                    if($this->apiStaffHRMWithPhone($strPhone)){
                        $result = [
                            "code" => 200,
                            "value" => Config::get('constants.response.checkCustomerIsStaff.isStaff'),
                            "message" => "CustomerId ".$customerId." có số điện thoại ".$strPhone." là nhân viên NKK."
                        ];
                        $isStaffNKK = true;
                    }
                    
                   
                }
                if(!$isStaffNKK){
                     //Check NKK's relative staff
                    $customerRelationships = $customer->relationships;
                    if(!$customerRelationships->isEmpty()){
                        $arrPhone = [];
                        
                        foreach ($customerRelationships as $customeRelationship) {
                            $phoneNumbers = $customeRelationship->phones;
                            if(!$phoneNumbers->isEmpty()){
                                foreach ($phoneNumbers as $item) {
                                    array_push($arrPhone,$item->PhoneNumber);
                                }
                            }
                        }
                        
                        $strPhone = implode(",",$arrPhone);
                        if($this->apiStaffHRMWithPhone($strPhone)){
                            $result = [
                                "code" => 200,
                                "value" => Config::get('constants.response.checkCustomerIsStaff.isStaffRelationship'),
                                "message" => "CustomerId ".$customerId." có liên hệ với các số điện thoại ".$strPhone." là người thân nhân viên NKK."
                            ];
                            $isStaffNKK = true;
                        }
                    }
                }
                if($isStaffNKK){
                    if(isset($result['value']) && $result['value'] > 0){
                        $customerPattern = $result['value'];
                        $customer->CustomerPattern = $customerPattern;
                        if($customer->save()){
                            $result['message'] = "Đã cập nhật thành công. ".$result['message'];
                        }else{
                            $result['message'] = "Đã cập nhật THẤT BẠI. ".$result['message'];
                        }
                    }else{
                        $result['message'] = "KHÔNG TÌM THẤY PATTERN CỦA CUSTOMER. ".$result['message'];
                    }
                }
                if(!$isStaffNKK){
                    //Update case remove relationship or staff
                    if($customer->CustomerPattern != 0){
                        $customerPattern = Config::get('constants.response.checkCustomerIsStaff.not');
                        $customer->CustomerPattern = $customerPattern;
                        $result['code'] =  200;
                        if($customer->save()){
                            $result['message'] = "Đã cập nhật thành công khi chuyển CustomerPattern về 0";
                        }else{
                            $result['message'] = "Đã cập nhật THẤT BẠI khi chuyển CustomerPattern về 0 ";
                        }
                    }else{
                        $result['code'] =  204;
                        $result['message'] = "Không tìm thấy Customer là nhân viên hoặc người thân NKK";
                    }
                }
                
            }else{
                $result['code'] =  204;
                $result['message'] = "Không tìm thấy thông tin Customer";
            }
        }
        if(env('APP_DEBUG')){
            Log::debug('Debug information Request and Result in action updateCustomerPattern',[
                'request' => $request->all(),
                'result' => $result
            ]);
        }
        return $this->json([$this->formatData('updateCustomerPattern',$result)]);
    }


    /**
     * @param Request $request
     * return json with type, code and message
     * Check customer level with customerId, amount and action.
     * Functions plus or minus amount with totalAmount
     */
    public function updateCustomerLevel(Request $request)
    {
        //Define reponse result variable when return
        $result = [
            "code" => 400,
            "message" => "CustomerId chưa đúng. Yêu cầu phải là các ký tự số"
        ];

        //Get parameters tranfer in method post
        $customerId = $request->input('customerId');

        //Check variable accept rule
        if(!$customerId || empty($customerId) || !is_numeric($customerId) || strlen($customerId) == 0){
            if(env('APP_DEBUG')){
                Log::debug('CustomerId is empty in request', $request->all());
            }
            return $this->json([$this->formatData('updateCustomerLevel',$result)]);
        }
        //Declar variable reponsitory
        $customerRepository = new CustomerRepository();
        $customerLevelRepository = new CustomerLevelRepository();

        //Get and check customer is exits
        $customer = $customerRepository->find($customerId);
        if(!empty($customer)){
            //Get Receipts and Expenditure conditions by CustomerId
            $customerReceipts = $customerRepository->getReceipts($customerId);
            $customerExpenditures = $customerRepository->getExpenditures($customerId);

            //Plus total receipts and expenditures
            $totalReceipts = 0;
            $totalExpenditures = 0;
            if(!$customerReceipts->isEmpty()){
                foreach ($customerReceipts as $receipt){
                    if($receipt->TotalAmount){
                        $totalReceipts += $receipt->TotalAmount;
                    }
                }
            }
            if(!$customerExpenditures->isEmpty()){
                foreach ($customerExpenditures as $expenditure){
                    if($expenditure->Amount){
                        $totalExpenditures += $expenditure->Amount;
                    }
                }
            }

            //Check totalReceips and totalExpenditures
            if($totalReceipts >= 0 && $totalExpenditures >= 0 && $totalReceipts > $totalExpenditures){
                $totalAmount = $totalReceipts - $totalExpenditures;
            }else{
                $totalAmount = 0;
            }
            //Get all Level in CustomerLevel and convert to array
            $customerLevels = $customerLevelRepository->all()->sortByDesc(function ($level,$index){
                return $level->Priority;
            })
            ->sortByDesc(function ($level,$index){
                return $level->Amount;
            })->toArray();
            //Check level customer
            $result['code'] = 200;

            //Check condition and return level code
            $maxAmount = PHP_INT_MAX ;
            if(current($customerLevels) && isset(current($customerLevels)['Amount'])){
                $minAmount = current($customerLevels)['Amount'];
            }else{
                $minAmount = 1;
            }
            $customerLevel = 0;
            while ( $level = current($customerLevels)){
                if($totalAmount >= $minAmount && $totalAmount < $maxAmount){
                    $customerLevel = $level['CustomerLevelId'];
                    break;
                }
                $maxAmount = $level['Amount'];
                $nextLevel = next($customerLevels);
                if(isset($nextLevel) && !empty($nextLevel) && $nextLevel['Amount'] > 0){
                    $minAmount = $nextLevel['Amount'];
                }else{
                    $minAmount = 1;
                }
                if($maxAmount <= $minAmount){
                    break;
                }
            }

            //Check condition customer used any service at NKK
            if($customerLevel == 0 && $totalReceipts > 0){
                foreach ($customerLevels as $level){
                    if($level['Code'] == 'KIM'){
                        $customerLevel = $level['CustomerLevelId'];
                    }
                }
            }
            //Check condition customer have the first check-in or have the fist appointment
            $appointments = $customer->appointments;
            if(!$appointments->isEmpty()){
                $appointmentStatus = [];
                foreach ($appointments as $appointment){
                    if(!empty($appointment) && isset($appointment->AppointmentStatusId)){
                        $appointmentStatus[] = $appointment->AppointmentStatusId;
                    }
                }
                if(count($appointmentStatus) > 0){
                    $maxLevel = max($appointmentStatus);
                    if($maxLevel >= 21 && $customerLevel == 0){
                        foreach ($customerLevels as $level){
                            if($level['Code'] == 'POT'){
                                $customerLevel = $level['CustomerLevelId'];
                            }
                        }
                    }
                    if($maxLevel <= 11  && $customerLevel == 0){
                        foreach ($customerLevels as $level){
                            if($level['Code'] == 'PRO'){
                                $customerLevel = $level['CustomerLevelId'];
                            }
                        }
                    }
                }
            }
            //Check condition customer have the fist appointment
            //Update CustomerLevel into table Customer
            $customer->CustomerLevelId = $customerLevel;
            $customer->UpdatedAt = strtotime('now');
            if($customer->save()){
                $result['level'] = $customerLevel;
                $result['message'] = 'Đã cập nhật thành công';
            }else{
                $result['message'] = 'Đã có lỗi trong quá trình cập nhật dữ liệu cho Customer '.$customerId.' với CustomerLevelId '.$customerLevel;
            }
            //Check conditions is true then show more information
            if(env('APP_DEBUG')){
                $result['totalAmount'] = $totalAmount;
                $result['totalReceipts'] = $totalReceipts;
                $result['totalExpenditures'] = $totalExpenditures;
                Log::debug("CustomerId ".$customerId." have result in action updateCustomerLevel", [
                    'request' => $request->all(),
                    'result' => $result
                ]);
            }
        }else{
            $result['code'] =  204;
            $result['message'] = "Không tìm thấy thông tin Customer";
            if(env('APP_DEBUG')){
                Log::debug("CustomerId ".$customerId." is not data in database", $request->all());
            }
        }
        return $this->json([$this->formatData('updateCustomerLevel',$result)]);
    }

    private function apiStaffHRMWithPhone($phoneNumber)
    {
        //Check string phone and get information from API
        if(\strlen($phoneNumber) > 0){
            //Check customerID is staff NKK
            //Get URI API from constants.php
            $apiURI = Config::get('constants.api.API_HR_GET_INFO_STAFF_BY_PHONE');
            $body['Phone'] = $phoneNumber;
            $remote = new ApiProcess();
            $remote->request('module.views.data')->from($apiURI)->where($body)->execute();
            $response = $remote->loadVar(false);
            if(!empty($response) && count($response) > 0){
                foreach ($response as $staff) {
                    $dataStaff = $staff->data;
                    //Check State == 1, if State isn't equals 1 then Staff don't work in NKK
                    if(isset($dataStaff->State) && !empty($dataStaff->State) && $dataStaff->State == 1){
                        //Return with code and value
                        return true;
                        break;
                    }
                }
            }
        }
        
        return false;
    }

    public function importInsuranceReceipt(Request $request) {
        
        $validator = Validator::make($request->all(), [
            'File' => 'required'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $file = $request->file('File');
        try {

            DB::beginTransaction();
            $import = new InsuranceReceiptImport;
            Excel::import($import, $file);
            $errors = $import->getErrors();
            if ($errors->any()) {
                DB::rollback();
                $result = [
                    'code' => false,
                    'message' => $errors->get('IPR0001')[0]
                ];
                $this->addMessage($errors->get('IPR0001')[0], 'IPR0002', self::$ERROR);
                return $this->json([$this->formatData('importInsuranceReceipt', $result)]);
            }

            DB::commit();
            $result = [
                'code' => true,
                'message' => 'Import phiếu thu bảo hiểm thành công.'
            ];
            $this->addMessage("Import phiếu thu bảo hiểm thành công.", 'IPR0001', self::$SUCCESS);
            return $this->json([$this->formatData('importInsuranceReceipt', $result)]);

        } catch (\Exception $e) {
            DB::rollback();
            $result = [
                'code' => false,
                'message' => 'Import phiếu thu bảo hiểm không thành công.'
            ];
            $this->addMessage("Import phiếu thu bảo hiểm không thành công.", 'IPR0002', self::$ERROR);
            return $this->json([$this->formatData('importInsuranceReceipt', $result)]);
        }
    }

    public function listMoneyCollector(Request $request) {
        $validator = Validator::make($request->all(), [
            'CustomerId'    => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $customerId = $request->input('CustomerId');

        try {
            
            $customerRepository = new CustomerRepository;
            $result = $customerRepository->listMoneyCollector($customerId);

            return $this->json([$this->formatData('ListMoneyCollector',$result)]);

        } catch (\Exception $e) {

            return $this->json([$this->formatData('ListMoneyCollector error',[])]);

        }
    }

    public function changePhoneNumberFromZalo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'OldPhoneNumber'    => 'required|numeric',
            'NewPhoneNumber'    => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        if (app()->environment() == 'production') {
            $this->addMessage("Chức năng này chỉ dùng cho hệ thống Testing, UAT", self::$ERROR, 'ERR001');
            return $this->json(false, 'bool');
        }

        $oldPhoneNumber = $request->input('OldPhoneNumber');
        $newPhoneNumber = $request->input('NewPhoneNumber');
        $customerRepository = new CustomerRepository;
        $res = $customerRepository->changePhoneNumberFromZalo($oldPhoneNumber, $newPhoneNumber);
        if ($res) {
            $this->addMessage("Đã cập nhật số điện thoại thành công.", 'CPN001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage("Cập nhật số điện thoại không thành công.", 'CPN002', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function listPhoneAreaCode()
    {
        try {
            
            $customerRepository = new CustomerRepository;
            $results = $customerRepository->listPhoneAreaCode();
            if($results){
                foreach($results as $value){
                    $value->CountryPhoneCode = "(+". (int)$value->CountryPhoneNumber .")";
                }
            }

            return $this->json([$this->formatData('ListPhoneAreaCode',$results)]);

        } catch (\Exception $e) {

            return $this->json([$this->formatData('ListPhoneAreaCode error',[])]);
        }
    }
    public function getServiceDeleteHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CustomerId'    => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $customerId = $request->input('CustomerId');
        $lmstart = $request->input('lmstart') ?? 0;
        $limit = $request->input('limit') ?? 20;

        try {
            
            $data = $this->customerRepo->getServiceDeleteHistory($customerId, $lmstart, $limit);

            $results[] = $this->formatDataPaginationByStore('ServiceDeleteHistory', $data, 'Grid');
            return $this->json($results, 'views');

        } catch (\Exception $e) {

            return $this->json([$this->formatData('ServiceDeleteHistory',[])]);

        }
    }
    
    public function getUrgentContactDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CustomerId'    => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $detail = $this->customerRepo->getUrgentDetail($request->input('CustomerId'));
        $result[] = $this->formatData('UrgentDetail', $detail);

        return $this->json($result);
    }

    public function updateUrgentContact(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CustomerId'    => 'required|numeric',
            'UrgentContactName' => 'required',
            'UrgentContactPhone' => 'required',
            'UrgentContactAddress' => 'required',
            'UrgentRelationshipId' => 'required|numeric',
            'UrgentIsAdult' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $res = $this->customerRepo->updateUrgentContact($request->input('CustomerId'), $request->all());
        if ($res) {
            $this->addMessage("Cập nhật thông tin người thân/giám hộ thành công.", 'SUCC001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage("Cập nhật thông tin người thân/giám hộ thất bại", "ERROR001", self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function getCustomerDebtSummary(Request $request)
    {
        $data = $this->customerRepo->getCustomerDebtSummary($request->input('BranchId'));
        $result[] = $this->formatData('CustomerDebtSummary', $data);

        return $this->json($result);
    }

    public function getCustomerDebtDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId'    => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $detail = $this->customerRepo->getCustomerDebtDetail($request->all());
        $result[] = $this->formatDataPaginationByStore('CustomerDebtDetail', $detail);

        return $this->json($result);
    }

    public function getReceiptAdjustTracking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lmstart'    => 'required|numeric',
            'limit'    => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $customerRepository = new CustomerRepository;
        $data = $customerRepository->getReceiptAdjustTracking($request->all());
        $result[] = $this->formatPagination('ReceiptAdjustTracking', $data);

        return $this->json($result);
    }

    public function getCustomerByIdNumber(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CustomerIdNumber'    => 'required',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $detail = $this->customerRepo->getCustomerByIdNumber($request->input('CustomerIdNumber'));
        $result[] = $this->formatData('CustomerByIdNumber', $detail);

        return $this->json($result);
    }

    public function createOrderDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Treatment'    => 'required|array',
            'AddServices' => 'required|array',
            'OrderId' => 'numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $addServices = $request->input('AddServices');
        $treatment = $request->input('Treatment');
        $orderId = $request->input('OrderId') ?? 0;
        $note = $request->input('Note') ?? '';
        if(!$orderId){
            $orderId = 0;
        }
        try {
            $staffId = Auth::user()['StaffId'];

            // Kiểm tra IpAddress nhưng loại Doctor QC
            $ipAddress = Helper::getClientIp();
            $branchId = $this->customerRepo->checkIpAddress($ipAddress);
            if (!$branchId && $staffId != 3048) {
                $this->addMessage("Địa chỉ IP không hợp lệ.", "COD0001", self::$ERROR);
                return $this->json(false, 'bool');
            }
            // Kiểm tra nhân viên check-in trên hệ thống nhưng loại Doctor QC
            $timeKeeper = $this->customerRepo->checkTimeKeeper($staffId);
            if (!$timeKeeper && $staffId != 3048) {
                $this->addMessage("Nhân viên chưa thực hiện Checkin ngày hôm nay.", "COD0005", self::$ERROR);
                return $this->json(false, 'bool');
            }

            // Kiểm tra dịch vụ và răng có lên trùng hay không
            $checkService = $this->customerRepo->checkServiceAndTooth($treatment['TreatmentId'], $addServices);
            if ($checkService) {
                if(isset($checkService->TreatmentMedicalProcedureStatusId) && $checkService->TreatmentMedicalProcedureStatusId == 2){
                    $this->addMessage("Không thể thêm dịch vụ đã tồn tại và chưa hoàn thành, vui lòng tải lại trang để cập nhật trạng thái.", "COD0009", self::$ERROR);
                    return $this->json(false, 'bool');
                } else {
                    $this->addMessage(" Dịch vụ và răng của khách hàng bị trùng: ".$checkService->ServiceName." - ".$checkService->AnatomyBodyPartItemName.". Vui lòng kiểm tra lại.", "COD0008", self::$ERROR);
                    return $this->json(false, 'bool');
                }
            }

            // Kiểm tra không phải là BS thì không được phép thêm dịch vụ có bước điều trị
            $checkDoctor = $this->customerRepo->checkStaffDoctor($staffId, $addServices);
            if(!$checkDoctor){
                $this->addMessage("QLPK/TVV chỉ được lên dịch vụ bán hàng.", "COD0010", self::$ERROR);
                return $this->json(false, 'bool');
            }

            $results = $this->customerRepo->createOrderDetail($treatment, $addServices, $orderId, $ipAddress, $branchId, $note);
            if($results){
                $this->customerRepo->saveCustomerDoctor($treatment['CustomerId'] ?? 0, $staffId);
                foreach ($results as $key => $result) {
                    if ($result->Result == 1) {
                        $this->addMessage($result->ResultMessage, "COD0002", self::$SUCCESS);
                        return $this->json(true, 'bool');
                    } else {
                        $this->addMessage($result->ResultMessage, "COD0005", self::$ERROR);
                        return $this->json(false, 'bool');
                    }
                }
            }
            $this->addMessage("Lưu dịch vụ không thành công!", "COD0003", self::$ERROR);
            return $this->json(false, 'bool');

        } catch (\Exception $e) {

            $this->addMessage("Lưu dịch vụ không thành công!", "COD0004", self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    public function deleteOrderDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'OrderDetails' => 'required|array',
            'TreatmentId' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $orderDetails = $request->input('OrderDetails');
        $treatmentId = $request->input('TreatmentId');

        try {

            $staffId = Auth::user()['StaffId'];
            // Kiểm tra IpAddress nhưng loại Doctor QC
            $ipAddress = Helper::getClientIp();
            $branchId = $this->customerRepo->checkIpAddress($ipAddress);
            if (!$branchId && $staffId != 3048) {
                $this->addMessage("Địa chỉ IP không hợp lệ.", "COD0001", self::$ERROR);
                return $this->json(false, 'bool');
            }
            // Kiểm tra nhân viên check-in trên hệ thống nhưng loại Doctor QC
            $timeKeeper = $this->customerRepo->checkTimeKeeper($staffId);
            if (!$timeKeeper && $staffId != 3048) {
                $this->addMessage("Nhân viên chưa thực hiện Checkin ngày hôm nay.", "DOD0005", self::$ERROR);
                return $this->json(false, 'bool');
            }
            
            // Kiểm tra Dịch vụ đã kéo treatment không được xoá, Dịch vụ đã có CTKM không được xoá
            $checkOrderDetails = $this->customerRepo->checkOrderDetails($orderDetails, $treatmentId);

            if($checkOrderDetails){
                foreach ($checkOrderDetails as $key => $item) {
                    if ($item->numPd > 0) {
                        $this->addMessage("Không thể xóa dịch vụ đã có thanh toán. Vui lòng tải lại trang để cập nhật trạng thái.", "DOD0006", self::$ERROR);
                        return $this->json(false, 'bool');
                    }
                    if ($item->numProOd > 0) {
                        $this->addMessage("Không thể xóa dịch vụ đã áp dụng giảm giá. Vui lòng hủy giảm giá trước.", "DOD0007", self::$ERROR);
                        return $this->json(false, 'bool');
                    }
                    if ($item->TreatmentMedicalProcedureStatusId == 2) {
                        $this->addMessage("Không thể xóa dịch vụ đang được thực hiện. Vui lòng tải lại trang để cập nhật trạng thái.", "DOD0008", self::$ERROR);
                        return $this->json(false, 'bool');
                    }
                    if ($item->TreatmentMedicalProcedureStatusId == 3) {
                        $this->addMessage("Không thể xóa dịch vụ đã hoàn tất. Vui lòng tải lại trang để cập nhật trạng thái.", "DOD0009", self::$ERROR);
                        return $this->json(false, 'bool');
                    }
                }
            }

            $checkStatusOrderDetails = $this->customerRepo->checkStatusOrderDetails($orderDetails);
            if($checkStatusOrderDetails){
                foreach ($checkStatusOrderDetails as $key => $v){
                    if ((int)$v['Status'] != 1) {
                        $this->addMessage("Trạng thái dịch vụ đã được thay đổi bởi người khác, vui lòng tải lại trang.", "CSOD0003", self::$ERROR);
                        return $this->json(false, 'bool');
                    }
                }
            }

            $results = $this->customerRepo->deleteOrderDetail($orderDetails, $branchId);
            if($results){
                foreach ($results as $key => $result) {
                    if ($result->Result == 1) {
                        $this->addMessage($result->ResultMessage, "DOD0002", self::$SUCCESS);
                        return $this->json(true, 'bool');
                    } else {
                        $this->addMessage($result->ResultMessage, "DOD0003", self::$ERROR);
                        return $this->json(false, 'bool');
                    }
                }
            }

            $this->addMessage("Xoá dịch vụ không thành công!", "DOD0010", self::$ERROR);
            return $this->json(false, 'bool');

        } catch (\Exception $e) {

            $this->addMessage("Xoá dịch vụ không thành công!", "DOD0004", self::$ERROR);
            return $this->json(false, 'bool');

        }
    }

    public function getTreatmentByCustomerId(Request $request)
    {
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
            $treatments = $this->customerRepo->getTreatmentByCustomerId($customerId);
            $result[] = $this->formatData('TreatmentByCustomerId', $treatments, 'Grid');
            return $this->json($result, 'views');
        } catch (\Exception $e) {
            return $this->json([$this->formatData('TreatmentByCustomerId', [])]);
        }
    }

    public function changeStatusOrderDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'OrderDetailAgree' => 'array',
            'OrderDetailUnAgree' => 'array',
            'TreatmentId' => 'required|numeric',
            'CustomerId' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $orderDetailAgree = $request->input('OrderDetailAgree');
        $orderDetailUnAgree = $request->input('OrderDetailUnAgree');
        $treatmentId = $request->input('TreatmentId');
        $customerId = $request->input('CustomerId');
        $note = $request->input('Note');
        try {

            $staffId = Auth::user()['StaffId'];
            // Kiểm tra IpAddress nhưng loại Doctor QC
            $ipAddress = Helper::getClientIp();
            $branchId = $this->customerRepo->checkIpAddress($ipAddress);
            if (!$branchId && $staffId != 3048) {
                $this->addMessage("Địa chỉ IP không hợp lệ.", "CSOD0001", self::$ERROR);
                return $this->json(false, 'bool');
            }
            // Kiểm tra nhân viên check-in trên hệ thống nhưng loại Doctor QC
            $timeKeeper = $this->customerRepo->checkTimeKeeper($staffId);
            if (!$timeKeeper && $staffId != 3048) {
                $this->addMessage("Nhân viên chưa thực hiện Checkin ngày hôm nay.", "CSOD0002", self::$ERROR);
                return $this->json(false, 'bool');
            }
            // Kiểm tra dữ liệu truyền vào
            if (empty($orderDetailAgree) && empty($orderDetailUnAgree)) {
                $this->addMessage("Dữ liệu truyền vào không hợp lệ.", "CSOD0009", self::$ERROR);
                return $this->json(false, 'bool');
            }
            // Kiểm tra trạng thái hiện tại của dịch vụ
            $checkOrderDetailAgree = $this->customerRepo->checkStatusOrderDetails($orderDetailAgree);
            $checkOrderDetailUnAgree = $this->customerRepo->checkStatusOrderDetails($orderDetailUnAgree);
            // $checkTransferReceipt = $this->customerRepo->checkTransferReceipt($orderDetailUnAgree, $treatmentId, $customerId);

            if($checkOrderDetailAgree){
                foreach ($checkOrderDetailAgree as $key => $v){
                    if ((int)$v['Status'] > 1 || (int)$v['Status'] == 0) {
                        $this->addMessage("Trạng thái dịch vụ đã được thay đổi bởi người khác, vui lòng tải lại trang để cập nhật trạng thái.", "CSOD0003", self::$ERROR);
                        return $this->json(false, 'bool');
                    }
                }
            }
            if($checkOrderDetailUnAgree){
                foreach ($checkOrderDetailUnAgree as $key => $va){
                    if ((int)$va['Status'] == 1) {
                        $this->addMessage("Trạng thái dịch vụ đã được thay đổi bởi người khác, vui lòng tải lại trang để cập nhật trạng thái.", "CSOD0009", self::$ERROR);
                        return $this->json(false, 'bool');
                    }
                }
            }

            // if(!$checkTransferReceipt){
            //     $this->addMessage("Không thể thay đổi trạng thái của dịch vụ, vui lòng tải lại trang để cập nhật trạng thái.", "CSOD0009", self::$ERROR);
            //     return $this->json(false, 'bool');
            // }

            // Kiểm tra Dịch vụ đã kéo treatment không được xoá, Dịch vụ đã có CTKM không được xoá
            $checkOrderDetails = $this->customerRepo->checkOrderDetails($orderDetailUnAgree, $treatmentId);

            if($checkOrderDetails){
                foreach ($checkOrderDetails as $key => $item) {
                    if ($item->TreatmentMedicalProcedureStatusId == 2) {
                        $this->addMessage("Dịch vụ đang được thực hiện không thể thay đổi trạng thái, vui lòng tải lại trang để cập nhật trạng thái.", "CSOD0004", self::$ERROR);
                        return $this->json(false, 'bool');
                    }
                    if ($item->TreatmentMedicalProcedureStatusId == 3) {
                        $this->addMessage("Dịch vụ đã hoàn tất không thể thay đổi trạng thái, vui lòng tải lại trang để cập nhật trạng thái.", "CSOD0005", self::$ERROR);
                        return $this->json(false, 'bool');
                    }
                }
            }

            $result = $this->customerRepo->changeStatusOrderDetail($orderDetailAgree, $orderDetailUnAgree, $treatmentId, $customerId, $note, $branchId );
            if($result){
                $this->addMessage("Thay đổi trạng thái dịch vụ thành công!", "CSOD0006", self::$SUCCESS);
                return $this->json(true, 'bool');
            }
            $this->addMessage("Thay đổi trạng thái dịch vụ không thành công!", "CSOD0007", self::$ERROR);
            return $this->json(false, 'bool');

        } catch (\Exception $e) {
            $this->addMessage("Thay đổi trạng thái dịch vụ không thành công!", "CSOD0008", self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    public function historyChangeOrderDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CustomerId' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        try {

            $arrStatus = [
                0 => 'Đã xoá',
                1 => 'Chưa đồng ý',
                2 => 'Xác nhận',
                3 => 'Kéo một phần',
                4 => 'Hoàn thành'
            ];
            $history = $this->customerRepo->getHistoryChangeOrderDetail($request->all());
            if($history){
                foreach ($history as $key => $item) {
                    $item->StatusName = isset($arrStatus[$item->StatusId]) ? $arrStatus[$item->StatusId] : 'Xác nhận';
                    if(isset($item->AnatomyBodyPartItemIds) && !empty($item->AnatomyBodyPartItemIds)) {
                        $item->AnatomyBodyPartItemName = $this->customerRepo->getAnatomyBodyPartItemName($item->AnatomyBodyPartItemIds);
                    } else {
                        $item->AnatomyBodyPartItemName = 'Xác nhận';
                    }
                }
            }
            $result[] = $this->formatPagination('HistoryChangeOrderDetail', $history, 'Grid');
            return $this->json($result, 'views');
        } catch (\Exception $e) {
            return $this->json([$this->formatPagination('HistoryChangeOrderDetail', [])]);
        }
    }

    public function spinWheel(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'CustomerId' => 'required|numeric',
                'TypeId' => 'numeric|in:1,2',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                $this->addMessage($errors->first(), 'ERR001', 3);
                return $this->json(false, 'bool');
            }
            $ipAddress = Helper::getClientIp();
            $typeId = $request->input('TypeId', 1);
            $customerId = $request->input('CustomerId');
            $luckyDraw = config('constants.lucky_draw')[$typeId] ?? null;
            $branchId = $this->customerRepo->checkIpAddress($ipAddress);
            if(!$luckyDraw)
                return $this->json(false, 'bool');
            if (!in_array($branchId, $luckyDraw['branch_ids'])) {
                if($typeId == 1) {
                    $this->addMessage("Chỉ áp dụng tại các Phòng khám Nha Khoa Kim khu vực TP. Hồ Chí Minh", "SW0002", self::$ERROR);
                }else{
                    $this->addMessage("Sự kiện ".$luckyDraw['name']." chưa áp dụng tại chi nhánh này.", "SW0002", self::$ERROR);
                }
                return $this->json(false, 'bool');
            }
            // $appointment = $this->customerRepo->getAppointmentCustomer($customerId);
            // if (!$appointment) {
            //     $this->addMessage("Vui lòng check in lịch hẹn cho khách hàng để tiến hành quay thưởng nhé!", "SW0001", self::$ERROR);
            //     return $this->json(false, 'bool');
            // }

            [$receipt, $numberSpins, $availableSpins] = $this->numAvailableSpins($customerId, $luckyDraw);
            if($availableSpins <= 0) {
                $this->addMessage("Số lần quay đã hết.", "SW0003", self::$ERROR);
                return $this->json(false, 'bool');
            }

            $result = $this->customerRepo->spinWheel($branchId, $customerId, $typeId);

            $results[] = $this->formatData('SpinWheel', $result, 'Grid');
            return $this->json($results, 'views');
        } catch (\Exception $e) {
            return $this->json([$this->formatData('SpinWheel', [])]);
        }
    }

    private function numAvailableSpins(int $customerId, array $luckyDraw): array
    {
        $receipt = $this->customerRepo->getTotalReceiptCustomer($customerId);
        $spinWheel = $this->customerRepo->getSpinWheel($customerId, $luckyDraw['type_id']);
        $numberSpins = count($spinWheel);
        $availableSpins = (int)($receipt/$luckyDraw['min_amount']) - $numberSpins;
        return [$receipt, $numberSpins, $availableSpins];
    }

    public function getCustomerPrize(Request $request)
    {
        try {
            $typeId = $request['TypeId'] ?? 1;
            $ipAddress = Helper::getClientIp();
            $luckyDraw = config('constants.lucky_draw')[$typeId] ?? null;
            if(!$luckyDraw)
                return $this->json(false, 'bool');
            $branchId = $this->customerRepo->checkIpAddress($ipAddress);
            $result = [];
            if (in_array($branchId, $luckyDraw['branch_ids'])) {
                $result = $this->customerRepo->getCustomerPrize($typeId);
            }
            $results[] = $this->formatData('CustomerPrize', $result, 'Grid');
            return $this->json($results, 'views');
        } catch (\Exception $e) {
            return $this->json([$this->formatData('CustomerPrize', [])]);
        }
    }

    public function getSpinWheel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CustomerId' => 'required|numeric',
            'TypeId' => 'numeric|in:1,2',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        try {

            $customerId = $request->input('CustomerId');
            $typeId = $request->input('TypeId', 1);
            $spinWheel = $this->customerRepo->getSpinWheel($customerId, $typeId);
            if (count($spinWheel) > 0) {
                $result[] = $this->formatData('ListSpinWheel', $spinWheel, 'Grid');
                return $this->json($result, 'views');
            }

            $ipAddress = Helper::getClientIp();
            $branchId = $this->customerRepo->checkIpAddress($ipAddress);
            $luckyDraw = config('constants.lucky_draw')[$typeId] ?? null;
            if(!$luckyDraw)
                return $this->json(false, 'bool');
            if(!$luckyDraw)
                return $this->json(false, 'bool');
            if (!in_array($branchId, $luckyDraw['branch_ids'])) {
                if($typeId == 1) {
                    $this->addMessage("Chỉ áp dụng tại các Phòng khám Nha Khoa Kim khu vực TP. Hồ Chí Minh", "SW0002", self::$ERROR);
                }else{
                    $this->addMessage("Sự kiện ".$luckyDraw['name']." chưa áp dụng tại chi nhánh này.", "SW0002", self::$ERROR);
                }
                return $this->json(false, 'bool');
            }
            // $appointment = $this->customerRepo->getAppointmentCustomer($customerId);
            // if (!$appointment) {
            //     $this->addMessage("Vui lòng check in lịch hẹn cho khách hàng để tiến hành quay thưởng nhé!", "SW0001", self::$ERROR);
            //     return $this->json(false, 'bool');
            // }

            $receipt = $this->customerRepo->getTotalReceiptCustomer($customerId);
            if (!$receipt && (int) $receipt >= $luckyDraw['min_amount']) {
                $this->addMessage("Khách hàng tư vấn phát sinh phiếu thu hợp lệ tại phòng khám, có tổng tiền thực thu từ ".number_format($luckyDraw['min_amount'])." đồng trở lên trong cùng 1 ngày.", "SW0001", self::$ERROR);
                return $this->json(false, 'bool');
            }

            if (count($spinWheel) > (int) ($receipt/$luckyDraw['min_amount'])) {
                $this->addMessage("Số lần quay đã hết.", "SW0003", self::$ERROR);
                return $this->json(false, 'bool');
            }

            return $this->json([$this->formatData('ListSpinWheel', [])]);

        } catch (\Exception $e) {
            return $this->json([$this->formatData('ListSpinWheel', [])]);
        }
    }

    public function infoSpinWheel(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'CustomerId' => 'required|numeric',
                'TypeId' => 'numeric|in:1,2',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                $this->addMessage($errors->first(), 'ERR001', 3);
                return $this->json(false, 'bool');
            }
            $customerId = $request->input('CustomerId');
            $typeId = $request->input('TypeId', 1);
            $luckyDraw = config('constants.lucky_draw')[$typeId] ?? null;
            if(!$luckyDraw)
                return $this->json(false, 'bool');

            [$receipt, $numberSpins, $availableSpins] = $this->numAvailableSpins($customerId, $luckyDraw);

            $result = [
                'CustomerId' => $customerId,
                'Receipt' => $receipt,
                'NumberSpins' => $numberSpins,
                'AvailableSpins' => $availableSpins
            ];

            $results[] = $this->formatData('InfoSpinWheel', $result, 'Grid');
            return $this->json($results, 'views');
        } catch (\Exception $e) {
            return $this->json([$this->formatData('InfoSpinWheel', [])]);
        }
    }

    public function confirmSpinResults(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'CustomerId' => 'required|numeric',
                'BranchId' => 'required|numeric',
                'LuckyDrawCampaignId' => 'required|numeric',
                'LuckyDrawGiftTypeId' => 'required|numeric',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                $this->addMessage($errors->first(), 'ERR001', 3);
                return $this->json(false, 'bool');
            }
            $customerId = $request->input('CustomerId');
            $branchId = $request->input('BranchId');
            $luckyDrawCampaignId = $request->input('LuckyDrawCampaignId');
            $luckyDrawGiftTypeId = $request->input('LuckyDrawGiftTypeId');

            $checkConfirmSpinResults = $this->customerRepo->checkConfirmSpinResults($customerId);
            if($checkConfirmSpinResults){
                $this->addMessage("Khách hàng đã xác nhận nhận quà từ chương trình quay số may mắn trước đó.", "CSWR001", self::$ERROR);
                return $this->json(false, 'bool');
            }
            $results = $this->customerRepo->confirmSpinResults($customerId, $branchId, $luckyDrawCampaignId, $luckyDrawGiftTypeId);

            if($results){
                foreach ($results as $key => $result) {
                    if ($result->Result == 1) {
                        $this->addMessage($result->ResultMessage, "CSWR004", self::$SUCCESS);
                        return $this->json(true, 'bool');
                    } else {
                        $this->addMessage($result->ResultMessage, "CSWR005", self::$ERROR);
                        return $this->json(false, 'bool');
                    }
                }
            }

            $this->addMessage("Xác nhận kết quả quay không thành công.", "CSWR002", self::$ERROR);
            return $this->json(false, 'bool');
        } catch (\Exception $e) {
            $this->addMessage("Xác nhận kết quả quay không thành công.", "CSWR003", self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    public function addOrUpdateInvisalign(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'CustomerId' => 'required|numeric',
                'InvisalignId'  => [
                    'nullable',
                    'max:25',
                    //'regex:/^[A-Za-z0-9]+$/',
                ],
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
                return $this->json(false, 'bool');
            }

            $customerId = $request->input('CustomerId');
            $invisalignId = $request->input('InvisalignId');

            if($invisalignId){
                $data = $this->customerRepo->checkCustomerInvisalign($invisalignId);
                if($data){
                    if($customerId != $data->CustomerId){
                        $this->addMessage("Mã Invisalign ID này (".$invisalignId.") đã trùng với KH ".$data->FullName." (".$data->CustomerCode."). Vui lòng kiểm tra lại.", "AUIOD0001", self::$ERROR);
                        return $this->json(false, 'bool');
                    }
                    $this->addMessage("Thêm thông tin Invisalign thành công!", "AUIOD0002", self::$SUCCESS);
                    return $this->json(true, 'bool');
                }
            }

            $result = $this->customerRepo->addOrUpdateInvisalign($customerId, $invisalignId);
            if($result){
                $this->addMessage("Thêm thông tin Invisalign thành công!", "AUIOD0002", self::$SUCCESS);
                return $this->json(true, 'bool');
            }
            $this->addMessage("Thêm thông tin Invisalign không thành công!", "AUIOD0003", self::$ERROR);
            return $this->json(false, 'bool');

        } catch (\Exception $e) {
            $this->addMessage("Thêm thông tin Invisalign không thành công!", "AUIOD0004", self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    public function listStaffByBranch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId'    => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $branchId = $request->input('BranchId');

        try {
            
            $customerRepository = new CustomerRepository;
            $result = $customerRepository->listStaffByBranch($branchId);

            return $this->json([$this->formatData('ListStaffByBranch',$result)]);

        } catch (\Exception $e) {

            return $this->json([$this->formatData('ListStaffByBranch',[])]);

        }
    }

    public function listStaffByBranchInDay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId'    => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $branchId = $request->input('BranchId');

        $result = [];
        try {
            
            $customerRepository = new CustomerRepository;
            $result = $customerRepository->listStaffByBranchInDay($branchId);


        } catch (\Exception $e) {
            Log::error("Get list Staff By Branch In Day fail", [$e->getMessage()]);
        }
        return $this->json([$this->formatData('ListStaffByBranch',$result)]);
    }

    public function infoInsurance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CustomerId'    => 'required|numeric',
            'TreatmentId'    => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $customerId = $request->input('CustomerId');
        $treatmentId = $request->input('TreatmentId');

        $result = [];
        try {
            
            $result = $this->customerRepo->infoInsurance($customerId, $treatmentId);


        } catch (\Exception $e) {
            Log::error("Info Insurance In Day fail", [$e->getMessage()]);
        }
        return $this->json([$this->formatData('InfoInsurance',$result)]);
    }

    public function infoCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CustomerId'    => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $customerId = $request->input('CustomerId');

        $result = [];
        try {
            
            $result = $this->customerRepo->infoCustomer($customerId);

        } catch (\Exception $e) {
            Log::error("Get Info Customer fail", [$e->getMessage()]);
        }
        return $this->json([$this->formatData('InfoCustomer',$result)]);
    }
}
