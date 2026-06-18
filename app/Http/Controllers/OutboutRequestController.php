<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repositories\OutboutRequestRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OutboutRequestController extends Controller
{
    protected $outboutRequestRepo;

    public function __construct(OutboutRequestRepository $outboutRequestRepo)
    {
        parent::__construct();
        $this->outboutRequestRepo = $outboutRequestRepo;
    }

    /**
     * POST /outbout-request/list — Danh sách phiếu xuất kho.
     */
    public function index(Request $request)
    {
        $items = $this->outboutRequestRepo->search($request->all());
        $response[] = $this->formatPagination('OutboutRequestList', $items, 'Grid');

        return $this->json($response);
    }

    /**
     * POST /outbout-request/detail — Chi tiết phiếu xuất kho.
     */
    public function show(Request $request)
    {
        $id             = (int) $request->input('OutboutRequestId');
        $outboutRequest = $this->outboutRequestRepo->getDetail($id);

        if (!$outboutRequest) {
            $this->addMessage('Không tìm thấy phiếu xuất kho', 'ERR003', self::$ERROR);
            return $this->json(null, 'views', 404);
        }

        $response[] = $this->formatData('OutboutRequestDetail', $outboutRequest);

        return $this->json($response);
    }

    /**
     * POST /outbout-request/create — Tạo phiếu xuất kho.
     */
    public function store(Request $request)
    {
        $data    = $request->all();
        $details = $data['Details'] ?? [];
        unset($data['Details']);

        $data['OutboutCode']      = $this->outboutRequestRepo->generateCode();
        $data['Status']      = 1;
        $data['CreatedBy']   = Auth::user()['StaffId'] ?? 0;
        $data['CreatedDate'] = Carbon::now();

        $outboutRequestId = $this->outboutRequestRepo->store($data, $details);
        $outboutRequest   = $this->outboutRequestRepo->getDetail($outboutRequestId);

        $response[] = $this->formatData('OutboutRequestDetail', $outboutRequest);
        $this->addMessage('Tạo phiếu xuất kho thành công', 'SUC001', self::$SUCCESS);

        return $this->json($response, 'views', 201);
    }

    /**
     * POST /outbout-request/confirm — Xác nhận nhận hàng.
     */
    public function confirm(Request $request)
    {
        $id             = (int) $request->input('OutboutRequestId');
        $outboutRequest = $this->outboutRequestRepo->find($id);

        if (!$outboutRequest) {
            $this->addMessage('Không tìm thấy phiếu xuất kho', 'ERR003', self::$ERROR);
            return $this->json(null, 'views', 404);
        }

        if ($outboutRequest->Status !== 1) {
            $this->addMessage('Trạng thái phiếu xuất kho không hợp lệ', 'ERR004', self::$ERROR);
            return $this->json(null, 'views', 422);
        }

        $data              = $request->all();
        $data['UpdatedBy'] = Auth::user()['StaffId'] ?? 0;
        unset($data['OutboutRequestId']);

        $this->outboutRequestRepo->confirm($id, $data);

        $this->addMessage('Xác nhận phiếu xuất kho thành công', 'SUC002', self::$SUCCESS);

        return $this->json(null);
    }
}
