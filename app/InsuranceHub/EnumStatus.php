<?php

namespace App\InsuranceHub;

class EnumStatus
{
    // ── Yêu cầu BLVP ─────────────────────────────────────────
    const DRAFT          = 'DRAFT';
    const SUBMITTED      = 'SUBMITTED';
    const IN_REVIEW      = 'IN_REVIEW';
    const ADJUSTING      = 'ADJUSTING';
    const PENDING_INFO   = 'PENDING_INFO';
    const SUPPLEMENTED   = 'SUPPLEMENTED';
    const APPROVED       = 'APPROVED';
    const REJECTED       = 'REJECTED';
    const COMPLETED      = 'COMPLETED';
    const CANCELLED      = 'CANCELLED';

    // ── Hồ sơ quyết toán ─────────────────────────────────────
    const CLAIM_WAITING      = 'CLAIM_WAITING';
    const CLAIM_SENT         = 'CLAIM_SENT';
    const CLAIM_RECEIVED     = 'CLAIM_RECEIVED';
    const CLAIM_PENDING_INFO = 'CLAIM_PENDING_INFO';
    const CLAIM_SUPPLEMENTED = 'CLAIM_SUPPLEMENTED';
    const PENDING_PAYMENT    = 'PENDING_PAYMENT';
    const PAID               = 'PAID';
    const CLAIM_REJECTED     = 'CLAIM_REJECTED';
    const CLAIM_CANCELLED    = 'CLAIM_CANCELLED';

    // Label mặc định — dùng làm fallback khi không có provider context.
    // Label hiển thị thực tế lấy từ provider::workflowSteps()[]['label'].
    const LABELS = [
        self::DRAFT              => 'Lưu nháp',
        self::SUBMITTED          => 'Đã gửi',
        self::IN_REVIEW          => 'Đang xử lý',
        self::ADJUSTING          => 'Điều chỉnh',
        self::PENDING_INFO       => 'Chờ bổ sung',
        self::SUPPLEMENTED       => 'Đã bổ sung',
        self::APPROVED           => 'Đã xác nhận',
        self::REJECTED           => 'Từ chối',
        self::COMPLETED          => 'Kết thúc',
        self::CANCELLED          => 'Đã hủy',
        self::CLAIM_WAITING      => 'Chờ gửi hồ sơ',
        self::CLAIM_SENT         => 'Đã gửi hồ sơ',
        self::CLAIM_RECEIVED     => 'Đã nhận hồ sơ',
        self::CLAIM_PENDING_INFO => 'Chờ bổ sung hồ sơ',
        self::CLAIM_SUPPLEMENTED => 'Đã bổ sung hồ sơ',
        self::PENDING_PAYMENT    => 'Chờ thanh toán',
        self::PAID               => 'Đã thanh toán',
        self::CLAIM_REJECTED     => 'Từ chối hồ sơ',
        self::CLAIM_CANCELLED    => 'Đã hủy hồ sơ',
    ];

    const ALL = [
        self::DRAFT, self::SUBMITTED, self::IN_REVIEW, self::PENDING_INFO,
        self::SUPPLEMENTED, self::APPROVED, self::REJECTED,
        self::COMPLETED, self::CANCELLED,
        self::CLAIM_WAITING, self::CLAIM_SENT, self::CLAIM_RECEIVED,
        self::CLAIM_PENDING_INFO, self::CLAIM_SUPPLEMENTED,
        self::PENDING_PAYMENT, self::PAID, self::CLAIM_REJECTED, self::CLAIM_CANCELLED,
    ];

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::ALL, true);
    }
}
