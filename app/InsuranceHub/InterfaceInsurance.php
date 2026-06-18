<?php

namespace App\InsuranceHub;

use App\InsuranceHub\DTOs\RequestResultDto;

/**
 * Contract cho mọi Insurance provider.
 * Controller → InterfaceInsurance → RequestRepository → ApiClient
 *
 * Mỗi provider implement interface này.
 * Provider nào chưa có API cho 1 operation thì throw RuntimeException trong implementation.
 * Controller kiểm tra availableActions() trước khi expose operation ra FE.
 */
interface InterfaceInsurance
{
    // ── Yêu cầu BLVP ──────────────────────────────────────────────────────────

    public function createRequest(array $data): RequestResultDto;

    public function supplementRequest(string $providerRequestId, array $data): void;

    public function cancelRequest(string $providerRequestId): void;

    public function canEdit(string $providerRequestId): bool;

    public function editRequest(string $providerRequestId, array $data): void;

    // ── Hồ sơ quyết toán ──────────────────────────────────────────────────────

    public function submitClaim(string $providerRequestId, array $data = []): void;

    // ── Config / Workflow ──────────────────────────────────────────────────────

    public function getRequestWorkflow(): array;

    public function getClaimWorkflow(): array;

    public function getAvailableActions($insuranceRequest): array;

    // ── Chi tiết yêu cầu ────────────────────────────────────────────────────────

    public function getClaimDetail(string $providerRequestId, $insuranceRequest): array;

    // ── File xác nhận bảo lãnh ────────────────────────────────────────────────

    public function getGuaranteeFile(string $providerRequestId): array;
}
