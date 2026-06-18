<?php

namespace App\InsuranceHub\Providers\BaoMinh;

use App\InsuranceHub\DTOs\ActionDto;
use App\InsuranceHub\DTOs\RequestResultDto;
use App\InsuranceHub\EnumStatus;
use App\InsuranceProviderBranch;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * Business logic layer — được gọi static từ Provider.
 * Nhận ApiClient đã khởi tạo, gọi xuống ApiClient cho mỗi nghiệp vụ.
 */
class RequestRepository
{
    // ─── Tạo yêu cầu BLVP ────────────────────────────────────────────────────

    public static function createRequest(ApiClient $client, array $data): RequestResultDto
    {
        $branchId    = (int) ($data['branchId'] ?? 0);
        $branch      = $branchId ? InsuranceProviderBranch::where('BranchId', $branchId)->where('IsActive', 1)->first() : null;
        $medicalCode = $branch->medicalCode ?? '';

        if (empty($medicalCode)) {
            throw new RuntimeException('ProviderBranchCode (medicalCode BaoMinh) chưa được cấu hình cho chi nhánh này.');
        }

        static::verifyMember($client, $data['memberCode'] ?? '');

        $treatments      = $data['treatments'] ?? [];
        $treatmentCode   = static::generateTreatmentCode((int) ($data['insuranceRequestsId'] ?? 0) ?: null);
        $inDate          = static::toIsoDateTime($data['inDate'] ?? '');
        $outDate         = static::toIsoDateTime($data['outDate'] ?? $data['inDate'] ?? '');
        $estimatedAmount = static::sumTreatmentAmount($treatments);
        $icdAggregated   = static::aggregateIcd($treatments);
        $diagnosisNames  = implode(';', array_unique(array_filter(array_map(function ($t) { return trim($t['DiagnosisName'] ?? ''); }, $treatments))));
        $serviceDesc     = implode(';', array_map(function ($t) {
            $parts = [trim($t['ServiceName'] ?? '')];
            if (!empty($t['AnatomyBodyPartItemName'])) {
                $parts[] = trim($t['AnatomyBodyPartItemName']);
            }
            $parts[] = '(' . number_format((float) ($t['TotalAmount'] ?? 0), 0, '.', ',') . ' VND)';
            return implode(' ', $parts);
        }, $treatments));

        $payload = [
            'requestId'      => Uuid::uuid4()->toString(),
            'insuranceInfo'  => [
                'medicalCode' => $medicalCode,
                'memberCode'  => $data['memberCode'] ?? '',
            ],
            'contactPhone'   => $data['contactPhone'] ?? '',
            'contactemail'   => $data['contactEmail'] ?? '',
            'treatment'      => [
                'treatmentCode'     => $treatmentCode,
                'treatmentTypeCode' => $data['treatmentTypeCode'] ?? 'D',
                'inDate'            => $inDate,
                'outDate'           => $outDate,
                'treatmentDate'     => $inDate,
                'insuredEventDate'  => $inDate,
                'icdCode'           => $icdAggregated['primary'] ?: '000',
                'icdName'           => $diagnosisNames,
                'icdSubCodes'       => $icdAggregated['subCodes'],
                'icdSubText'        => $icdAggregated['subText'],
                'description'       => $serviceDesc,
                'medicalHistory'    => '',
                'doctor'            => $data['doctor'] ?? '',
            ],
            'estimate'       => [
                'estimatedAmount' => $estimatedAmount,
                'currency'        => 'VND',
                'estimatedDays'   => (int) ($data['estimatedDays'] ?? 1),
            ],
            'signedPdfBase64' => $data['signedPdfBase64'] ?? '',
        ];

        $result = $client->createGuaranteeRequest($payload);

        $success = $result['Success'] ?? $result['success'] ?? false;
        if (!$success) {
            throw new RuntimeException('Không gửi được yêu cầu: ' . json_encode($result['Messages'] ?? $result['messages'] ?? $result));
        }

        $payloadForStorage                   = $payload;
        $payloadForStorage['signedPdfBase64'] = !empty($payload['signedPdfBase64']) ? 'có gửi file' : '';

        return new RequestResultDto([
            'providerRequestId' => $result['Model']['Code'] ?? $result['model']['code'] ?? '',
            'treatmentCode'     => $treatmentCode,
            'unifiedStatus'     => EnumStatus::SUBMITTED,
            'estimatedAmount'   => $estimatedAmount,
            'payload'           => $payloadForStorage,
        ]);
    }

    // ─── Chi tiết yêu cầu BLVP ────────────────────────────────────────

    public static function getClaimDetail(ApiClient $client, string $providerRequestId, $insuranceRequest): array
    {
        if (empty($providerRequestId)) {
            return $insuranceRequest->toArray();
        }

        $detail = $client->getClaimDetail($providerRequestId);
        $merged = $insuranceRequest->toArray();
        $merged['Notes']              = $detail['Notes']              ?? null;
        $merged['DocumentGuarantees'] = $detail['DocumentGuarantees'] ?? null;
        $merged['MedicalCode']        = $detail['MedicalCode']        ?? null;
        $merged['MedicalName']        = $detail['MedicalName']        ?? null;
        $merged['EditInfor']          = null;
        $merged['IsSendDocument']     = false;
        $merged['EditInforDesc']      = '';
        $merged['Stage']              = null;
        $merged['IsdocumentResult']   = null;

        $result = $client->queryHospital([
            'Type'      => 'REQUESTCLAIMINIT',
            'CiCodeRef' => $providerRequestId,
            'Page'      => '1',
            'Limit'     => '1',
        ]);
        $items = $result['Model'] ?? $result['model'] ?? [];
        if (empty($items)) {
            return $merged;
        }
        $item = is_array($items) ? $items[0] : $items;
        if(($item['StatusName'] ?? '') == 'Kết thúc') {
            $resultClaim = $client->queryHospital([
                'Type'      => 'REQUESTCLAIM',
                'CiCodeRef' => $providerRequestId,
                'Page'      => '1',
                'Limit'     => '1',
            ]);
            $itemCLaims = $resultClaim['Model'] ?? $resultClaim['model'] ?? [];
            if (!empty($itemCLaims)) {
                $itemCLaim = is_array($itemCLaims) ? $itemCLaims[0] : $itemCLaims;
                $merged['IsdocumentResult'] = $itemCLaim['IsdocumentResult'] ?? null;
            }
        }

        $merged['EditInfor']          = $item['EditInfor'] ?? false;
        $merged['IsSendDocument']   = $item['IsSendDocument'] ?? false;
        $merged['EditInforDesc']   = $item['EditInforDesc'] ?? '';
        $merged['Stage']   = $item['Stage'] ?? null;

        return $merged;
    }

    // ─── Danh sách yêu cầu từ BaoMinh ────────────────────────────────────────

    public static function queryRequestList(ApiClient $client, array $filters = []): array
    {
        $result = $client->queryHospital([
            'Type'      => $filters['type']      ?? 'REQUESTCLAIMINIT',
            'Statuses'  => $filters['statuses']  ?? [],
            'Page'      => (string) ($filters['page']     ?? 1),
            'Limit'     => (string) ($filters['limit']    ?? 10),
            'SendFrom'  => $filters['sendFrom']  ?? '',
            'SendTo'    => $filters['sendTo']    ?? '',
            'CiCodeRef' => $filters['ciCodeRef'] ?? '',
            'MemCode'   => $filters['memCode']   ?? '',
            'MemName'   => $filters['memName']   ?? '',
        ]);

        return [
            'total' => $result['Count'] ?? $result['count'] ?? count($result['Model'] ?? $result['model'] ?? []),
            'items' => $result['Model'] ?? $result['model'] ?? [],
        ];
    }

    // ─── Các actions khả dụng theo unified status ─────────────────────────────

    public static function availableActions($insuranceRequest): array
    {
        $id = $insuranceRequest['InsuranceRequestsId'];
        $unifiedStatus = is_array($insuranceRequest) ? ($insuranceRequest['UnifiedStatus'] ?? '') : $insuranceRequest->UnifiedStatus;
        $actionKeys = ProviderConfig::availableActions()[$unifiedStatus] ?? [];
        $meta       = ProviderConfig::actionMeta();
        $actions    = [];
        $canEdit  = ($insuranceRequest['EditInfor'] ?? false) === true;
        $isDocumentResult = $insuranceRequest['IsdocumentResult'] ?? null;
        $env = env('APP_ENV') ?? 'production';
        if ($unifiedStatus == EnumStatus::APPROVED) {
            $isSendDocument = ($insuranceRequest['IsSendDocument'] ?? false) === true;
            $stage = $insuranceRequest['Stage'] ?? null;
            // Stage = 1: đã gửi hồ sơ → bỏ submit_request_online và submit_request_hard (có IsSendDocument = true)
            if ((int) $stage !== 1) {
                $actionKeys = array_merge($actionKeys, ['submit_request_online']);
                if ($isSendDocument) {
                    $actionKeys = array_merge($actionKeys, ['submit_request_hard']);
                }
            }

        }
        if ($unifiedStatus == EnumStatus::COMPLETED && $isDocumentResult === true) {
            $actionKeys = array_merge($actionKeys, ['print_pdf']);
        }

        if ($canEdit) {
            $actionKeys = array_merge($actionKeys, ['request_adjust']);
        }


        if($env !== 'production') {
            // Môi trường dev/test cho phép chỉnh sửa nếu có EditInfor = true, bất kể unified status là gì.
            if ($canEdit) {
                $actionKeys = array_merge($actionKeys, ['request_adjust']);
            }
        }

        foreach ($actionKeys as $key) {
            if (!isset($meta[$key])) {
                continue;
            }
            $actions[] = (new ActionDto([
                'action'               => $key,
                'label'                => $meta[$key]['label'],
                'method'               => $meta[$key]['method'],
                'endpoint'             => str_replace('{id}', $id, $meta[$key]['endpoint']),
                'requiresConfirmation' => $meta[$key]['requiresConfirmation'],
            ]))->toArray();
        }

        return $actions;
    }

    // ─── Bổ sung yêu cầu BLVP ────────────────────────────────────────────────

    public static function supplementRequest(ApiClient $client, string $providerRequestId, array $data): void
    {
        $client->supplementGuaranteeRequest($providerRequestId, $data);
    }

    // ─── Hủy yêu cầu BLVP ────────────────────────────────────────────────────

    public static function cancelRequest(ApiClient $client, string $providerRequestId): void
    {
        $client->cancelGuaranteeRequest($providerRequestId);
    }

    // ─── Điều chỉnh yêu cầu BLVP ─────────────────────────────────────────────

    public static function canEdit(ApiClient $client, string $providerRequestId): bool
    {
        if (empty($providerRequestId)) {
            return false;
        }

        $result = $client->queryHospital([
            'Type'      => 'REQUESTCLAIMINIT',
            'CiCodeRef' => $providerRequestId,
            'Page'      => '1',
            'Limit'     => '1',
        ]);

        $items = $result['Model'] ?? $result['model'] ?? [];
        if (empty($items)) {
            return false;
        }

        $item = is_array($items) ? $items[0] : $items;
        return ($item['EditInfor'] ?? false) === true;
    }

    public static function editRequest(ApiClient $client, string $providerRequestId, array $data): void
    {
        $notes     = $data['notes'] ?? '';
        $documents = $data['documents'] ?? [];

        if (empty($notes)) {
            throw new RuntimeException('Nội dung điều chỉnh (notes) không được để trống.');
        }

        $result = $client->editRequest($providerRequestId, $notes, $documents);

        $success = $result['success'] ?? $result['Success'] ?? false;
        if (!$success) {
            throw new RuntimeException('Gửi yêu cầu tới bảo hiểm không thành công.');
        }
    }

    // ─── Nộp hồ sơ quyết toán ────────────────────────────────────────────────

    /**
     * $data['sendType'] = 'ONLINE' | 'HARD'
     * BaoMinh dùng 2 endpoint riêng — routing nội bộ tại đây.
     * Provider khác (PTI, BaoViet) tự implement logic gửi HS của họ.
     */
    public static function submitClaim(ApiClient $client, string $providerRequestId, array $data = []): void
    {
        $sendType = $data['sendType'] ?? 'ONLINE';
        if ($sendType === 'HARD') {
            $result = $client->submitClaimHard($providerRequestId, $data);
        } elseif ($sendType === 'SUPPLEMENTDOCUMENT') {
            $result = $client->submitClaimSupplementDocument($providerRequestId, $data);
        } else {
            $result = $client->submitClaimOnline($providerRequestId, $data);
        }
        $success = $result['Success'] ?? $result['success'] ?? false;
        if (!$success) {
            throw new RuntimeException('Gửi yêu cầu tới bảo hiểm bị từ chối.');
        }
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private static function verifyMember(ApiClient $client, string $memberCode): void
    {
        if (empty($memberCode)) {
            throw new RuntimeException('memberCode không được để trống');
        }

        $result = $client->queryHospital(['Type' => 'MEMBER', 'Code' => $memberCode]);
        $members = $result['Model'] ?? $result['model'] ?? null;

        if (empty($members)) {
            throw new RuntimeException("Không tìm thấy thành viên với mã thẻ [{$memberCode}] trên hệ thống Bảo Minh.");
        }

        $member = is_array($members) ? $members[0] : $members;
        $effectiveFrom = $member['EffectiveFrom'] ?? $member['effectiveFrom'] ?? null;
        $effectiveTo   = $member['EffectiveTo']   ?? $member['effectiveTo']   ?? null;

        if (empty($effectiveFrom) || empty($effectiveTo)) {
            throw new RuntimeException("Thẻ bảo hiểm [{$memberCode}] không có thông tin hiệu lực.");
        }

        $now = new \DateTime();
        $from = new \DateTime($effectiveFrom);
        $to   = new \DateTime($effectiveTo);

        if ($now < $from) {
            throw new RuntimeException("Thẻ bảo hiểm [{$memberCode}] chưa có hiệu lực (hiệu lực từ {$effectiveFrom}).");
        }

        if ($now > $to) {
            throw new RuntimeException("Thẻ bảo hiểm [{$memberCode}] đã hết hiệu lực (hết hiệu lực ngày {$effectiveTo}).");
        }
    }

    private static function generateTreatmentCode(int $id = null): string
    {
        static $cached = null;

        $envMap = ['testing' => 'TEST', 'staging' => 'STG'];
        $envTag = $envMap[env('APP_ENV', 'production')] ?? null;

        if ($id !== null) {
            $parts = array_filter(['NKK', $envTag, date('Y'), str_pad($id, 6, '0', STR_PAD_LEFT)]);
            return implode('-', $parts);
        }

        if ($cached !== null) {
            return $cached;
        }

        $nextId = \DB::selectOne(
            "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'pos' AND TABLE_NAME = 'InsuranceRequests'"
        )->AUTO_INCREMENT ?? ((\App\InsuranceRequest::max('InsuranceRequestsId') ?? 0) + 1);

        $parts  = array_filter(['NKK', $envTag, date('Y'), str_pad($nextId, 6, '0', STR_PAD_LEFT)]);
        $cached = implode('-', $parts);
        return $cached;
    }

    private static function toIsoDateTime(string $date): string
    {
        if (empty($date)) {
            return date('Y-m-d') . 'T00:00:00';
        }
        return date('Y-m-d', strtotime($date)) . 'T00:00:00';
    }

    private static function sumTreatmentAmount(array $treatments): float
    {
        $total = 0.0;
        foreach ($treatments as $t) {
            $total += (float) ($t['TotalAmount'] ?? 0);
        }
        return $total;
    }

    private static function aggregateIcd(array $treatments): array
    {
        $seen        = [];
        $primary     = '';
        $primaryName = '';
        $subCodes    = [];
        $subText     = [];

        foreach ($treatments as $t) {
            $raw  = trim($t['ICD10Code'] ?? '');
            $code = trim(ltrim($raw, ', '));
            $name = trim($t['DiagnosisName'] ?? '');

            if ($code === '' || isset($seen[$code])) {
                continue;
            }

            $seen[$code] = true;

            if ($primary === '') {
                $primary     = $code;
                $primaryName = $name;
            } else {
                $subCodes[] = $code;
                $subText[]  = $name;
            }
        }

        return [
            'primary'     => $primary,
            'primaryName' => $primaryName,
            'subCodes'    => implode(';', $subCodes),
            'subText'     => implode(';', $subText),
        ];
    }
}
