<?php

namespace App\InsuranceHub\Providers\BaoMinh;

use App\InsuranceHub\EnumStatus;
use App\InsuranceHub\InterfaceProviderConfig;

class ProviderConfig implements InterfaceProviderConfig
{
    const BASE_URL_UAT  = 'https://api-uat-ibmi.baominh.vn:8200';
    const BASE_URL_PROD = '';
    const LOGIN_FOR     = 'API';

    public static function baseUrl(): string
    {
        $env = env('APP_ENV', 'testing');
        return $env === 'production' ? self::BASE_URL_PROD : self::BASE_URL_UAT;
    }

    // ─── Fallback PGP keys (dùng khi DB chưa có) ─────────────────────────────

    public static function pgpPublicKey(): string
    {
        return file_get_contents(__DIR__ . '/KeyEncryption/keybaominh.txt');
    }

    public static function pgpPrivateKey(): string
    {
        return file_get_contents(__DIR__ . '/KeyEncryption/nkk_baominh_private_key.txt');
    }

    // ─── InterfaceProviderConfig ──────────────────────────────────────────────

    public static function requestWorkflowSteps(): array
    {
        return [
            ['stepNumber' => 1, 'status' => EnumStatus::DRAFT,        'label' => 'Lưu nháp',           'description' => 'Chi nhánh tạo yêu cầu bảo lãnh viện phí',        'isFinal' => false],
            ['stepNumber' => 2, 'status' => EnumStatus::SUBMITTED,    'label' => 'Đã gửi yêu cầu',    'description' => 'Yêu cầu đã được gửi lên Bảo Minh',               'isFinal' => false],
            ['stepNumber' => 3, 'status' => EnumStatus::IN_REVIEW,    'label' => 'Đang xử lý',         'description' => 'Bảo Minh đang thẩm định yêu cầu BLVP',           'isFinal' => false],
            ['stepNumber' => 4, 'status' => EnumStatus::PENDING_INFO, 'label' => 'Chờ bổ sung',        'description' => 'Bảo Minh yêu cầu bổ sung thông tin BLVP',        'isFinal' => false],
            ['stepNumber' => 5, 'status' => EnumStatus::SUPPLEMENTED, 'label' => 'Đã bổ sung',         'description' => 'Chi nhánh đã bổ sung thông tin BLVP',             'isFinal' => false],
            ['stepNumber' => 6, 'status' => EnumStatus::APPROVED,     'label' => 'Đã xác nhận',  'description' => 'Bảo Minh đã xác nhận bảo lãnh viện phí',         'isFinal' => false],
            ['stepNumber' => 7, 'status' => EnumStatus::COMPLETED,    'label' => 'Hoàn tất', 'description' => 'Chi nhánh xác nhận bệnh nhân đã hoàn tất điều trị', 'isFinal' => false],
            ['stepNumber' => 8, 'status' => EnumStatus::REJECTED,     'label' => 'Từ chối',       'description' => 'Bảo Minh từ chối bảo lãnh viện phí',             'isFinal' => true],
            ['stepNumber' => 9, 'status' => EnumStatus::CANCELLED,   'label' => 'Đã hủy yêu cầu',    'description' => 'Yêu cầu BLVP bị hủy',                            'isFinal' => true],
        ];
    }

    public static function claimWorkflowSteps(): array
    {
        return [
            ['stepNumber' => 1,  'status' => EnumStatus::CLAIM_WAITING,      'label' => 'Chờ gửi HS',       'description' => 'Chi nhánh chuẩn bị và chờ gửi hồ sơ quyết toán',  'isFinal' => false],
            ['stepNumber' => 2,  'status' => EnumStatus::CLAIM_SENT,         'label' => 'Đã gửi HS',        'description' => 'Hồ sơ quyết toán đã gửi đến Bảo Minh',            'isFinal' => false],
            ['stepNumber' => 3,  'status' => EnumStatus::CLAIM_RECEIVED,     'label' => 'Đã nhận HS',       'description' => 'Bảo Minh đã nhận hồ sơ quyết toán',               'isFinal' => false],
            ['stepNumber' => 4,  'status' => EnumStatus::CLAIM_PENDING_INFO, 'label' => 'Chờ bổ sung HS',   'description' => 'Bảo Minh yêu cầu bổ sung hồ sơ quyết toán',       'isFinal' => false],
            ['stepNumber' => 5,  'status' => EnumStatus::CLAIM_SUPPLEMENTED, 'label' => 'Đã bổ sung HS',    'description' => 'Chi nhánh đã bổ sung hồ sơ quyết toán',            'isFinal' => false],
            ['stepNumber' => 6,  'status' => EnumStatus::PENDING_PAYMENT,    'label' => 'Chờ thanh toán',   'description' => 'Bảo Minh đang xử lý thanh toán bồi thường',        'isFinal' => false],
            ['stepNumber' => 7,  'status' => EnumStatus::PAID,               'label' => 'Đã thanh toán',    'description' => 'Bảo Minh đã thanh toán bồi thường',               'isFinal' => true],
            ['stepNumber' => 8,  'status' => EnumStatus::CLAIM_REJECTED,     'label' => 'Từ chối HS',       'description' => 'Bảo Minh từ chối hồ sơ quyết toán',               'isFinal' => true],
            ['stepNumber' => 9,  'status' => EnumStatus::CLAIM_CANCELLED,    'label' => 'Đã hủy HS',        'description' => 'Hồ sơ quyết toán bị hủy',                         'isFinal' => true],
        ];
    }

    public static function availableActions(): array
    {
        $env = env('APP_ENV') ?? 'production';
        return [
            // ── gửi yêu REQUESTCLAIMINIT ──────────────────────────────────────────────────────
            EnumStatus::DRAFT              => ['submit', 'delete'],
            EnumStatus::SUBMITTED          => ['cancel'],
            EnumStatus::IN_REVIEW          => ['cancel'],
            EnumStatus::PENDING_INFO       => ['cancel', 'supplement'],
            EnumStatus::SUPPLEMENTED       => ['cancel'],
            EnumStatus::APPROVED           => ['cancel'], // 'submit_request_online', 'submit_request_hard', 'cancel', 'print_pdf'
            EnumStatus::COMPLETED          => [],


            // ── công nợ REQUESTCLAIM ────────────────────────────────────────────────
            EnumStatus::CLAIM_WAITING      => [],
            EnumStatus::CLAIM_SENT         => [],
            EnumStatus::CLAIM_RECEIVED     => [],
            EnumStatus::CLAIM_PENDING_INFO => ['supplement_claim'],
            EnumStatus::CLAIM_SUPPLEMENTED => [],
            EnumStatus::PENDING_PAYMENT    => [],
            // ── Kết thúc ────────────────────────────────────────────────────────
            EnumStatus::REJECTED           => ['view_reason'],
            EnumStatus::CLAIM_REJECTED     => ['view_reason'],
            EnumStatus::CANCELLED          => [],
            EnumStatus::CLAIM_CANCELLED    => [],
        ];
    }

    public static function actionMeta(): array
    {
        return [
            'submit'                => ['label' => 'Gửi yêu cầu',                 'method' => 'POST',   'endpoint' => 'requests/{id}/submit',           'requiresConfirmation' => true],
            'delete'                => ['label' => 'Xóa yêu cầu',                 'method' => 'POST',   'endpoint' => 'requests/delete',                'requiresConfirmation' => true],
            'cancel'                => ['label' => 'Hủy yêu cầu',                 'method' => 'POST', 'endpoint' => 'requests/{id}/cancel',           'requiresConfirmation' => true],
            'chat'                  => ['label' => 'Nhắn tin',                     'method' => 'POST',   'endpoint' => 'requests/{id}/messages',         'requiresConfirmation' => false],
            'request_adjust'        => ['label' => 'Được điều chỉnh',          'method' => 'POST',    'endpoint' => 'requests/{id}',                  'requiresConfirmation' => false],
            'submit_adjust'         => ['label' => 'Gửi điều chỉnh',              'method' => 'POST',   'endpoint' => 'requests/{id}/submit',           'requiresConfirmation' => true],
            'complete'              => ['label' => 'Hoàn tất điều trị',           'method' => 'POST',   'endpoint' => 'requests/{id}/complete',         'requiresConfirmation' => true],
            'print_pdf'             => ['label' => 'In xác nhận',            'method' => 'POST',    'endpoint' => 'requests/{id}/confirmation-pdf', 'requiresConfirmation' => false],
            'submit_request_online' => ['label' => 'Gửi HS online',     'method' => 'POST', 'endpoint' => 'requests/{id}/submit-claim', 'requiresConfirmation' => true],
            'submit_request_hard' => ['label' => 'Gửi HS bản cứng', 'method' => 'POST', 'endpoint' => 'requests/{id}/submit-claim', 'requiresConfirmation' => true],
            'supplement'            => ['label' => 'Bổ sung thông tin',            'method' => 'POST',   'endpoint' => 'requests/{id}/submit-claim',      'requiresConfirmation' => false],

            'view_claim'            => ['label' => 'Xem hồ sơ quyết toán',        'method' => 'POST',    'endpoint' => 'claims/{id}',                    'requiresConfirmation' => false],
            'supplement_claim'      => ['label' => 'Bổ sung HS quyết toán',       'method' => 'POST',   'endpoint' => 'claims/{id}/attachments',        'requiresConfirmation' => false],
            'view_reason'           => ['label' => 'Xem lý do từ chối',           'method' => 'GET',    'endpoint' => 'requests/{id}/status-history',   'requiresConfirmation' => false],
        ];
    }
}
