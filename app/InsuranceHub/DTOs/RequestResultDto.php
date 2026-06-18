<?php

namespace App\InsuranceHub\DTOs;

class RequestResultDto
{
    /** @var string|null ID từ phía provider */
    public $providerRequestId;

    /** @var string|null Mã điều trị sinh ra khi tạo */
    public $treatmentCode;

    /** @var string UnifiedStatus */
    public $unifiedStatus;

    /** @var float|null */
    public $estimatedAmount;

    /** @var string|null URL PDF xác nhận */
    public $confirmationPdfUrl;

    /** @var array raw response payload */
    public $payload = [];

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function toArray(): array
    {
        return [
            'providerRequestId'  => $this->providerRequestId,
            'treatmentCode'      => $this->treatmentCode,
            'unifiedStatus'      => $this->unifiedStatus,
            'estimatedAmount'    => $this->estimatedAmount,
            'confirmationPdfUrl' => $this->confirmationPdfUrl,
        ];
    }
}
