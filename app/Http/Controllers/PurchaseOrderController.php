<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repositories\PurchaseOrderRepository;
use App\Exports\PurchaseOrderExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PurchaseOrderController extends Controller
{
    protected $purchaseOrderRepo;

    public function __construct(PurchaseOrderRepository $purchaseOrderRepo)
    {
        parent::__construct();
        $this->purchaseOrderRepo = $purchaseOrderRepo;
    }

    /**
     * POST /purchase-order/list — Danh sách PO.
     */
    public function index(Request $request)
    {
        $pos = $this->purchaseOrderRepo->search($request->all());
        $response[] = $this->formatPagination('PurchaseOrderList', $pos, 'Grid');

        return $this->json($response);
    }

    /**
     * POST /purchase-order/detail — Chi tiết PO.
     */
    public function show(Request $request)
    {
        $id = (int) $request->input('PurchaseOrderId');
        $po = $this->purchaseOrderRepo->getDetail($id);

        if (!$po) {
            $this->addMessage('Không tìm thấy đơn đặt hàng', 'ERR003', self::$ERROR);
            return $this->json(null, 'views', 404);
        }

        $response[] = $this->formatData('PurchaseOrderDetail', $po);

        return $this->json($response);
    }

    /**
     * POST /purchase-order/create — Tạo 1 hoặc nhiều PO.
     */
    public function store(Request $request)
    {
        $orders = $request->input('Order', []);
        $orderRequestIds = $request->input('OrderRequestIds', []);

        if (empty($orders)) {
            $this->addMessage('Vui lòng cung cấp thông tin đơn đặt hàng', 'ERR001', self::$ERROR);
            return $this->json(null, 'views', 422);
        }

        $ordersPayload = [];
        foreach ($orders as $order) {
            $data    = $order;
            $details = $data['Details'] ?? [];
            unset($data['Details']);

            if (empty($details)) {
                $this->addMessage('Mỗi đơn đặt hàng phải có ít nhất 1 sản phẩm', 'ERR002', self::$ERROR);
                return $this->json(null, 'views', 422);
            }

            $data['Status']    = 1;
            $data['CreatedBy'] = Auth::user()['StaffId'] ?? 0;

            $ordersPayload[] = ['data' => $data, 'details' => $details];
        }

        $createdIds = $this->purchaseOrderRepo->store($ordersPayload,$orderRequestIds);

        if (empty($createdIds)) {
            $this->addMessage('Tạo đơn đặt hàng thất bại', 'ERR003', self::$ERROR);
            return $this->json(null, 'views', 422);
        }

        $response = [];
        foreach ($createdIds as $id) {
            $po = $this->purchaseOrderRepo->getDetail($id);
            $response[] = $this->formatData('PurchaseOrderDetail', $po);
        }

        $this->addMessage('Tạo đơn đặt hàng thành công', 'SUC001', self::$SUCCESS);

        return $this->json($response, 'views', 201);
    }

    /**
     * POST /purchase-order/update — Cập nhật PO.
     */
    public function update(Request $request)
    {
        $id = (int) $request->input('PurchaseOrderId');
        $po = $this->purchaseOrderRepo->find($id);

        if (!$po) {
            $this->addMessage('Không tìm thấy đơn đặt hàng', 'ERR003', self::$ERROR);
            return $this->json(null, 'views', 404);
        }

        if ($po->Status !== 1) {
            $this->addMessage('Không thể cập nhật đơn đặt hàng này', 'ERR004', self::$ERROR);
            return $this->json(null, 'views', 422);
        }

        $data    = $request->all();
        $details = $data['Details'] ?? [];
        unset($data['Details']);
        unset($data['PurchaseOrderId']);

        $data['UpdatedBy'] = Auth::user()['StaffId'] ?? 0;

        $this->purchaseOrderRepo->updatePO($id, $data, $details);

        $purchaseOrder = $this->purchaseOrderRepo->getDetail($id);
        $response[] = $this->formatData('PurchaseOrderDetail', $purchaseOrder);
        $this->addMessage('Cập nhật đơn đặt hàng thành công', 'SUC002', self::$SUCCESS);

        return $this->json($response);
    }

    /**
     * POST /purchase-order/export — Xuất Excel danh sách PO theo điều kiện filter.
     */
    public function export(Request $request)
    {
        $conditions = $request->all();
        $conditions['limit']   = 10000;
        $conditions['lmstart'] = 0;

        $pos = $this->purchaseOrderRepo->getListForExport($conditions);

        if (empty($pos)) {
            $this->addMessage('Không có dữ liệu để xuất', 'ERR001', self::$ERROR);
            return $this->json(null, 'views', 404);
        }

        try {
            // Đặt tên file theo PurchaseOrderCode nếu chỉ export 1 PO, ngược lại dùng date
            if (count($pos) === 1 && !empty($pos[0]['PurchaseOrderCode'])) {
                $fileExportName = $pos[0]['PurchaseOrderCode'] . '.xlsx';
            } else {
                $fileExportName = 'PO_' . date('Ymd_His') . '.xlsx';
            }
            $filePathExport = storage_path('app/excel') . '/' . $fileExportName;

            if (!file_exists(storage_path('app/excel'))) {
                mkdir(storage_path('app/excel'), 0755, true);
            }

            $exportClass = new PurchaseOrderExport($pos);
            $exportClass->setHeadings([
                'STT', 'Mã đơn hàng', 'Nhà cung cấp', 'Mã vật tư', 'Tên vật tư', 'ĐVT', 'Số lượng',
                'Trạng thái', 'Ngày tạo', 'Ghi chú',
            ])
            ->formatHeadings('A1:J1', 'FFFFFF', '1F7A4D')
            ->setBold('A1:J1')
            ->setAlignment('A1:J1');

            $s3Storage = new \App\Exports\S3ExportStorage();
            $exportClass->setStorage($s3Storage);

            $exportURL = $exportClass
                ->store('excel/' . $fileExportName)
                ->export($filePathExport, 'PurchaseOrder/exports', $fileExportName);

            $exportClass->unlink($filePathExport);

            if (!$exportURL) {
                $this->addMessage('Xuất file thất bại', 'ERR002', self::$ERROR);
                return $this->json(null, 'views', 500);
            }

            $response[] = $this->formatData('PurchaseOrderExport', ['ExportURL' => $exportURL]);
            return $this->json($response);

        } catch (\Exception $e) {
            Log::error('PurchaseOrderController@export: ' . $e->getMessage());
            $this->addMessage('Xuất file thất bại', 'ERR002', self::$ERROR);
            return $this->json(null, 'views', 500);
        }
    }
    public function send(Request $request)
    {
        $id     = (int) $request->input('PurchaseOrderId');
        $result = $this->purchaseOrderRepo->send($id, $request->input('Note'));

        if (!$result) {
            $this->addMessage('Không thể gửi đơn đặt hàng', 'ERR005', self::$ERROR);
            return $this->json(null, 'views', 422);
        }

        $this->addMessage('Gửi đơn đặt hàng thành công', 'SUC003', self::$SUCCESS);

        return $this->json(null);
    }
}
