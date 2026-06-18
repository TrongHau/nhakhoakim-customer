<?php

namespace App\Http\Controllers\InsuranceHub;

use App\Http\Controllers\Controller;
use App\InsuranceHub\EnumStatus;
use App\InsuranceHub\InsuranceDriver;
use App\Repositories\InsuranceClaimRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InsuranceClaimController extends Controller
{
    /** @var InsuranceClaimRepository */
    private $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new InsuranceClaimRepository();
    }

    // GET /customer/insurance-hub/claims
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page'      => 'nullable|integer|min:1',
            'pageSize'  => 'nullable|integer|min:1|max:100',
            'status'    => 'nullable|string',
            'fromDate'  => 'nullable|date_format:Y-m-d',
            'toDate'    => 'nullable|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $page     = (int) $request->input('page', 1);
        $pageSize = (int) $request->input('pageSize', 20);
        $filters  = $request->only(['status', 'fromDate', 'toDate']);

        try {
            $paginator = $this->repo->list($filters, $page, $pageSize);
            $results[] = $this->formatPagination('InsuranceClaimList', $paginator);
            return $this->json($results, 'views');
        } catch (\Exception $e) {
            $this->addMessage($e->getMessage(), 'ERR500', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    // GET /customer/insurance-hub/claims/:id
    public function show(Request $request, int $id)
    {
        try {
            $claim = $this->repo->findWithRelations($id);

            if (!$claim) {
                $this->addMessage('Không tìm thấy hồ sơ quyết toán.', 'ERR404', self::$ERROR);
                return $this->json(false, 'bool');
            }

            $results[] = $this->formatData('InsuranceClaim', $claim->toArray(), 'Grid');
            return $this->json($results, 'views');
        } catch (\Exception $e) {
            $this->addMessage($e->getMessage(), 'ERR500', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    // POST /customer/insurance-hub/claims/:id/submit
    public function submit(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'sendType' => 'required|in:ONLINE,HARD',
            'sendDate' => 'nullable|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        try {
            $staffId  = $this->resolveStaffId($request);
            $claim    = $this->repo->find($id);
            $sendType = $request->input('sendType');

            if (!$claim) {
                $this->addMessage('Không tìm thấy hồ sơ quyết toán.', 'ERR404', self::$ERROR);
                return $this->json(false, 'bool');
            }

            try {
                InsuranceDriver::for($claim->ProviderCode)
                    ->submitClaim($claim->ProviderClaimId ?? '', $request->all());
            } catch (\Exception $e) {
                // API chưa cấp — chỉ lưu local
            }

            $extra = ['SendType' => $sendType, 'SentAt' => Carbon::now()->toDateTimeString()];
            if ($sendType === 'HARD' && $request->input('sendDate')) {
                $extra['SendDate'] = $request->input('sendDate');
            }

            $this->repo->updateStatus($claim, EnumStatus::CLAIM_SENT, 'Đã gửi HS', $extra);

            $note = $sendType === 'HARD'
                ? 'Gửi HS bản cứng' . ($request->input('sendDate') ? ' ngày ' . $request->input('sendDate') : '')
                : 'Gửi HS online';

            $this->repo->addHistory($claim->Id, EnumStatus::CLAIM_SENT, 'Đã gửi HS', $staffId, $note);

            $results[] = $this->formatData('InsuranceClaim', $claim->fresh()->toArray(), 'Grid');
            return $this->json($results, 'views');
        } catch (\Exception $e) {
            $this->addMessage($e->getMessage(), 'ERR500', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    // POST /customer/insurance-hub/claims/:id/attachments
    public function addAttachment(Request $request, int $id)
    {
        try {
            if (!$this->repo->find($id)) {
                $this->addMessage('Không tìm thấy hồ sơ quyết toán.', 'ERR404', self::$ERROR);
                return $this->json(false, 'bool');
            }

            // Placeholder — tích hợp storage service thực tế
            $results[] = $this->formatData('InsuranceClaimAttachment', ['uploaded' => true], 'Grid');
            return $this->json($results, 'views');
        } catch (\Exception $e) {
            $this->addMessage($e->getMessage(), 'ERR500', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function resolveStaffId(Request $request): int
    {
        return (int) $request->input('CurrentStaffId', 0);
    }
}
