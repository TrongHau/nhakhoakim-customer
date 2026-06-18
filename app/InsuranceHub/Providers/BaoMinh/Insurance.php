<?php

namespace App\InsuranceHub\Providers\BaoMinh;

use App\InsuranceHub\DTOs\ActionDto;
use App\InsuranceHub\DTOs\RequestResultDto;
use App\InsuranceHub\EnumStatus;
use App\InsuranceHub\InterfaceInsurance;
use App\InsuranceProviderCredential;

class Insurance implements InterfaceInsurance
{
    private $credential;
    private $apiClient = null;

    public function __construct(InsuranceProviderCredential $credential)
    {
        $this->credential = $credential;
        // ApiClient không khởi tạo ở đây — chỉ tạo khi thực sự cần gọi API đối tác (lazy init)
    }

    // ── Yêu cầu BLVP ──────────────────────────────────────────────────────────

    public function createRequest(array $data): RequestResultDto
    {
        return RequestRepository::createRequest($this->client(), $data);
//        return RequestRepository::createRequest($data);
    }

    public function supplementRequest(string $providerRequestId, array $data): void
    {
        RequestRepository::supplementRequest($this->client(), $providerRequestId, $data);
    }

    public function cancelRequest(string $providerRequestId): void
    {
        RequestRepository::cancelRequest($this->client(), $providerRequestId);
    }

    public function canEdit(string $providerRequestId): bool
    {
        return RequestRepository::canEdit($this->client(), $providerRequestId);
    }

    public function editRequest(string $providerRequestId, array $data): void
    {
        RequestRepository::editRequest($this->client(), $providerRequestId, $data);
    }

    // ── Gửi Hồ sơ quyết toán

    public function submitClaim(string $providerRequestId, array $data = []): void
    {
        RequestRepository::submitClaim($this->client(), $providerRequestId, $data);
    }

    public function getClaimDetail(string $providerRequestId, $insuranceRequest): array
    {
        return RequestRepository::getClaimDetail($this->client(), $providerRequestId, $insuranceRequest);
    }

    public function getGuaranteeFile(string $providerRequestId): array
    {
        return $this->client()->getGuaranteeFile($providerRequestId);
    }

    // ── Config / Workflow — không cần gọi API ─────────────────────────────────

    public function getRequestWorkflow(): array
    {
        return ProviderConfig::requestWorkflowSteps();
    }

    public function getClaimWorkflow(): array
    {
        return ProviderConfig::claimWorkflowSteps();
    }

    public function getAvailableActions($insuranceRequest): array
    {
        return RequestRepository::availableActions($insuranceRequest);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function client(): ApiClient
    {
        if ($this->apiClient === null) {
            $encryption      = new Encryption(
                $this->credential->PgpPublicKey  ?: ProviderConfig::pgpPublicKey(),
                $this->credential->PgpPrivateKey ?: ProviderConfig::pgpPrivateKey()
            );
            $this->apiClient = new ApiClient($this->credential, $encryption);

        }

        return $this->apiClient;
    }
}
