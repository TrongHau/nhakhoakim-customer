<?php

namespace App\InsuranceHub\Providers\BaoMinh;

use App\InsuranceHub\CredentialEncryption;
use App\InsuranceProviderCredential;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * API BaoMinh đã cấp:
 *
 *   [✓] 1.  POST /account/sign-in
 *           — Đăng nhập lấy token (token hết hạn ~1 phút → login lại, không có refresh endpoint)
 *
 *   [✓] 2.  POST /PublicApi/claim/nkkim/queryhospital
 *           — Đa năng: Type=MEMBER (tra thành viên) | Type=REQUESTCLAIMINIT (ds yêu cầu BLVP)
 *                                                   | Type=REQUESTCLAIM    (ds yêu cầu quyết toán)
 *
 *   [✓] 3.  POST /PublicApi/claim/NKKIM/GuaranteeRequests
 *           — Tạo yêu cầu bảo lãnh viện phí
 *
 *   [✓] 4.  POST /PublicApi/claim/NKKIM/{cmCode}/Execute  (Type=ONLINE)
 *           — Nộp hồ sơ quyết toán bản online. Điều kiện: BLVP đã xác nhận, Stage ≠ 1
 *
 *   [✓] 5.  POST /PublicApi/claim/NKKIM/{cmCode}/Execute  (Type=HARD)
 *           — Nộp hồ sơ quyết toán bản cứng. Điều kiện: BLVP đã xác nhận, Stage ≠ 1
 *
 *   [✓] 6.  POST /PublicApi/claim/NKKIM/Execute  (Type=INFORMATIONUPDATE)
 *           — Điều chỉnh hồ sơ. Điều kiện: BLVP đã xác nhận, Stage ≠ 1, EditInfor = true
 *
 * Prototype (BaoMinh chưa cấp endpoint):
 *
 *   [?] 7.  ??? /PublicApi/claim/NKKIM/GuaranteeRequests/{ciCode}/cancel
 *   [?] 8.  ??? /PublicApi/claim/NKKIM/GuaranteeRequests/{ciCode}/supplement
 */
class ApiClient
{
    /** @var InsuranceProviderCredential */
    private $credential;

    /** @var Encryption */
    private $encryption;

    /** @var Client */
    private $http;

    /** @var string — BaoMinh token cache trong memory, không lưu DB */
    private $accessToken = '';

    public function __construct(InsuranceProviderCredential $credential, Encryption $encryption)
    {
        $this->credential = $credential;
        $this->encryption = $encryption;
        $this->http       = new Client([
            'base_uri' => rtrim($credential->BaseUrl ?: ProviderConfig::baseUrl(), '/'),
            'timeout'  => 60,
            'verify'   => false,
        ]);
    }

    // ─── [✓] 1. Token ────────────────────────────────────────────────────────

    public function getToken(): string
    {
        if (!empty($this->accessToken)) {
            return $this->accessToken;
        }

        return $this->signIn();
    }

    private function signIn(): string
    {
        $password = CredentialEncryption::decrypt($this->credential->Password, $this->credential->Id);

        Log::info('[BaoMinh] sign-in request', ['username' => $this->credential->Username]);

        try {
            $response = $this->http->post('/account/sign-in', [
                'json' => [
                    'username' => $this->credential->Username,
                    'password' => $password,
                    'loginFor' => ProviderConfig::LOGIN_FOR,
                ],
                'headers' => ['Content-Type' => 'application/json'],
            ]);
        } catch (GuzzleException $e) {
            Log::error('[BaoMinh] sign-in failed', ['error' => $e->getMessage()]);
            throw new RuntimeException('BaoMinh sign-in failed: ' . $e->getMessage());
        }

        $body = json_decode($response->getBody()->getContents(), true);

        $success = $body['success'] ?? $body['Success'] ?? false;
        $token   = $body['token']   ?? $body['Token']   ?? '';

        if (empty($success) || empty($token)) {
            Log::error('[BaoMinh] sign-in rejected', ['response' => $body]);
            throw new RuntimeException('BaoMinh sign-in failed: ' . json_encode($body['messages'] ?? $body['Messages'] ?? $body));
        }

        $tokenExpired = $body['tokenExpired'] ?? $body['TokenExpired'] ?? '';
        Log::info('[BaoMinh] sign-in success', ['tokenExpiresAt' => $tokenExpired]);

        $this->accessToken = $token;

        return $this->accessToken;
    }

    // ─── [✓] 2. Tra cứu thành viên / danh sách yêu cầu ─────────────────────

    /**
     * Đa năng — dùng cho:
     *   Type=MEMBER             → tra thành viên theo Code / SearchName+Dob / PolicyNo / EmpCode / CitizenIdentity
     *   Type=REQUESTCLAIMINIT   → danh sách yêu cầu BLVP
     *   Type=REQUESTCLAIM       → danh sách yêu cầu quyết toán (công nợ)
     */
    public function queryHospital(array $params): array
    {
        return $this->callEncrypted('POST', '/PublicApi/claim/nkkim/queryhospital', $params);
    }

    // ─── [✓] 7.8. Chi tiết yêu cầu BLVP ───────────────────────────────────

    public function getClaimDetail(string $requestId): array
    {
        $result = $this->queryHospital(['Type' => 'GUARANTEEDETAIL', 'Code' => $requestId]);
        $model  = $result['Model'] ?? $result['model'] ?? [];
        return is_array($model) ? ($model[0] ?? []) : $model;
    }

    // ─── [✓] 7.10. Lấy file xác nhận bảo lãnh ──────────────────────────────
    // Điều kiện: IsdocumentResult = true

    public function getGuaranteeFile(string $ciCode): array
    {
        $result  = $this->queryHospital(['Type' => 'FILEDOCUMENTHOSPITAL', 'Code' => $ciCode]);
        $success = $result['Success'] ?? $result['success'] ?? false;
        if (!$success) {
            throw new RuntimeException('Không lấy được file xác nhận bảo lãnh từ Bảo Minh.');
        }
        $items = $result['Model'] ?? $result['model'] ?? [];
        return is_array($items) ? ($items[0] ?? []) : [];
    }

    // ─── [✓] 3. Tạo yêu cầu BLVP ────────────────────────────────────────────

    public function createGuaranteeRequest(array $payload): array
    {
        return $this->callEncrypted('POST', '/PublicApi/claim/NKKIM/GuaranteeRequests', $payload);
    }

    // ─── [?] 4. Hủy yêu cầu BLVP ────────────────────────────────────────────
    // TODO: cập nhật URI khi BaoMinh cấp endpoint chính thức

    public function cancelGuaranteeRequest(string $ciCode, array $payload = []): array
    {
        throw new RuntimeException("BaoMinh chưa cấp endpoint hủy yêu cầu [{$ciCode}]. Cập nhật URI khi có docs.");
        // return $this->callEncrypted('POST', "/PublicApi/claim/NKKIM/GuaranteeRequests/{$ciCode}/cancel", $payload);
    }

    // ─── [?] 5. Bổ sung thông tin yêu cầu BLVP ──────────────────────────────
    // TODO: cập nhật URI khi BaoMinh cấp endpoint chính thức

    public function supplementGuaranteeRequest(string $ciCode, array $payload): array
    {
        throw new RuntimeException("BaoMinh chưa cấp endpoint bổ sung yêu cầu [{$ciCode}]. Cập nhật URI khi có docs.");
        // return $this->callEncrypted('POST', "/PublicApi/claim/NKKIM/GuaranteeRequests/{$ciCode}/supplement", $payload);
    }

    // ─── [✓] 4. Nộp hồ sơ quyết toán (online) ──────────────────────────────
    // Tài liệu 7.5: POST /PublicApi/claim/NKKIM/{cmCode}/Execute
    // Điều kiện BaoMinh: BLVP đã xác nhận (statusName = "Đã xác nhận BLVP") và Stage ≠ 1

    public function submitClaimOnline(string $cmCode, array $payload = []): array
    {
        $body = [
            'Type'      => 'ONLINE',
            'Notes'     => $payload['notes'] ?? '',
            'Code'      => $cmCode,
            'Documents' => array_map(function ($doc) {
                return [
                    'FileName' => $doc['fileName'] ?? $doc['FileName'] ?? '',
                    'Type'     => $doc['type']     ?? $doc['Type']     ?? '',
                    'File'     => $doc['file']      ?? $doc['File']     ?? '',
                ];
            }, $payload['documents'] ?? []),
        ];

        return $this->callEncrypted('POST', '/PublicApi/claim/NKKIM/Execute', $body);
    }

    // ─── [✓] 5. Nộp hồ sơ quyết toán (bản cứng) ─────────────────────────────
    // Tài liệu 7.6: POST /PublicApi/claim/NKKIM/{cmCode}/Execute
    // Điều kiện BaoMinh: BLVP đã xác nhận (statusName = "Đã xác nhận BLVP") và Stage ≠ 1

    public function submitClaimHard(string $cmCode, array $payload = []): array
    {
        $sentAt = !empty($payload['sendDate'])
            ? $payload['sendDate']
            : date('Y-m-d\TH:i:s\Z');

        $body = [
            'Code'   => $cmCode,
            'Type'   => 'HARD',
            'SentAt' => $sentAt,
        ];

        return $this->callEncrypted('POST', '/PublicApi/claim/NKKIM/Execute', $body);
    }

    // ─── [✓] 6. Bổ sung chứng từ ─────────────────────────────────────────────
    // Tài liệu 7.9: POST /PublicApi/claim/NKKIM/Execute (Type=SUPPLEMENTDOCUMENT)

    public function submitClaimSupplementDocument(string $cmCode, array $payload = []): array
    {
        $body = [
            'Type'      => 'SUPPLEMENTDOCUMENT',
            'Code'      => $cmCode,
            'Documents' => array_map(function ($doc) {
                return [
                    'FileName' => $doc['fileName'] ?? $doc['FileName'] ?? '',
                    'File'     => $doc['file']     ?? $doc['File']     ?? '',
                ];
            }, $payload['documents'] ?? []),
        ];

        return $this->callEncrypted('POST', '/PublicApi/claim/NKKIM/Execute', $body);
    }

    // ─── [✓] 7. Điều chỉnh hồ sơ ─────────────────────────────────────────────

    public function editRequest(string $providerRequestId, string $notes, array $documents = []): array
    {
        return $this->callEncrypted('POST', '/PublicApi/claim/NKKIM/Execute', [
            'Type'      => 'INFORMATIONUPDATE',
            'Code'      => $providerRequestId,
            'Notes'     => $notes,
            'Documents' => $documents,
        ]);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function callEncrypted(string $method, string $uri, array $body): array
    {
        $token     = $this->getToken();
        $jsonBody  = json_encode($body, JSON_UNESCAPED_UNICODE);
        $encrypted = $this->encryption->encrypt($jsonBody);

        $loggableBody = $body;
        if (!empty($loggableBody['signedPdfBase64'])) {
            $loggableBody['signedPdfBase64'] = '[file]';
        }
        if (!empty($loggableBody['Documents']) && is_array($loggableBody['Documents'])) {
            $loggableBody['Documents'] = array_map(function ($doc) {
                if (!empty($doc['File'])) {
                    $doc['File'] = '[file]';
                }
                return $doc;
            }, $loggableBody['Documents']);
        }

        Log::info('[BaoMinh] API request', [
            'method'  => $method,
            'uri'     => $uri,
            'payload' => $loggableBody,
        ]);

        try {
            $response = $this->http->request($method, $uri, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'text/plain',
                ],
                'body'        => $encrypted,
                'http_errors' => true,
            ]);
        } catch (GuzzleException $e) {
            Log::error('[BaoMinh] API error', [
                'method' => $method,
                'uri'    => $uri,
                'error'  => $e->getMessage(),
            ]);
            throw new RuntimeException('Gửi yêu cầu tới bảo hiểm không thành công');
        }

        $raw       = $response->getBody()->getContents();
        $decrypted = $this->encryption->decrypt($raw);
        $data      = json_decode($decrypted, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('[BaoMinh] API non-JSON response', [
                'uri'      => $uri,
                'response' => substr($decrypted, 0, 500),
            ]);
            throw new RuntimeException('BaoMinh returned non-JSON: ' . substr($decrypted, 0, 200));
        }

        $loggableData = $data;
        if (!empty($loggableData['Model']) && is_array($loggableData['Model'])) {
            $loggableData['Model'] = array_map(function ($item) {
                if (!empty($item['File'])) {
                    $item['File'] = '[file]';
                }
                return $item;
            }, $loggableData['Model']);
        }

        Log::info('[BaoMinh] API response', [
            'uri'      => $uri,
            'response' => $loggableData,
        ]);

        return $data;
    }

    private function parseDateTime(string $isoStr): string
    {
        if (empty($isoStr)) {
            return Carbon::now()->addMinutes(1)->toDateTimeString();
        }

        try {
            return Carbon::parse($isoStr)->toDateTimeString();
        } catch (\Exception $e) {
            return Carbon::now()->addMinutes(1)->toDateTimeString();
        }
    }
}
