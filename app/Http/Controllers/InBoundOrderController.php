<?php

namespace App\Http\Controllers;

use App\Repositories\InBoundOrderRepository;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class InBoundOrderController extends Controller
{
    protected $inBoundOrderRepo;

    public function __construct()
    {
        Parent::__construct();
        $this->inBoundOrderRepo = new InBoundOrderRepository();
    }

    public function create(Request $request)
    {
        $validateMsg = [
            'Products' => 'thông tin sản phẩm nhập hàng',
            'ExpectArrivalDate' => 'Ngày dự kiến nhập hàng'
        ];
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required|numeric',
            'ProviderId' => 'nullable|numeric',
            'ExpectArrivalDate' => 'nullable|date',
            'RefCode' => 'nullable',
            'ExpectQuantity' => 'nullable|numeric',
            'Price' => 'nullable|numeric',
            'Products'  => 'required|array',
        ], [], $validateMsg);
        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), "IBC001", self::$ERROR);
            return $this->json(false, 'bool');
        }
        $refCode = $request->get('RefCode');
        if (isset($refCode) && !empty($refCode)) {
            $checkRefCode = $this->inBoundOrderRepo->checkRefCode($refCode);
            if (!$checkRefCode) {
                $this->addMessage("Mã phiếu nhập tham chiếu đã tồn tại.", "IBC002", self::$ERROR);
                return $this->json(false, 'bool');
            }
        }

        $rs = $this->inBoundOrderRepo->createInboundOrder($request->all());

        if ($rs->Result) {
            $this->addMessage("Tạo phiếu nhập hàng thành công.", "IBC002", self::$SUCCESS);
            return $this->json(true, 'bool');
        }

        $this->addMessage($rs->ResultMessage ?? "Tạo phiếu nhập hàng thất bại.", "IBC002", self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function listWarehousedProducts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lmstart' => 'numeric',
            'limit' => 'numeric'
        ]);
        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), "IBL001", self::$ERROR);
            return $this->json(false, 'bool');
        }

        $list = $this->inBoundOrderRepo->getWarehousedProductsList($request->all());

        return $this->json([$this->formatPagination("ListInboundOrder", $list)], 'views');
    }

    public function listProductsIR(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate' => 'nullable|date',
            'ToDate' => 'nullable|date',
            'Type' => 'nullable|numeric',
            'lmstart' => 'nullable|numeric',
            'limit' => 'nullable|numeric'
        ]);
        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), "IBL001", self::$ERROR);
            return $this->json(false, 'bool');
        }

        $list = $this->inBoundOrderRepo->getListIR($request->all());

        return $this->json([$this->formatPagination("ListInboundOrder", $list)], 'views');
    }

    public function updateStatusInboundOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'IRId' => 'required|numeric',
            'Type' => 'required|numeric',
            'CurrentStatus' => 'required|numeric',
            'Products' => 'array'
        ]);
        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), "IBU001", self::$ERROR);
            return $this->json(false, 'bool');
        }

        if ($request->get('Type') == 2 && $request->get('Products') == NULL) {
            $this->addMessage("Trường Products không được bỏ trống.", "IBU003", self::$ERROR);
            return $this->json(false, 'bool');
        }

        $results = $this->inBoundOrderRepo->updateInboundOrder($request->all());
        $message = 'Cập nhật phiếu nhập hàng thất bại';
        if (isset($results) && !empty($results) && is_array($results) && isset($results[0])) {
            $message = $results[0]->ResultMessage;
            if ($results[0]->Result) {
                $this->addMessage($message, 'IBU001', self::$SUCCESS);
                return $this->json(true, 'bool');
            } else {
                $this->addMessage($message, 'IBU001', self::$ERROR);
                return $this->json(false, 'bool');
            }
        }
        $this->addMessage($message, 'IBU001', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function updateIR(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'IRId' => 'required|numeric',
            'Status' => 'numeric',
            'BranchId' => 'required|numeric',
            'ProviderId' => 'numeric',
            'ExpectArrivalDate' => 'date',
            'RefCode' => 'nullable',
            'ExpectQuantity' => 'numeric',
            'Price' => 'numeric',
            'Products'  => 'array',
        ]);
        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), "IBU001", self::$ERROR);
            return $this->json(false, 'bool');
        }

        $status = $request->get('Status');
        if (!isset($status) || empty($status) || $status != 1) {
            $this->addMessage("Chỉ có thể chỉnh sửa phiếu nhập hàng ở trạng thái 'Mới'.", "IBU003", self::$ERROR);
            return $this->json(false, 'bool');
        }

        // Kiểm tra người tạo mới cho sửa
        $checkCreateBy = $this->inBoundOrderRepo->checkCreateBy($request->get('IRId'));
        if (!$checkCreateBy) {
            $this->addMessage("Chỉ có thể chỉnh sửa phiếu nhập hàng do bạn tạo.", "IBU003", self::$ERROR);
            return $this->json(false, 'bool');
        }

        $request->merge(['Type' => 4]);
        $results = $this->inBoundOrderRepo->updateInboundOrder($request->all());
        $message = 'Cập nhật phiếu nhập hàng thất bại';
        if (isset($results) && !empty($results) && is_array($results) && isset($results[0])) {
            $message = $results[0]->ResultMessage;
            if ($results[0]->Result) {
                $this->addMessage($message, 'IBU002', self::$SUCCESS);
                return $this->json(true, 'bool');
            } else {
                $this->addMessage($message, 'IBU003', self::$ERROR);
                return $this->json(false, 'bool');
            }
        }
        $this->addMessage($message, 'IBU004', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function getOptionLists(Request $request)
    {
        $listOption = $this->inBoundOrderRepo->getOptionLists();

        return $this->json([$this->formatData("OptionList", $listOption, 'Grid')], 'views');
    }

    public function getDetailIR(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'IRId' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), "IBD001", self::$ERROR);
            return $this->json(false, 'bool');
        }
        $detailIR = $this->inBoundOrderRepo->getDetailIR($request->get('IRId'));
        return $this->json([$this->formatData("DetailProductIR", $detailIR, 'Grid')], 'views');
    }

    public function processExcelInbound(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'File' => 'required|file',
        ]);
        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), "IBI001", self::$ERROR);
            return $this->json(false, 'bool');
        }
        if ($request->file('File')) {
            $allowFileTypes = ['xls', 'xlsx'];
            $file = $request->file('File');
            if ($file && !in_array(strtolower($file->getClientOriginalExtension()), $allowFileTypes)) {
                $this->addMessage("File import không đúng định dạng. Định dạng đúng .xlsx, .xls", 'ERR001', self::$ERROR);
                return $this->json(false, 'bool');
            }
        }

        $result = $this->inBoundOrderRepo->processExcelInbound($request->file('File'));

        if ($result && isset($result['Error'])) {
            $this->addMessage($result['Error'], "IBI004", self::$ERROR);
            return $this->json(false, 'bool');
        }

        if ($result) {
            $this->addMessage("Xử lý dữ liệu thành công.", "IBI002", self::$SUCCESS);
            $results[] = $this->formatData('ProcessExcelInbound', $result);
            return $this->json($results, 'views');
        }

        $this->addMessage("Xử lý dữ liệu thất bại. Vui lòng kiểm tra lại dữ liệu.", "IBI003", self::$ERROR);
        return $this->json(false, 'bool');
    }
}
