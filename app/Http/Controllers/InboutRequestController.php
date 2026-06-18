<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repositories\InboutRequestRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InboutRequestController extends Controller
{
    protected $inboutRequestRepo;

    public function __construct(InboutRequestRepository $inboutRequestRepo)
    {
        parent::__construct();
        $this->inboutRequestRepo = $inboutRequestRepo;
    }

    /**
     * POST /inbout-request/list — Danh sách phiếu nhập kho.
     */
    public function index(Request $request)
    {
        $items = $this->inboutRequestRepo->search($request->all());
        $response[] = $this->formatPagination('InboutRequestList', $items, 'Grid');

        return $this->json($response);
    }

    /**
     * POST /inbout-request/detail — Chi tiết phiếu nhập kho.
     */
    public function show(Request $request)
    {
        $id = (int) $request->input('InboutRequestId');
        $inboutRequest = $this->inboutRequestRepo->getDetail($id);

        if (!$inboutRequest) {
            $this->addMessage('Không tìm thấy phiếu nhập kho', 'ERR003', self::$ERROR);
            return $this->json(null, 'views', 404);
        }

        $response[] = $this->formatData('InboutRequestDetail', $inboutRequest);

        return $this->json($response);
    }

    /**
     * POST /inbout-request/create — Tạo phiếu nhập kho.
     */
    public function store(Request $request)
    {
        $data    = $request->all();
        $details = $data['Details'] ?? [];
        unset($data['Details']);

        $data['IRCode']      = $this->inboutRequestRepo->generateCode();
        $data['Status']      = 2;
        $data['CreatedBy']   = Auth::user()['StaffId'] ?? 0;
        $data['CreatedDate'] = Carbon::now();

        $inboutRequestId = $this->inboutRequestRepo->store($data, $details);
        $inboutRequest   = $this->inboutRequestRepo->getDetail($inboutRequestId);

        $response[] = $this->formatData('InboutRequestDetail', $inboutRequest);
        $this->addMessage('Tạo phiếu nhập kho thành công', 'SUC001', self::$SUCCESS);

        return $this->json($response, 'views', 201);
    }
}
