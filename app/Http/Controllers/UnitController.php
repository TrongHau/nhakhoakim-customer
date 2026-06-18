<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repositories\UnitRepository;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    protected $unitRepo;

    public function __construct(UnitRepository $unitRepo)
    {
        parent::__construct();
        $this->unitRepo = $unitRepo;
    }

    /**
     * POST /unit/list — Danh sách đơn vị tính.
     */
    public function index(Request $request)
    {
        $units = $this->unitRepo->getActiveList();
        $response[] = $this->formatData('UnitList', $units);

        return $this->json($response);
    }
}
