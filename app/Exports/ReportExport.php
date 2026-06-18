<?php


namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithEvents, WithColumnFormatting
{
    public $data = [];
    public $headings = [];
    protected $highestColumnB1 = false;
    protected $cellIndex = false;
    protected $highestColumn = NULL;
    protected $exceptionColumnNotNumber = [];
    protected $rgbColor = [
        'White' => 'FFFFFF',
        'Black' => '000000',
        'Red' => 'f44542',
        'Blue' => '4285f4'
    ];

    public function __construct($data, $headings = [], $isHightB1 = false, $cellIndex = '')
    {
        $this->data = $data ?? [];
        
        $this->setHeadings($headings);
        $this->highestColumnB1 = $isHightB1;
        $this->cellIndex = $cellIndex;
    }

    public function collection()
    {
        return collect($this->data);
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function map($row): array
    {
        $rows = !is_array($row) ? $row->toArray() : $row;

        if ((is_array($rows)) && count($rows) > 0) {
            foreach ($rows as $key => $item) {
                if (is_numeric($item) && $item == 0) {
                    $rows[$key] = '0';
                }
            }
        }
        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $highestColumn = $this->highestColumn ?? $event->sheet->getHighestColumn();
                if (is_string($this->cellIndex)){
                    $cellRangeAmountFormat = $this->cellIndex ?? 'B2:' . $highestColumn . '1000';
                    if (!isset($cellRangeAmountFormat) || empty($cellRangeAmountFormat)) {
                        $cellRangeAmountFormat = 'B2:' . $highestColumn . '1000';
                    }
                    $event->sheet->getDelegate()->getStyle($cellRangeAmountFormat)
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.00');
                    if (!empty($this->exceptionColumnNotNumber)) {
                        foreach ($this->exceptionColumnNotNumber as $item) {
                            $cellRangeAmountFormat = $item . '0:' . $item . '1000';
                            $event->sheet->getDelegate()->getStyle($cellRangeAmountFormat)
                                ->getNumberFormat()
                                ->setFormatCode('#,##0');
                        }
                    }
                }

                if ($this->highestColumnB1) {
                    $event->sheet->getDelegate()->getStyle('A2:A2')
                        ->applyFromArray(
                            [
                                'font' => [
                                    'bold' => true,
                                    'italic' => false,
                                    'color' => [
                                        'rgb' => $this->rgbColor['Red']
                                    ]
                                ],
                                'alignment' => [
                                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                                    'vertical' => Alignment::VERTICAL_CENTER,
                                    'wrapText' => true,
                                ],
                            ]
                        );
                    $event->sheet->getDelegate()->getStyle('B2:'.$highestColumn.'2')
                        ->applyFromArray(
                            [
                                'font' => [
                                    'bold' => true,
                                    'italic' => false,
                                    'color' => [
                                        'rgb' => $this->rgbColor['Black']
                                    ]
                                ],
                                'alignment' => [
                                    'horizontal' => Alignment::HORIZONTAL_RIGHT,
                                    'vertical' => Alignment::VERTICAL_CENTER,
                                    'wrapText' => true,
                                ],
                            ]
                        );
                }
                $this->formatCell($event, $highestColumn);
            }
        ];
    }

    public function columnFormats(): array
    {
        $range = range('A', 'I');
        $format = [];
        foreach ($range as $column) {
            $format[$column] = NumberFormat::FORMAT_TEXT;
        }
        return $format;
    }

    public function setHeadings($headings)
    {
        if (!empty($headings) && !is_array($headings)) {
            $headings = [$headings];
        }

        if (empty($headings) || count($headings) < 1) {
            $headings = [
                'STT',
                'Mã vạch',
                'Tên sản phẩm',
                'Tên danh mục',
                'Mã đơn vị',
                'Tên đơn vị',
                'Số lượng quy đổi',
                'Đơn vị nhỏ nhất',
                'Mô tả',
                'Lỗi'
            ];
        }

        $this->headings = $headings;
    }

    public function setHighestColumn($column)
    {
        $this->highestColumn = $column;
    }

    protected function formatCell($event, $highestColumn)
    {
        $cellRange = 'A1:' . $highestColumn . '1';
        $event->sheet->getDelegate()->getStyle($cellRange)
            ->applyFromArray(
                [
                    'font' => [
                        'bold' => true,
                        'italic' => false,
                        'color' => [
                            'rgb' => $this->rgbColor['White']
                        ]
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => $this->rgbColor['Blue']
                        ]
                    ],
                ]
            );
    }

    public function setExceptionColumnNotNumber($column)
    {
        $this->exceptionColumnNotNumber = $column;
    }
}
