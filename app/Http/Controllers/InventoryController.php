<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repositories\InventoryRepository;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    protected $inventoryRepo;

    public function __construct(InventoryRepository $inventoryRepo)
    {
        parent::__construct();
        $this->inventoryRepo = $inventoryRepo;
    }

    /**
     * POST /inventory/list — Danh sách tồn kho.
     */
    public function index(Request $request)
    {
        $items = $this->inventoryRepo->search($request->all());
        $response[] = $this->formatPagination('InventoryList', $items, 'Grid');

        return $this->json($response);
    }

    /**
     * POST /inventory/summary — Tổng quan tồn kho.
     */
    public function summary(Request $request)
    {
        $summary = $this->inventoryRepo->getSummary($request->all());
        $response[] = $this->formatData('InventorySummary', $summary);

        return $this->json($response);
    }

    /**
     * POST /inventory/history — Lịch sử giao dịch của 1 inventory item.
     */
    public function history(Request $request)
    {
        $id    = (int) $request->input('InventoryId');
        $items = $this->inventoryRepo->getHistory($id, $request->all());
        $response[] = $this->formatPagination('InventoryHistory', $items, 'Grid');

        return $this->json($response);
    }
}
