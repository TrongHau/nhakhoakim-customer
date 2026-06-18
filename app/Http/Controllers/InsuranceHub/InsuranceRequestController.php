<?php

namespace App\Http\Controllers\InsuranceHub;

use App\Http\Controllers\Controller;
use App\InsuranceHub\EnumStatus;
use App\InsuranceHub\InsuranceDriver;
use App\Libs\Helper;
use App\Repositories\CustomerRepository;
use App\Repositories\InsuranceClaimRepository;
use App\Repositories\InsuranceRequestRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class InsuranceRequestController extends Controller
{
    /** @var InsuranceRequestRepository */
    private $repo;
    /** @var CustomerRepository */
    private $customerRepo;

    public function __construct()
    {
        parent::__construct();
        $this->repo         = new InsuranceRequestRepository();
        $this->customerRepo = new CustomerRepository();
    }

    // GET /customer/insurance-hub/requests
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page'       => 'nullable|integer|min:1',
            'pageSize'   => 'nullable|integer|min:1|max:100',
            'status'     => 'nullable|string|in:inReview,pendingInfo,approved,rejected',
            'companyId'  => 'nullable|integer',
            'memberCode' => 'nullable|string',
            'fromDate'   => 'nullable|date_format:Y-m-d',
            'toDate'     => 'nullable|date_format:Y-m-d',
            'BranchId'   => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $page     = (int) $request->input('page', 1);
        $pageSize = (int) $request->input('pageSize', 20);
        $filters  = $request->only(['status', 'companyId', 'memberCode', 'fromDate', 'toDate', 'BranchId']);

        try {
            $summary   = $this->repo->summary($filters);
            $paginator = $this->repo->list($filters, $page, $pageSize);

            $results[] = $this->formatData('InsuranceRequestSummary', $summary, 'Grid');
            $results[] = $this->formatPagination('InsuranceRequestList', $paginator);

            return $this->json($results, 'views');
        } catch (\Exception $e) {
            $this->addMessage($e->getMessage(), 'ERR500', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    // POST /customer/insurance-hub/requests
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CurrentStaffId'      => 'required|integer',
            'CompanyId'           => 'required|integer',
            'CustomerId'          => 'required|integer',
            'TreatmentId'         => 'required|integer',
            'CustomerInsuranceId' => 'required|integer',
            'ServiceIds'          => 'required|array|min:1',
            'ServiceIds.*'        => 'required|integer',
            'signedPdfBase64'     => 'nullable|string',
            'Status'              => 'nullable|string|in:DRAFT,CREATE',
            'InsuranceRequestId'  => 'nullable|integer',
            'Note'                => 'nullable|string',
        ]);

        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        try {
            $status              = strtoupper($request->input('Status', 'CREATE'));
            $insuranceRequestId  = (int) $request->input('InsuranceRequestId', 0);
            $note                = $request->input('Note');
            $branchId            = $this->resolveBranchId();
            $staffId             = (int) $request->input('CurrentStaffId');
            $companyId           = (int) $request->input('CompanyId');
            $customerId          = (int) $request->input('CustomerId');
            $treatmentId         = (int) $request->input('TreatmentId');
            $customerInsuranceId = (int) $request->input('CustomerInsuranceId');
            $serviceIds          = array_map('intval', $request->input('ServiceIds'));

            // Lấy thông tin thẻ bảo hiểm
            $insurance = $this->customerRepo->getCustomerInsuranceById($customerInsuranceId);
            if (!$insurance || (int) $insurance->CustomerId !== $customerId) {
                $this->addMessage('Thẻ bảo hiểm không hợp lệ hoặc không thuộc khách hàng này.', 'ERR001', self::$ERROR);
                return $this->json(false, 'bool');
            }

            $today    = \Carbon\Carbon::today();
            $fromDate = $insurance->FromDate ? \Carbon\Carbon::parse($insurance->FromDate)->startOfDay() : null;
            $toDate   = $insurance->ToDate   ? \Carbon\Carbon::parse($insurance->ToDate)->startOfDay()  : null;

            if ($fromDate && $today->lt($fromDate)) {
                $this->addMessage('Thẻ bảo hiểm chưa có hiệu lực.', 'ERR001', self::$ERROR);
                return $this->json(false, 'bool');
            }
            if ($toDate && $today->gt($toDate)) {
                $this->addMessage('Thẻ bảo hiểm đã hết hạn.', 'ERR001', self::$ERROR);
                return $this->json(false, 'bool');
            }

            // Lấy thông tin khách hàng
            $customer = $this->customerRepo->getCustomerBasicInfo($customerId);
            if (!$customer) {
                $this->addMessage('Không tìm thấy thông tin khách hàng.', 'ERR001', self::$ERROR);
                return $this->json(false, 'bool');
            }

            // Lấy ProviderCode từ CompanyId
            $credential = \App\InsuranceProviderCredential::where('CompanyId', $companyId)
                ->where('IsActive', 1)
                ->first();
            if (!$credential) {
                $this->addMessage('Không tìm thấy nhà bảo hiểm cho công ty này.', 'ERR001', self::$ERROR);
                return $this->json(false, 'bool');
            }
            $providerCode = $credential->ProviderCode;
            $treatments   = $this->customerRepo->getSelectedTreatmentServices($customerId, $treatmentId, $serviceIds);
            $doctor       = $this->customerRepo->getDoctorByTreatmentId($treatmentId);

            $driver = InsuranceDriver::for($providerCode);

            // ── DRAFT: chỉ lưu DB, không gửi API nhà cung cấp ───────────────
            if ($status === 'DRAFT') {
                $insuranceRequest = $this->repo->store([
                    'BranchId'      => $branchId,
                    'CompanyId'     => $companyId,
                    'ProviderCode'  => $providerCode,
                    'CustomerId'    => $customerId,
                    'CustomerCode'  => $customer->CustomerCode ?? '',
                    'MemberCode'    => $insurance->InsuranceCode,
                    'MemberName'    => $customer->FullName ?? '',
                    'FromDate'      => $insurance->FromDate,
                    'ToDate'        => $insurance->ToDate,
                    'TreatmentType' => 'D',
                    'UnifiedStatus' => EnumStatus::DRAFT,
                    'Treatments'    => $treatments,
                    'CreatedBy'     => $staffId,
                    'ServiceIds'    => $serviceIds,
                    'Note'          => $note,
                ]);

                $this->repo->addHistory($insuranceRequest->InsuranceRequestsId, EnumStatus::DRAFT, $staffId, 'Lưu nháp yêu cầu BLVP');

                $results[] = $this->formatData('InsuranceRequest', $insuranceRequest->toArray(), 'Grid');
                $this->addMessage('Tạo yêu cầu nháp thành công', 'CR0002', self::$SUCCESS);
                return $this->json(true, 'bool');
            }

            // ── CREATE: gửi API nhà cung cấp ─────────────────────────────────

            $apiData = [
                'branchId'          => $branchId,
                'memberCode'        => $insurance->InsuranceCode,
                'contactPhone'      => $customer->PhoneNumber ?? '',
                'contactEmail'      => '',
                'treatmentTypeCode' => 'D',
                'inDate'            => date('Y-m-d'),
                'outDate'           => date('Y-m-d'),
                'estimatedDays'     => 1,
                'treatments'        => $treatments,
                'doctor'            => $doctor,
                'signedPdfBase64'   => $request->input('signedPdfBase64', ''),
            ];

            // Nếu có InsuranceRequestId → nâng từ nháp lên
            if ($insuranceRequestId > 0) {
                $existingRequest = \App\InsuranceRequest::where('InsuranceRequestsId', $insuranceRequestId)
                    ->where('CompanyId', $companyId)
                    ->where('CustomerId', $customerId)
                    ->where('UnifiedStatus', EnumStatus::DRAFT)
                    ->first();

                if (!$existingRequest) {
                    $this->addMessage('Không tìm thấy bản nháp hợp lệ để gửi yêu cầu.', 'ERR001', self::$ERROR);
                    return $this->json(false, 'bool');
                }

                $apiData['insuranceRequestsId'] = $existingRequest->InsuranceRequestsId;

                DB::beginTransaction();
                try {
                    $this->dispatchToProvider($existingRequest, $driver, $apiData, $staffId, 'Gửi yêu cầu BLVP từ bản nháp');
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }

                $this->addMessage('Tạo yêu cầu thành công', 'CR0002', self::$SUCCESS);
                return $this->json(true, 'bool');
            }

            DB::beginTransaction();
            try {
                $insuranceRequest = $this->repo->store([
                    'BranchId'      => $branchId,
                    'CompanyId'     => $companyId,
                    'ProviderCode'  => $providerCode,
                    'CustomerId'    => $customerId,
                    'CustomerCode'  => $customer->CustomerCode ?? '',
                    'MemberCode'    => $insurance->InsuranceCode,
                    'MemberName'    => $customer->FullName ?? '',
                    'FromDate'      => $insurance->FromDate,
                    'ToDate'        => $insurance->ToDate,
                    'TreatmentType' => 'D',
                    'UnifiedStatus' => EnumStatus::DRAFT,
                    'Treatments'    => $treatments,
                    'CreatedBy'     => $staffId,
                    'ServiceIds'    => $serviceIds,
                    'Note'          => $note,
                ]);

                $apiData['insuranceRequestsId'] = $insuranceRequest->InsuranceRequestsId;

                $this->dispatchToProvider($insuranceRequest, $driver, $apiData, $staffId, 'Tạo yêu cầu BLVP');

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            $this->addMessage('Tạo yêu cầu thành công', 'CR0002', self::$SUCCESS);
            return $this->json(true, 'bool');
        } catch (\Exception $e) {
            $this->addMessage($e->getMessage(), 'ERR500', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    // POST /customer/insurance-hub/requests/delete
    public function deleteDraft(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'InsuranceRequestId' => 'required|integer',
        ]);

        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        try {
            $insuranceRequest = \App\InsuranceRequest::where('InsuranceRequestsId', (int) $request->input('InsuranceRequestId'))
                ->where('UnifiedStatus', EnumStatus::DRAFT)
                ->first();

            if (!$insuranceRequest) {
                $this->addMessage('Không tìm thấy yêu cầu nháp hoặc yêu cầu không ở trạng thái Nháp.', 'ERR404', self::$ERROR);
                return $this->json(false, 'bool');
            }

            DB::transaction(function () use ($insuranceRequest) {
                \App\InsuranceRequestHistory::where('InsuranceRequestId', $insuranceRequest->InsuranceRequestsId)->delete();
                DB::table('InsuranceRequestServices')
                    ->where('InsuranceRequestId', $insuranceRequest->InsuranceRequestsId)
                    ->delete();
                $insuranceRequest->delete();
            });

            return $this->json(true, 'bool');
        } catch (\Exception $e) {
            $this->addMessage($e->getMessage(), 'ERR500', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    // GET /customer/insurance-hub/requests/:id
    public function show(Request $request, int $id)
    {
        try {
            $insuranceRequest = $this->repo->findWithRelations($id);

            if (!$insuranceRequest) {
                $this->addMessage('Không tìm thấy yêu cầu.', 'ERR404', self::$ERROR);
                return $this->json(false, 'bool');
            }

            $workflow = [];
            $actions  = [];
            $driver = null;
            try {
                $driver = InsuranceDriver::for($insuranceRequest->ProviderCode);
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Lỗi không kết nối được đối tác'
                );
            }

            if ($insuranceRequest->UnifiedStatus !== EnumStatus::DRAFT
                && !empty($insuranceRequest->ProviderRequestId)
                && isset($driver)) {
                try {
                    $insuranceRequest = $driver->getClaimDetail($insuranceRequest->ProviderRequestId, $insuranceRequest);
                } catch (\Exception $e) {
                    throw new RuntimeException(
                        'Không lấy được thông tin chi tiết từ nhà bảo hiểm'
                    );
                }
            }
            $workflow      = $driver->getRequestWorkflow();
            $actions = $driver->getAvailableActions($insuranceRequest);

            $raw = is_array($insuranceRequest) ? $insuranceRequest : $insuranceRequest->toArray();

            $companyName      = $insuranceRequest->partnerCompany->Name ?? null;
            $companyShortName = $insuranceRequest->partnerCompany->ShortName ?? null;

            unset($raw['insurance_company'], $raw['partner_company']);

            $data = array_merge($raw, [
                'CompanyName'      => $companyName,
                'CompanyShortName' => $companyShortName,
                'ProviderStatus'   => EnumStatus::label($raw['UnifiedStatus'] ?? ''),
                'workflow'         => $workflow,
                'availableActions' => $actions,
            ]);
            unset($data['Payload']);
            $results[] = $this->formatData('InsuranceRequest', $data, 'Grid');
            return $this->json($results, 'views');
        } catch (\Exception $e) {
            $this->addMessage($e->getMessage(), 'ERR500', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    // POST /customer/insurance-hub/requests/:id/submit
    public function submit(Request $request, int $id)
    {
        try {
            $staffId          = $this->resolveStaffId($request);
            $insuranceRequest = $this->repo->find($id);

            if (!$insuranceRequest) {
                $this->addMessage('Không tìm thấy yêu cầu.', 'ERR404', self::$ERROR);
                return $this->json(false, 'bool');
            }

            InsuranceDriver::for($insuranceRequest->ProviderCode);

            $this->repo->updateStatus($insuranceRequest, EnumStatus::SUBMITTED);
            $this->repo->addHistory($id, EnumStatus::SUBMITTED, $staffId, 'Gửi yêu cầu lên nhà bảo hiểm');

            $results[] = $this->formatData('InsuranceRequest', $insuranceRequest->fresh()->toArray(), 'Grid');
            return $this->json($results, 'views');
        } catch (\Exception $e) {
            $this->addMessage($e->getMessage(), 'ERR500', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    // DELETE /customer/insurance-hub/requests/:id/cancel
    public function cancel(Request $request, int $id)
    {
        try {
            $staffId          = $this->resolveStaffId($request);
            $insuranceRequest = $this->repo->find($id);

            if (!$insuranceRequest) {
                $this->addMessage('Không tìm thấy yêu cầu.', 'ERR404', self::$ERROR);
                return $this->json(false, 'bool');
            }

            try {
                InsuranceDriver::for($insuranceRequest->ProviderCode)
                    ->cancelRequest($insuranceRequest->ProviderRequestId ?? '');
            } catch (\Exception $e) {
                // API chưa cấp — chỉ lưu local
            }

            $this->repo->updateStatus($insuranceRequest, EnumStatus::CANCELLED);
            $this->repo->addHistory($id, EnumStatus::CANCELLED, $staffId, $request->input('reason', 'Hủy yêu cầu'));

            return $this->json(true, 'bool');
        } catch (\Exception $e) {
            $this->addMessage($e->getMessage(), 'ERR500', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    // POST /customer/insurance-hub/requests/:id/edit
    public function edit(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'notes'              => 'required|string',
            'documents'          => 'nullable|array',
            'documents.*.Name'   => 'required|string',
            'documents.*.Type'   => 'required|string',
            'documents.*.File'   => 'required|string',
        ]);

        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        try {
            $staffId          = $this->resolveStaffId($request);
            $insuranceRequest = $this->repo->find($id);

            if (!$insuranceRequest) {
                $this->addMessage('Không tìm thấy yêu cầu.', 'ERR404', self::$ERROR);
                return $this->json(false, 'bool');
            }

            if (empty($insuranceRequest->ProviderRequestId)) {
                $this->addMessage('Yêu cầu chưa được gửi đến nhà bảo hiểm.', 'ERR001', self::$ERROR);
                return $this->json(false, 'bool');
            }

            $driver = InsuranceDriver::for($insuranceRequest->ProviderCode);

//            if (!$driver->canEdit($insuranceRequest->ProviderRequestId)) {
//                $this->addMessage('Yêu cầu này không được phép chỉnh sửa.', 'ERR001', self::$ERROR);
//                return $this->json(false, 'bool');
//            }

            $driver->editRequest($insuranceRequest->ProviderRequestId, [
                'notes'     => $request->input('notes'),
                'documents' => $request->input('documents', []),
            ]);
            $insuranceRequest->EditedBy = $staffId;
            $insuranceRequest->save();


            $this->repo->addHistory($id, EnumStatus::ADJUSTING, $staffId, $request->input('notes'));
            $this->addMessage('Chỉnh sửa yêu cầu thành công');
            return $this->json(true, 'bool');
        } catch (\Exception $e) {
            $this->addMessage($e->getMessage(), 'ERR500', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    public function submitClaim(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'sendType'               => 'required|in:ONLINE,HARD,SUPPLEMENTDOCUMENT',
            'notes'                  => 'nullable|string',
            'documents'              => 'required_if:sendType,ONLINE,SUPPLEMENTDOCUMENT|array|min:1',
            'documents.*.fileName'   => 'required_if:sendType,ONLINE,SUPPLEMENTDOCUMENT|string',
            'documents.*.type'       => 'required_if:sendType,ONLINE|in:RS,IV,OT',
            'documents.*.file'       => 'required_if:sendType,ONLINE,SUPPLEMENTDOCUMENT|string',
            'sendDate'               => 'required_if:sendType,HARD|nullable|string',
        ]);

        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        try {
            $staffId          = $this->resolveStaffId($request);
            $insuranceRequest = $this->repo->find($id);

            if (!$insuranceRequest) {
                $this->addMessage('Không tìm thấy yêu cầu.', 'ERR404', self::$ERROR);
                return $this->json(false, 'bool');
            }

            $sendType = $request->input('sendType');

            $driver = InsuranceDriver::for($insuranceRequest->ProviderCode);
            $driver->submitClaim($insuranceRequest->ProviderRequestId, $request->all());

            $extra = ['EditedBy' => $staffId];

            $UnifiedStatus = EnumStatus::CLAIM_SENT;
            if ($sendType === 'SUPPLEMENTDOCUMENT') {
                $note          = 'Bổ sung chứng từ';
                $UnifiedStatus = EnumStatus::CLAIM_SUPPLEMENTED;
            } elseif ($sendType === 'HARD') {
                $note          = 'Gửi HS bản cứng' . ($request->input('sendDate') ? ' ngày ' . $request->input('sendDate') : '');
            } else {
                $note          = 'Gửi HS online';
            }

            $this->repo->updateStatus($insuranceRequest, $UnifiedStatus, $extra);
            $this->repo->addHistory($id, $UnifiedStatus, $staffId, $note);

            $this->addMessage('Đã gửi ' . mb_strtolower($note, 'UTF-8') . ' thành công');
            return $this->json(true, 'bool');
        } catch (\Exception $e) {
            $this->addMessage($e->getMessage(), 'ERR500', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    // GET /customer/insurance-hub/requests/:id/confirmation-pdf
    public function confirmationPdf(Request $request, int $id)
    {
        try {
            $insuranceRequest = $this->repo->find($id);

            if (!$insuranceRequest) {
                $this->addMessage('Không tìm thấy yêu cầu.', 'ERR404', self::$ERROR);
                return $this->json(false, 'bool');
            }

            if (empty($insuranceRequest->ProviderRequestId)) {
                $this->addMessage('Yêu cầu chưa có mã bảo lãnh từ nhà bảo hiểm.', 'ERR001', self::$ERROR);
                return $this->json(false, 'bool');
            }
            $file = InsuranceDriver::for($insuranceRequest->ProviderCode)
                ->getGuaranteeFile($insuranceRequest->ProviderRequestId);

            $fileContent = $file['File']     ?? '';
            $fileName    = $file['FileName'] ?? 'confirmation.pdf';

            if (empty($fileContent)) {
                $this->addMessage('Không có dữ liệu file từ nhà bảo hiểm.', 'ERR404', self::$ERROR);
                return $this->json(false, 'bool');
            }

            $decoded = base64_decode($fileContent);
            return response($decoded)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $fileName . '"')
                ->header('Content-Length', strlen($decoded));
        } catch (\Exception $e) {
            $this->addMessage($e->getMessage(), 'ERR500', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function dispatchToProvider($record, $driver, array $apiData, int $staffId, string $historyNote): void
    {
        $result = $driver->createRequest($apiData);

        $this->repo->updateStatus($record, $result->unifiedStatus, [
            'ProviderRequestId' => $result->providerRequestId,
            'TreatmentCode'     => $result->treatmentCode,
            'EstimatedAmount'   => $result->estimatedAmount,
            'Payload'           => $result->payload,
            'Treatments'        => $apiData['treatments'],
            'EditedBy'          => $staffId,
        ]);

        $this->repo->addHistory($record->InsuranceRequestsId, $result->unifiedStatus, $staffId, $historyNote);
    }

    private function resolveBranchId(): int
    {
        $ipAddress = Helper::getClientIp();
        return (int) $this->customerRepo->checkIpAddress($ipAddress);
    }

    private function resolveStaffId(Request $request): int
    {
        return (int) $request->input('CurrentStaffId', 0);
    }
}
