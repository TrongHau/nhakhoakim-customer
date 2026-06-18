<?php

namespace App\Libs;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

abstract class BaseExportExcel implements FromCollection, WithEvents, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting
{
    
    /** 
     * Example:
     * 
     * $saveDir = 'Client' . $clientGroupId . '/exports';
     * $s3Storage = new S3ExportStorage();
     * 
     * $reportExport = new BaseReportWithTotal($exportValue, $totalRow);
     * $reportExport->setStorage($s3Storage);
     * $exportURL = $reportExport->setHeadings($headings)
     *   ->formatHeadings('A1:G1','FFFFFF', '4285F4')
     *   ->store('excel/' . $fileExportName)
     *   ->export($filePathExport, $saveDir);
     * 
     * $reportExport->unlink($filePathExport);
     */

    /**
     * The data of file excel export
     * @var array
     */
    protected $data = [];

    /**
     * The headings of file excel export
     * @var array|object
     */
    protected $headings = [];

    /**
     * The styles of file excel export
     * @var array
     */
    protected $styles = [];

    /**
     * The number formats of file excel export
     * @var array
     */
    protected $numberFormats = [];

    /**
     * The storage of file excel export
     * @var BaseExportStorage
     */
    protected $storage;

    abstract protected function getData();

    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * implements WithHeadings
     */
    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * implements FromCollection
     */
    public function collection()
    {
        return collect($this->data);
    }

    /**
     * implements WithMapping
     */
    public function map($row): array
    {
        $rows = !is_array($row) ? $row->toArray() : $row;

        if (is_array($rows) && count($rows) > 0) {
            foreach ($rows as $key => $item) {
                if (is_numeric($item) && $item == 0) {
                    $rows[$key] = '0';
                }
            }
        }
        return $rows;
    }

    /**
     * implements WithColumnFormatting
     */
    public function columnFormats(): array
    {
        $range = range('A', 'I');
        $format = [];
        foreach ($range as $column) {
            $format[$column] = NumberFormat::FORMAT_TEXT;
        }
        return $format;
    }

    /**
     * implements WithEvents
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;

                foreach ($this->styles as $range => $style) {
                    $sheet->getStyle($range)->applyFromArray($style);
                }

                foreach ($this->numberFormats as $range => $format) {
                    $sheet->getDelegate()->getStyle($range)->getNumberFormat()->setFormatCode($format);
                }
            }
        ];
    }


    /**
     * ================================
     * Custom functions for styling
     * ================================
     */

    /**
     * Set Storage
     */
    public function setStorage(BaseExportStorage $storage)
    {
        $this->storage = $storage;
        return $this;
    }

    /**
     * In đậm ký tự trong ô
     */
    public function setBold($range)
    {
        $this->styles[$range]['font']['bold'] = true;
        return $this;
    }

    /**
     * Đặt chữ nghiêng
     */
    public function setItalic($range)
    {
        $this->styles[$range]['font']['italic'] = true;
        return $this;
    }

    /**
     * Đặt chữ gạch ngang
     */
    public function setUnderline($range, $type = Font::UNDERLINE_SINGLE)
    {
        $this->styles[$range]['font']['underline'] = $type;
        return $this;
    }

    /**
     * Đổi màu ký tự trong ô
     */
    public function setFontColor($range, $color)
    {
        $this->styles[$range]['font']['color'] = ['rgb' => $color];
        return $this;
    }

    /**
     * Đổi màu nền
     */
    public function setBackgroundColor($range, $color)
    {
        $this->styles[$range]['fill'] = [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => $color]
        ];
        return $this;
    }

    /**
     * Căn chỉnh vị trí trong ô
     */
    public function setAlignment($range, $horizontal = Alignment::HORIZONTAL_CENTER, $vertical = Alignment::VERTICAL_CENTER)
    {
        $this->styles[$range]['alignment'] = [
            'horizontal' => $horizontal,
            'vertical' => $vertical
        ];
        return $this;
    }

    /**
     * Đặt cỡ ký tự trong ô
     */
    public function setFontSize($range, $size)
    {
        $this->styles[$range]['font']['size'] = $size;
        return $this;
    }

    /**
     * Format ký tự trong ô
     */
    public function setNumberFormat($range, $formatType)
    {
        $formats = [
            'decimal'    => NumberFormat::FORMAT_NUMBER_00,           // 0.00
            'decimal_1'  => '0.0',                                    // 0.0
            'integer'    => '#,##0',                                  // 1,000
            'percent'    => NumberFormat::FORMAT_PERCENTAGE,          // 50%
            'currency'   => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE, // $1,000.00
            'accounting' => NumberFormat::FORMAT_ACCOUNTING_USD,      // $ 1,000.00
            'date'       => 'DD/MM/YYYY',                             // 06/02/2024
            'month_year' => 'MM/YYYY',                                // 02/2024
            'time'       => 'hh:mm:ss AM/PM',                         // 03:45:30 PM
            'datetime'   => 'DD/MM/YYYY hh:mm:ss',                    // 06/02/2024 14:30:00
            'vnd'        => '#,##0 [$₫-vi-VN]',                      // 1.000 ₫
        ];

        if (isset($formats[$formatType])) {
            $this->numberFormats[$range] = $formats[$formatType];
        }
        return $this;
    }

    /**
     * Bật/tắt wrap text cho ô
     */
    public function setWrapText($range, $wrap = true)
    {
        $alignment = $this->styles[$range]['alignment'] ?? [];
        $alignment['wrapText'] = $wrap;
        $this->styles[$range]['alignment'] = $alignment;
        return $this;
    }

    /**
     * Bật/tắt wrap text cho nhiều ô
     */
    public function setWrapTextMultipleColumns(array $columns, int $dataRowCount, int $startRow = 2)
    {
        $ranges = $this->setRangeForColumns($columns, $dataRowCount, $startRow);

        foreach ($ranges as $range) {
            $this->setWrapText($range);
        }

        return $this;
    }

    /**
     * Tạo Range cho nhiều cột
     * Set từ hàng bắt đầu đến dòng cuối cùng có data
     */
    private function setRangeForColumns(array $columns, int $dataRowCount, int $startRow = 2): array
    {
        if ($dataRowCount <= 0) {
            return [];
        }

        $ranges = [];
        $endRow = $startRow + $dataRowCount - 1;

        foreach ($columns as $column) {
            $column = strtoupper(trim($column));
            if ($column === '') {
                continue;
            }
            $ranges[] = sprintf('%s%d:%s%d', $column, $startRow, $column, $endRow);
        }

        return $ranges;
    }

    /**
     * Đặt viền cho ô
     */
    public function setBorder($range, $type = 'all', $color = '000000')
    {
        $borderStyles = [
            'all'   => Border::BORDER_THIN,
            'thick' => Border::BORDER_THICK,
            'dashed'=> Border::BORDER_DASHED,
            'dotted'=> Border::BORDER_DOTTED,
        ];

        $borderStyle = $borderStyles[$type] ?? Border::BORDER_THIN;

        $this->styles[$range]['borders'] = [
            'allBorders' => [
                'borderStyle' => $borderStyle,
                'color' => ['rgb' => $color]
            ]
        ];
        return $this;
    }

    /**
     * Đặt tiêu đề
     */
    public function setHeadings(array $headings)
    {
        $this->headings = $headings;
        return $this;
    }

    /**
     * Format tiêu đề
     */
    public function formatHeadings($range, $fontColor, $backgroundColor)
    {
        $this->setFontColor($range, $fontColor)->setBackgroundColor($range, $backgroundColor);
        return $this;
    }

    /**
     * Upload file lên server và trả về đường dẫn file Export
     * 
     * @return string
     */
    public function export(string $filePath, $saveDir = '', $fileExportName = false)
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: " . $filePath);
        }
        return $this->storage->uploadFile($filePath, $saveDir, $fileExportName);
    }

    /**
     * Lưu file excel local
     */
    public function store(string $filePath)
    {
        Excel::store($this, $filePath);
        return $this;
    }

    /**
     * Xóa file local
     */
    public function unlink(string $filePath)
    {
        try {
            if (!file_exists($filePath)) {
                throw new Exception("File not found: " . $filePath);
            }
            unlink($filePath);
            return true;
        } catch (\Exception $e) {
            Log::error('Error when unlink file: ', [$e->getMessage()]);
        }
        return false;
    }
}
