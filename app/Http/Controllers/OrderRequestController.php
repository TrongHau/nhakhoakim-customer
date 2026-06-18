<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repositories\OrderRequestRepository;
use App\Repositories\OutboutRequestRepository;
use App\Repositories\InventoryRepository;
use App\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderRequestController extends Controller
{
    protected $orderRequestRepo;
    protected $outboutRequestRepo;
    protected $inventoryRepo;

    public function __construct(
        OrderRequestRepository $orderRequestRepo,
        OutboutRequestRepository $outboutRequestRepo,
        InventoryRepository $inventoryRepo
    ) {
        parent::__construct();
        $this->orderRequestRepo   = $orderRequestRepo;
        $this->outboutRequestRepo = $outboutRequestRepo;
        $this->inventoryRepo      = $inventoryRepo;
    }

    /**
     * POST /order-request/list
     */
    public function index(Request $request)
    {
        $conditions = $request->all();
        $conditions['DepartmentId'] = $conditions['BranchId'] ?? [];
        unset($conditions['BranchId']);

        $requests = $this->orderRequestRepo->search($conditions);
        $response[] = $this->formatPagination('OrderRequestList', $requests, 'Grid');

        return $this->json($response);
    }

    /**
     * POST /order-request/list-all
     */
    public function listOrderRequest()
    {
        $requests = $this->orderRequestRepo->listOrderRequest();
        $response[] = $this->formatData('OrderRequestListAll', $requests, 'Grid');

        return $this->json($response);
    }

    /**
     * POST /order-request/detail
     */
    public function show(Request $request)
    {
        $id           = (int) $request->input('OrderRequestId');
        $orderRequest = $this->orderRequestRepo->getDetail($id);

        if (!$orderRequest) {
            $this->addMessage('Không tìm thấy yêu cầu cấp phát', 'ERR003', self::$ERROR);
            return $this->json(null, 'views', 404);
        }

        $response[] = $this->formatData('OrderRequestDetail', $orderRequest);

        return $this->json($response);
    }

    /**
     * POST /order-request/create
     */
    public function store(Request $request)
    {
        $data    = $request->all();
        $details = $data['Details'] ?? [];
        unset($data['Details']);

        $now = Carbon::now();

        $data['OrderRequestCode'] = $this->orderRequestRepo->generateCode($data['DepartmentId']);
        $data['DepartmentId']      = $data['DepartmentId'] ?? 0;
        $data['RequestedBy']      = Auth::user()['StaffId'] ?? 0;
        $data['RequestDate']      = $now;
        $data['RequestType']      = 1;
        $data['Status']           = 1;
        $data['ProcessType']      = 2;
        $data['CreatedBy']        = Auth::user()['StaffId'] ?? 0;
        $data['CreatedDate']      = $now;

        unset($data['BranchId']);

        $orderRequestId = $this->orderRequestRepo->store($data, $details);

        $orderRequest = $this->orderRequestRepo->getDetail($orderRequestId);
        $response[]   = $this->formatData('OrderRequestDetail', $orderRequest);

        $this->addMessage('Tạo đơn đặt hàng thành công', 'SUC001', self::$SUCCESS);

        return $this->json($response, 'views', 201);
    }

    /**
     * POST /order-request/update
     * Chỉ cho phép sửa khi Status = 1 (Mới)
     */
    public function update(Request $request)
    {
        $id      = (int) $request->input('OrderRequestId');
        $data    = $request->all();
        $details = $data['Details'] ?? null;
        unset($data['Details'], $data['OrderRequestId']);

        $orderRequest = $this->orderRequestRepo->getDetail($id);

        if (!$orderRequest) {
            $this->addMessage('Không tìm thấy yêu cầu cấp phát', 'ERR003', self::$ERROR);
            return $this->json(null, 'views', 404);
        }

        if ($orderRequest->Status !== 1) {
            $this->addMessage('Chỉ có thể sửa yêu cầu ở trạng thái Mới', 'ERR004', self::$ERROR);
            return $this->json(null, 'views', 422);
        }

        $data['UpdatedBy']   = Auth::user()['StaffId'] ?? 0;
        $data['UpdatedDate'] = Carbon::now();

        $this->orderRequestRepo->updateOrderRequest($id, $data, $details);

        $orderRequest = $this->orderRequestRepo->getDetail($id);
        $response[]   = $this->formatData('OrderRequestDetail', $orderRequest);
        $this->addMessage('Cập nhật yêu cầu cấp phát thành công', 'SUC006', self::$SUCCESS);

        return $this->json($response);
    }

    /**
     * POST /order-request/update-expected-delivery-date
     */
    public function updateExpectedDeliveryDate(Request $request)
    {
        $id                   = (int) $request->input('OrderRequestId');
        $expectedDeliveryDate = $request->input('ExpectedDeliveryDate');

        if (!$expectedDeliveryDate) {
            $this->addMessage('Vui lòng cung cấp ngày giao hàng dự kiến', 'ERR001', self::$ERROR);
            return $this->json(null, 'views', 422);
        }

        $orderRequest = $this->orderRequestRepo->getDetail($id);

        if (!$orderRequest) {
            $this->addMessage('Không tìm thấy yêu cầu cấp phát', 'ERR003', self::$ERROR);
            return $this->json(null, 'views', 404);
        }

        $this->orderRequestRepo->updateExpectedDeliveryDate($id, $expectedDeliveryDate, Auth::user()['StaffId'] ?? 0);

        $orderRequest = $this->orderRequestRepo->getDetail($id);
        $response[]   = $this->formatData('OrderRequestDetail', $orderRequest);
        $this->addMessage('Cập nhật ngày giao hàng thành công', 'SUC005', self::$SUCCESS);

        return $this->json($response);
    }

    /**
     * POST /order-request/update-status
     * Status: 1: Mới, 2: Đang xử lý, 3: Huỷ, 4: Đang vận chuyển, 5: Hoàn thành, 6 Từ chối, 10, Tiếp nhận
     *
     * Status = 4 (xuất kho):
     *   - Tạo phiếu xuất kho (OutboutRequest)
     *   - Details (optional): [{OrderRequestDetailId, ExpectedQty}]
     *     Nếu không truyền Details hoặc thiếu dòng nào → dùng RequestQuantity gốc
     *
     * Status = 10 (Tiếp nhận):
     *   - Bắt buộc truyền Details: [{OrderRequestDetailId, ReceivedQuantity}]
     */
    public function updateStatus(Request $request)
    {
        $id      = (int) $request->input('OrderRequestId');
        $status  = (int) $request->input('Status');
        $note  = $request->input('Note') ?? NULL;
        $details = $request->input('Details', []);
        $expectedDeliveryDate = $request->input('ExpectedDeliveryDate') ?? NULL;

        if (!in_array($status, [1, 2, 3, 4, 5, 6, 10])) {
            $this->addMessage('Trạng thái không hợp lệ', 'ERR001', self::$ERROR);
            return $this->json(false, 'views', 422);
        }

        $orderRequest = $this->orderRequestRepo->getDetail($id);

        if (!$orderRequest) {
            $this->addMessage('Không tìm thấy yêu cầu cấp phát', 'ERR003', self::$ERROR);
            return $this->json(false, 'views', 404);
        }

        $staffId = Auth::user()['StaffId'] ?? 0;
        $now     = Carbon::now();

        // Status = 4: tạo phiếu xuất kho, trừ tồn kho
        if ($status === 4) {
            $orderDetails = $orderRequest->details ?? [];

            if (empty($orderDetails)) {
                $this->addMessage('Yêu cầu không có sản phẩm để xuất kho', 'ERR005', self::$ERROR);
                return $this->json(null, 'views', 422);
            }

            // Map ExpectedQty từ Details client truyền lên (key: OrderRequestDetailId)
            $clientQtyMap = [];
            foreach ($details as $d) {
                if (!empty($d['OrderRequestDetailId'])) {
                    $clientQtyMap[(int) $d['OrderRequestDetailId']] = max(0, (int) ($d['ExpectedQty'] ?? 0));
                }
            }

            $outboutDetails = [];
            foreach ($orderDetails as $detail) {
                $detailId    = (int) ($detail['OrderRequestDetailId'] ?? 0);
                $expectedQty = isset($clientQtyMap[$detailId])
                    ? $clientQtyMap[$detailId]
                    : (int) ($detail['RequestQuantity'] ?? 0);

                $outboutDetails[] = [
                    'ProductId'   => $detail['ProductId'],
                    'UnitId'      => $detail['UnitId'] ?? null,
                    'ExpectedQty' => $expectedQty,
                    'ActualQty'   => 0,
                ];
            }

            $outboutData = [
                'OutboutCode'         => $this->outboutRequestRepo->generateCode(),
                'RelatedType'         => 1,
                'RelatedId'           => $id,
                'TotalSKU'            => count($outboutDetails),
                'Status'              => 1,
                'ExpectedReceiptDate' => $request->input('ExpectedReceiptDate'),
                'DeliveryStaff'       => $request->input('DeliveryStaff'),
                'DeliveryDate'        => $request->input('DeliveryDate'),
                'Note'                => $request->input('Note'),
                'CreatedBy'           => $staffId,
                'CreatedDate'         => $now,
            ];

            $outboutRequestId = $this->outboutRequestRepo->store($outboutData, $outboutDetails);

            // Trừ tồn kho tại kho trung tâm (WarehouseType = 1)
            $centralWarehouse = Warehouse::where('WarehouseType', 1)->where('Status', 1)->first();
            if ($centralWarehouse) {
                foreach ($outboutDetails as $outboutDetail) {
                    if ($outboutDetail['ExpectedQty'] > 0) {
                        $this->inventoryRepo->updateStock(
                            $outboutDetail['ProductId'],
                            $centralWarehouse->WarehouseId,
                            $outboutDetail['ExpectedQty'],
                            2,                  // TransactionType = 2: Xuất kho
                            'OutboutRequest',
                            $outboutRequestId,
                            null,
                            $staffId
                        );
                    }
                }
            }
        }

        $result = $this->orderRequestRepo->updateStatus($id, $status, $note, $staffId, $details, $expectedDeliveryDate);

        if (!$result) {
            $this->addMessage('Không thể cập nhật trạng thái', 'ERR004', self::$ERROR);
            return $this->json(null, 'views', 422);
        }

        $orderRequest = $this->orderRequestRepo->getDetail($id);
        $response[]   = $this->formatData('OrderRequestDetail', $orderRequest);
        $this->addMessage('Cập nhật trạng thái thành công', 'SUC004', self::$SUCCESS);

        return $this->json($response);
    }

    /**
     * POST /order-request/department-demand-list
     * Lấy danh sách nhu cầu vật tư theo phòng khám
     */
    public function getDepartmentDemandList(Request $request)
    {
        $conditions = $request->all();
        $list = $this->orderRequestRepo->getDepartmentDemandList($conditions);

        $response[] = $this->formatData('DepartmentDemandList', $list, 'Grid');
        return $this->json($response);
    }

    /**
     * POST /order-request/department-demand-detail
     * Lấy chi tiết nhu cầu vật tư của một phòng khám
     */
    public function getDepartmentDemandDetail(Request $request)
    {
        $departmentId = (int) $request->input('DepartmentId');
        $keyword = $request->input('Keyword');

        if (!$departmentId) {
            $this->addMessage('Vui lòng cung cấp DepartmentId', 'ERR001', self::$ERROR);
            return $this->json(null, 'views', 422);
        }

        $detail = $this->orderRequestRepo->getDepartmentDemandDetail($departmentId,$keyword);
        $response[] = $this->formatData('DepartmentDemandDetail', $detail, 'Grid');

        return $this->json($response);
    }

    /**
     * POST /order-request/create-delivery-from-demand
     * Tạo phiếu giao hàng từ DepartmentDemand
     */
    public function createDeliveryFromDemand(Request $request)
    {
        $data = $request->all();
        
        // Validate required fields
        if (empty($data['DepartmentId'])) {
            $this->addMessage('Vui lòng cung cấp DepartmentId', 'ERR001', self::$ERROR);
            return $this->json(null, 'views', 422);
        }

        if (empty($data['Products']) || !is_array($data['Products'])) {
            $this->addMessage('Vui lòng cung cấp danh sách sản phẩm', 'ERR001', self::$ERROR);
            return $this->json(null, 'views', 422);
        }

        $staffId = Auth::user()['StaffId'] ?? 0;
        $now = Carbon::now();

        $data['CreatedBy'] = $staffId;
        $data['CreatedDate'] = $now;

        try {
            $outboutRequestId = $this->orderRequestRepo->createDeliveryFromDemand($data);

            $this->addMessage('Tạo phiếu giao hàng thành công', 'SUC001', self::$SUCCESS);
            $response[] = $this->formatData('OutboutRequestId', ['OutboutRequestId' => $outboutRequestId]);

            return $this->json($response, 'views', 201);

        } catch (\Exception $e) {
            $this->addMessage($e->getMessage(), 'ERR999', self::$ERROR);
            return $this->json(null, 'views', 201);
        }
    }

    /**
     * POST /order-request/delivery-list
     * Lấy danh sách phiếu giao hàng
     */
    public function getDeliveryList(Request $request)
    {
        $conditions = $request->all();
        $list = $this->outboutRequestRepo->getDeliveryList($conditions);

        $response[] = $this->formatPagination('DeliveryList', $list, 'Grid');
        return $this->json($response);
    }

    /**
     * POST /order-request/delivery-detail
     * Lấy chi tiết phiếu giao hàng
     */
    public function getDeliveryDetail(Request $request)
    {
        $id = (int) $request->input('OutboutRequestId');

        if (!$id) {
            $this->addMessage('Vui lòng cung cấp OutboutRequestId', 'ERR001', self::$ERROR);
            return $this->json(null, 'views', 422);
        }

        $detail = $this->outboutRequestRepo->getDeliveryDetail($id);

        if (!$detail) {
            $this->addMessage('Không tìm thấy phiếu giao hàng', 'ERR003', self::$ERROR);
            return $this->json(null, 'views', 404);
        }

        $response[] = $this->formatData('DeliveryDetail', $detail);
        return $this->json($response);
    }

    /**
     * POST /order-request/confirm-delivery
     * Xác nhận phiếu giao hàng
     */
    public function confirmDelivery(Request $request)
    {
        $id = (int) $request->input('OutboutRequestId');
        $branchNote = $request->input('BranchNote', NULL);
        $details = $request->input('Details', []);

        if (!$id) {
            $this->addMessage('Vui lòng cung cấp OutboutRequestId', 'ERR001', self::$ERROR);
            return $this->json(null, 'views', 422);
        }

        if (empty($details) || !is_array($details)) {
            $this->addMessage('Vui lòng cung cấp chi tiết số lượng nhận', 'ERR001', self::$ERROR);
            return $this->json(null, 'views', 422);
        }

        $staffId = Auth::user()['StaffId'] ?? 0;

        try {
            $this->outboutRequestRepo->confirmDelivery($id, $branchNote, $details, $staffId);

            $this->addMessage('Xác nhận phiếu giao hàng thành công', 'SUC001', self::$SUCCESS);
            
            // Lấy lại chi tiết sau khi xác nhận
            $detail = $this->outboutRequestRepo->getDeliveryDetail($id);
            $response[] = $this->formatData('DeliveryDetail', $detail);

            return $this->json($response);

        } catch (\Exception $e) {
            $this->addMessage($e->getMessage(), 'ERR999', self::$ERROR);
            return $this->json(false, 'views', 200);
        }
    }
}
