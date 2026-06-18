<?php

namespace App\Exports;

use App\Libs\BaseExportExcel;

class PurchaseOrderExport extends BaseExportExcel
{
    protected $poList;

    // Status labels
    protected $statusLabels = [
        1 => 'Mới',
        2 => 'Đã gửi NCC',
        3 => 'Hoàn thành',
    ];

    public function __construct($poList)
    {
        // Hỗ trợ cả 1 PO (array) lẫn list PO
        $this->poList = isset($poList['PurchaseOrderId']) ? [$poList] : $poList;
    }

    protected function getData()
    {
        return $this->data;
    }

    public function collection()
    {
        $rows = [];
        $stt  = 1;

        foreach ($this->poList as $po) {
            $supplierName = $po['supplier']['Name'] ?? '';
            $poCode       = $po['PurchaseOrderCode'] ?? '';
            $status       = $this->statusLabels[$po['Status'] ?? 1] ?? '';
            $createdDate  = isset($po['CreatedDate']) ? date('d/m/Y', strtotime($po['CreatedDate'])) : '';
            $details      = $po['details'] ?? [];

            foreach ($details as $detail) {
                $qty       = (int) ($detail['Quantity'] ?? 0);

                $rows[] = [
                    $stt++,
                    $poCode,
                    $supplierName,
                    $detail['product']['SKU'] ?? '',
                    $detail['product']['Name'] ?? '',
                    $detail['unit']['Name'] ?? '',
                    $qty,
                    $status,
                    $createdDate,
                    $detail['Note'] ?? '',
                ];
            }
        }

        return collect($rows);
    }

    public function map($row): array
    {
        return $row;
    }
}
