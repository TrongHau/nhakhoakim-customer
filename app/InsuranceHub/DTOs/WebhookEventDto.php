<?php

namespace App\InsuranceHub\DTOs;

class WebhookEventDto
{
    const EVENT_STATUS_UPDATE  = 'status_update';
    const EVENT_PAYMENT_UPDATE = 'payment_update';

    /** @var string treatmentCode mình tạo, dùng để tra cứu InsuranceRequest */
    public $treatmentCode;

    /** @var string ID yêu cầu phía provider (ciCode / RequestId) */
    public $providerRequestId;

    /** @var string|null ID hồ sơ quyết toán phía provider */
    public $providerClaimId;

    /** @var string unified status đã map */
    public $newUnifiedStatus;

    /** @var string status_update | payment_update */
    public $eventType;

    /** @var float|null Số tiền thanh toán (chỉ có ở payment_update) */
    public $paymentAmount;

    /** @var string|null Ngày thanh toán */
    public $paymentDate;

    /** @var string|null Mã phiếu thanh toán */
    public $paymentNo;

    /** @var array raw payload */
    public $payload = [];

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function isPaymentUpdate(): bool
    {
        return $this->eventType === self::EVENT_PAYMENT_UPDATE;
    }
}
