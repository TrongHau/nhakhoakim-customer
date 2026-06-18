<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\ExpenditureRepository;
use Illuminate\Support\Facades\Validator;
use App\Libs\Helper;

class ExpenditureController extends Controller
{
    /**
     * @var ExpenditureRepository
     */
    protected $expenditureRepo;


        public function __construct(ExpenditureRepository $expenditureRepo) {
        parent::__construct();
        $this->expenditureRepo = $expenditureRepo;
    }

    public function listService(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CustomerId' => 'required|integer'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $services = $this->expenditureRepo->listServiceCreateExpenditure($request->input('CustomerId'));
        $results[] = $this->formatData("ListService", $services);
        return $this->json($results);
    }

    public function createExpenditure(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId'                  => 'required|integer',
            'ExpenditureCode'           => 'required|string',
            'ExpenditureTypeId'         => 'required|integer',
            'ExpenditureCategoryId'     => 'required|integer',
            'ReceiverName'              => 'nullable|string',
            'Note'                      => 'nullable|string',
            'PaymentMethodId'           => 'required|integer',
            'BankId'                    => 'nullable|integer',
            'RefId'                     => 'nullable|integer',
            'Exhibit'                   => 'nullable|array',
            'type'                      => 'nullable|string',
            'OrderDetails'              => 'nullable|array',
            'TreatmentId'               => 'nullable|integer',
            'ExpenditureId'             => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        // Kiểm tra IP Phòng khám
        $ipAddress = Helper::getClientIp();
        $branchId = $this->expenditureRepo->checkIpAddress($ipAddress);
        if(!$branchId) {
            $this->addMessage("Địa chỉ IP không hợp lệ.", 'CR0001', self::$SUCCESS);
            return $this->json(false, 'bool');
        }

        // Kiểm tra dịch vụ đã thu đủ tiền
        $services = $this->expenditureRepo->checkServiceCreateExpenditure($request->all());
        if($services === false) {
            $this->addMessage("Tồn tại dịch vụ đã chi hết tiền. Vui lòng F5 lại màn hình.", 'CR0006', self::$ERROR);
            return $this->json(false, 'bool');
        }

        // Kiểm tra số tiền chi lớn hơn số tiền còn lại
        $checkAmount = $this->expenditureRepo->checkAmountServiceCreateExpenditure($request->all());
        if($checkAmount === false) {
            $this->addMessage("Tồn tại dịch vụ chi tiền lớn hơn số tiền còn lại. Vui lòng F5 lại màn hình.", 'CR0006', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $result = $this->expenditureRepo->createExpenditure($request->all());

        if($result) {
            $this->addMessage("Tạo phiếu chi thành công.", 'CR0002', self::$SUCCESS);
            return $this->json(true, 'bool');
        } else {
            $this->addMessage("Tạo phiếu chi thất bại.", 'CR0003', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }
}
