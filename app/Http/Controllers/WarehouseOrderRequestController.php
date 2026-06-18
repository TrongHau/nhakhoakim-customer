<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SearchWarehouseOrderRequestRequest;
use App\Repositories\Interfaces\OrderRequestInterface;
use Illuminate\Http\JsonResponse;

class WarehouseOrderRequestController extends Controller
{
    public function __construct(
        private OrderRequestInterface $orderRequestRepo,
    ) {
        parent::__construct();
    }

    /**
     * GET /warehouse-order-request/list — Danh sách yêu cầu (phía kho trung tâm).
     */
    public function index(SearchWarehouseOrderRequestRequest $request): JsonResponse
    {
        $requests = $this->orderRequestRepo->searchForWarehouse($request->validated());
        $response[] = $this->formatDataPaginationByStore('WarehouseOrderRequestList', $requests, 'Grid');

        return $this->json($response);
    }

    /**
     * GET /warehouse-order-request/detail/{id} — Chi tiết yêu cầu (phía kho).
     */
    public function show(int $id): JsonResponse
    {
        $orderRequest = $this->orderRequestRepo->getDetailForWarehouse($id);

        if (! $orderRequest) {
            $this->addMessage(__('inventory.order_request_not_found'), 'ERR003', self::$ERROR);

            return $this->json(null, 'views', 404);
        }

        $response[] = $this->formatData('WarehouseOrderRequestDetail', $orderRequest);

        return $this->json($response);
    }
}
