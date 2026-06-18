<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\ReceiptRepository;
use Illuminate\Support\Facades\Validator;
use App\Libs\Helper;
use Illuminate\Support\Facades\Log;
use App\Libs\Mutex;
use App\Libs\Logger\TelegramLogger;

class ReceiptController extends Controller
{
    /**
     * @var ReceiptRepository
     */
    protected $receiptRepo;


    public function __construct(ReceiptRepository $receiptRepo)
    {
        parent::__construct();
        $this->receiptRepo = $receiptRepo;
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

        $services = $this->receiptRepo->listServiceCreateReceipt($request->input('CustomerId'));
        foreach ($services as $key => $value) {

            $services[$key]['ActionByInfo'] = [ // Phụ tá xác nhận dịch vụ xét nghiệm
                'ActionBy'    => $value['ActionBy'] ?? null,
                'FullName'   => $value['ActionByName'] ?? null,
                'ActionDate' => $value['ActionDate'] ?? null,
            ];

            $services[$key]['ChangedByInfo'] = [ // Người tư vấn dịch vụ
                'ChangedAt'    => $value['ChangedAt'] ?? null,
                'ChangedBy'   => $value['ChangedBy'] ?? null,
                'FullName' => $value['ChangedByName'] ?? null,
            ];

            $services[$key]['ConsultedBy'] = [ // Người xác nhận dịch vụ
                'StaffId'    => $value['ConsultedBy'] ?? null,
                'StaffCode'   => $value['StaffCode'] ?? null,
                'FullName' => $value['ConsultedByName'] ?? null,
            ];
            $services[$key]['InvoiceId'] = $this->receiptRepo->getInfoInvoice($value['OrderDetailId']);
        }
        $results[] = $this->formatData("ListService", $services);
        return $this->json($results);
    }

    public function createReceipt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Receipt'       => 'required|array',
            'BranchId'      => 'required|integer',
            'CustomerId'    => 'required|integer',
            'TreatmentId'   => 'required|integer',
            'Note'          => 'nullable|string',
            'ReceiptType'   => 'required|string',
            'OrderDetails'  => 'nullable|array'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $orderDetails = $request->input('OrderDetails', []);
        if (!is_array($orderDetails)) {
            $orderDetails = [];
        }
        if (count($orderDetails) > 1) {
            $checkoutServiceDelete = $this->receiptRepo->checkoutServiceDelete($orderDetails);
            if($checkoutServiceDelete){
                $this->addMessage("Tồn tại dịch vụ đã thay đổi trạng thái. Vui lòng F5 lại màn hình.", 'CR0007', self::$ERROR);
                return $this->json(false, 'bool');
            }
        }

        // Kiểm tra IP Phòng khám
        $ipAddress = Helper::getClientIp();
        $branchId = $this->receiptRepo->checkIpAddress($ipAddress);
        if(!$branchId || empty($branchId)) {
            $this->addMessage("Địa chỉ IP không hợp lệ.", 'CR0001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        // Kiểm tra dịch vụ đã thu đủ tiền
        if($request->input('ReceiptType') == 1) {
            $services = $this->receiptRepo->checkServiceCreateReceipt($request->all());
            if ($services === false) {
                $this->addMessage("Tồn tại dịch vụ đã thu đủ tiền. Vui lòng F5 lại màn hình.", 'CR0007', self::$ERROR);
                return $this->json(false, 'bool');
            }
        }

        // Kiểm tra phiếu trả góp có nhiều hơn 1 dịch vụ hay không
        if($request->input('ReceiptType') == 3) {
            $orderDetails = $request->input('OrderDetails', []);
            if (count($orderDetails) > 1) {
                $this->addMessage("Đang tồn tại nhiều hơn 1 dịch vụ trong phiếu trả góp.", 'CR0012', self::$ERROR);
                return $this->json(false, 'bool');
            }
        }

        // Khóa tiến trình Phiếu Thu khách hàng để tránh tạo nhiều phiếu thu cùng lúc
        $customerId = $request->input('CustomerId');
        $lockKey = "lock:receipt_create_customer_id:$customerId";
        $cooldownKey = "cooldown:receipt_create_customer_id:$customerId";
        if (Mutex::hasCooldown($cooldownKey)) {
            $this->addMessage('Phiếu thu đã được tạo, vui lòng F5 lại trang.','ERR001', self::$ERROR);
            return $this->json(false,'bool');
        }
        $startTime = microtime(true);
        $result    = null;
        try {
            $result = Mutex::run($lockKey, 60, function () use ($request) {
                return $this->receiptRepo->createReceipt($request->all());
            });
        } catch (\Exception $e) {
            $this->addMessage("Đang xử lý phiếu thu, vui lòng đợi.", 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        } finally {
            $elapsed = microtime(true) - $startTime;
            if ($elapsed > 50) {
                $msg = "<b>CreateReceipt xử lý quá lâu</b>\n"
                    . "CustomerId: <code>{$customerId}</code>\n"
                    . "ReceiptId: <code>" . json_encode($result) . "</code>\n"
                    . "Elapsed time: <code>" . round($elapsed, 2) . "s</code>\n";
                TelegramLogger::send($msg);
            }
        }
        if ($result) {
            Mutex::setCooldown($cooldownKey, 5);
            $this->addMessage("Tạo phiếu thu thành công.", 'CR0002', self::$SUCCESS);
            $results[] = $this->formatData("InfoReceipt", $result);
            return $this->json($results);
        } else {
            $this->addMessage("Tạo phiếu thu thất bại.", 'CR0003', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    public function updateReceipt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ReceiptId'     => 'required|integer',
            'Receipt'       => 'required|array',
            'BranchId'      => 'required|integer',
            'CustomerId'    => 'required|integer',
            'Note'          => 'nullable|string',
            'ReceiptType'   => 'required|string'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        // Kiểm tra IP Phòng khám
        $ipAddress = Helper::getClientIp();
        $branchId = $this->receiptRepo->checkIpAddress($ipAddress);
        if (!$branchId || $branchId != $request->input('BranchId')) {
            $this->addMessage("Địa chỉ IP không hợp lệ.", 'CR0001', self::$SUCCESS);
            return $this->json(false, 'bool');
        }

        if($request->input('ReceiptType') == 1) {
            $this->addMessage("Không được phép sửa phiếu thu.", 'CR0011', self::$ERROR);
            return $this->json(false, 'bool');
        }

        // Kiểm tra ví khách hàng
        $infoDeposit = $this->receiptRepo->checkDepositCustomer($request->input('CustomerId'));
        if (!$infoDeposit) {
            $this->addMessage("Ví khách hàng không tồn tại để thực hiện giao dịch.", 'CR0007', self::$ERROR);
            return $this->json(false, 'bool');
        }

        // Chỉ sửa phiếu thu trong ngày và không quá 22:00
        $receiptValid = $this->receiptRepo->checkReceiptUpdate($request->input('ReceiptId'));
        if (!$receiptValid) {
            $this->addMessage("Chỉ được phép sửa phiếu thu trong ngày và trước 22:00.", 'CR0008', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $totalAmount = 0;
        $receipt = $request->input('Receipt');
        foreach ($receipt as $item) {
            if (isset($item['Amount'])) {
                $totalAmount += $item['Amount'];
            }
        }
        $totalAmountBeforeUpdate = isset($receiptValid->TotalAmount) ? $receiptValid->TotalAmount : 0;
        $checkEditReceipt = $this->receiptRepo->checkEditReceipt($request->input('CustomerId'));
        if (!$checkEditReceipt) {
            $this->addMessage("Không tồn tại đợt điều trị. Vui lòng thử lại", 'CR0010', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $paymentAmount = $checkEditReceipt->PaymentAmount;
        $treatmentAmount = $checkEditReceipt->TotalAmount;
        // Kiểm tra ví đã phân bổ hay chưa
        if (($paymentAmount - ($treatmentAmount + $totalAmount - $totalAmountBeforeUpdate)) > 0) {
            $this->addMessage("Ví khách hàng đã phân bổ không thể điều chỉnh phiếu thu", 'CR0009', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $checkAllocateReceipt = $this->receiptRepo->checkAllocateReceipt($request->input('ReceiptId'));
        $receiptAmountOld = 0;
        $transferAmountOld = 0;
        $expenditureAmountOld = 0;
        if ($checkAllocateReceipt) {
            foreach ($checkAllocateReceipt as $value) {
                $receiptAmountOld     += (int)$value['ReceiptAmount'];
                $transferAmountOld    += (int)$value['TransferAmount'];
                $expenditureAmountOld += (int)$value['ExpenditureAmount'];
            }
        }
        $availableAmountOld = $receiptAmountOld + $transferAmountOld + $expenditureAmountOld;
        // Phiếu thu cũ đã phân bổ thì không cho sửa phiếu thu
        if ($totalAmountBeforeUpdate > $totalAmount && (($totalAmountBeforeUpdate - $totalAmount) > $availableAmountOld)) {
            $this->addMessage("Phiếu thu đã phân bổ, không thể điều chỉnh nhỏ hơn số tiền đã phân bổ", 'CR0010', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $result = $this->receiptRepo->updateReceipt($request->all(), $totalAmountBeforeUpdate);

        if ($result) {
            $this->addMessage("Cập nhật phiếu thu thành công.", 'CR0004', self::$SUCCESS);
            return $this->json(true, 'bool');
        } else {
            $this->addMessage("Cập nhật phiếu thu thất bại.", 'CR0005', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    public function createPayInstallments(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'OrderDetailIds'        => 'required|array',
            'Status'                => 'required|integer'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'CPT0003', 3);
            return $this->json(false, 'bool');
        }

        $result = $this->receiptRepo->createPayInstallments($request->all());

        if ($result) {
            $this->addMessage("Cập nhật trả góp thành công.", 'CPT0001', self::$SUCCESS);
            return $this->json(true, 'bool');
        } else {
            $this->addMessage("Cập nhật trả góp thất bại.", 'CPI0002', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    public function webhookFinishReceiptPending(Request $request)
    {
        Log::info("[MPOS] Webhook finish receipt pending", $request->all());
        $validator = Validator::make($request->all(), [
            'ReceiptPendingId'        => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'FRP0001', 3);
            return $this->json(false, 'bool');
        }

        $result = $this->receiptRepo->webhookFinishReceiptPending($request->get('ReceiptPendingId', 0));

        if ($result) {
            $this->addMessage("Cập nhật thanh toán thành công.", 'FRP0002', self::$SUCCESS);
            return $this->json(true, 'bool');
        }

        $this->addMessage("Cập nhật thanh toán thất bại.", 'FRP0003', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function webhookCancelReceiptPending(Request $request)
    {
        Log::info("[MPOS] Webhook cancel receipt pending", $request->all());
        $validator = Validator::make($request->all(), [
            'ReceiptPendingId'        => 'required|numeric'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'FRP0001', 3);
            return $this->json(false, 'bool');
        }

        $result = $this->receiptRepo->webhookCancelReceiptPending($request->get('ReceiptPendingId', 0));

        if ($result) {
            $this->addMessage("Huỷ thanh toán thành công.", 'FRP0002', self::$SUCCESS);
            return $this->json(true, 'bool');
        }

        $this->addMessage("Huỷ thanh toán thất bại.", 'FRP0003', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function cancelReceiptPending(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ReceiptPendingId'        => 'required|numeric',
            'CurrentUpdatedDate'      => 'required'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'FRP0001', 3);
            return $this->json(false, 'bool');
        }
        $exist = $this->receiptRepo->findReceiptPending($request->get('ReceiptPendingId', 0), $request->get('CurrentUpdatedDate'));

        if (!$exist || empty($exist)) {
            $this->addMessage("Dữ liệu đã thay đổi. Vui lòng F5 màn hình.", 'FRP0003', self::$ERROR);
            return $this->json(false, 'bool');
        }
        //Call re check receipt pending
        $resCheck = $this->receiptRepo->checkReceiptPending($request->get('ReceiptPendingId', 0));
        $exist = $this->receiptRepo->findReceiptPending($request->get('ReceiptPendingId', 0), $request->get('CurrentUpdatedDate'));

        if (!$exist || empty($exist)) {
            $this->addMessage("Dữ liệu đã thay đổi. Vui lòng F5 màn hình.", 'FRP0003', self::$ERROR);
            return $this->json(false, 'bool');
        }


        //Call cancel receipt pending
        $result = $this->receiptRepo->cancelReceiptPending($request->get('ReceiptPendingId', 0));

        if ($result) {
            $this->addMessage("Huỷ thanh toán thành công.", 'FRP0002', self::$SUCCESS);
            return $this->json(true, 'bool');
        }

        $this->addMessage("Huỷ thanh toán thất bại.", 'FRP0003', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function checkReceiptPending(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ReceiptPendingId'        => 'required|numeric',
            'CurrentUpdatedDate'      => 'required'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'FRP0001', 3);
            return $this->json(false, 'bool');
        }

        $exist = $this->receiptRepo->findReceiptPending($request->get('ReceiptPendingId', 0), $request->get('CurrentUpdatedDate'));

        if (!$exist || empty($exist)) {
            $this->addMessage("Dữ liệu đã thay đổi. Vui lòng F5 màn hình.", 'FRP0003', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $result = $this->receiptRepo->checkReceiptPending($request->get('ReceiptPendingId', 0));

        if ($result) {
            $this->addMessage("Hệ thống đã cập nhật trạng thái thanh toán. Vui lòng F5 màn hình.", 'FRP0002', self::$SUCCESS);
            return $this->json(true, 'bool');
        }

        $this->addMessage("Khách hàng chưa thanh toán.", 'FRP0003', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function listCustomerInstallment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'nullable|integer',
            'Keyword' => 'nullable|string',
            'OrderInstallmentPlanStatus' => 'nullable|integer',
            'limit' => 'nullable|integer',
            'lmstart' => 'nullable|numeric'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $installments = $this->receiptRepo->listCustomerInstallment($request->all());

        $results[] = $this->formatPagination("ListCustomerInstallment", $installments);
        return $this->json($results);
    }

    public function totalCustomerInstallment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'nullable|integer',
            'Keyword' => 'nullable|string',
            'OrderInstallmentPlanStatus' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $installments = $this->receiptRepo->totalCustomerInstallment($request->all());

        $results[] = $this->formatData("TotalCustomerInstallment", $installments);
        return $this->json($results);
    }
}
