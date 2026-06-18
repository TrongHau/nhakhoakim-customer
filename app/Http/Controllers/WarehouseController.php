<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repositories\WarehouseRepository;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    protected $warehouseRepo;

    public function __construct(WarehouseRepository $warehouseRepo)
    {
        parent::__construct();
        $this->warehouseRepo = $warehouseRepo;
    }

    /**
     * POST /warehouse/list — Danh sách kho.
     */
    public function index(Request $request)
    {
        $warehouses = $this->warehouseRepo->getActiveList();
        $response[] = $this->formatData('WarehouseList', $warehouses);

        return $this->json($response);
    }
}
