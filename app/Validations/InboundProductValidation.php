<?php

namespace App\Validations;

use App\Exceptions\ImportExcelException;
use App\Repositories\ProductManagerRepository;
use App\Unit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeImport;
use Illuminate\Support\Str;

class InboundProductValidation implements ToCollection, WithChunkReading, WithStartRow, WithEvents
{
    protected $file;
    protected $fileName = '';
    protected $clientGroupId;
    protected $data = [];
    protected $validRows = [];
    protected $invalidRows = [];
    protected $invalidCounts = 0;
    protected $validCounts = 0;
    protected $errors = [];

    protected $uniqueProduct = [];

    public function __construct($file = null, $fileName = '')
    {
        $this->file = $file;
        $this->fileName = $fileName;
        $this->clientGroupId = Auth::user()['ClientGroupId'] ?? 0;
    }

    public function collection(Collection $rows)
    {
        /**
         * Column:
         * 
         * 0 => SKU
         * 1 => Unit Code
         * 2 => CostPrice
         * 3 => SLDK
         */
        $productRepo = new ProductManagerRepository();
        try {
            foreach ($rows as $key => $row) {
                if (!$row || empty($row) || is_array($row)) {
                    continue;
                }
                $nameUnit = '';

                $sku = $row[0] ?? null;
                $codeUnit = $row[1] ?? null;
                $sldk = $row[2] ?? null;

                $isEmptyRow = empty($sku) && empty($codeUnit) && empty($sldk);
                if ($isEmptyRow) {
                    continue;
                }

                $checkSKU = $productRepo->checkProductBySku($sku);
                $errorMessage = [];
                if (!$checkSKU || empty($checkSKU)) {
                    $errorMessage[] = 'SKU không tồn tại.';
                }

                if (empty($codeUnit)) {
                    $errorMessage[] = 'Mã đơn vị không tồn tại.';
                }

                if (empty($sldk) || !is_numeric($sldk)) {
                    $errorMessage[] = 'Số lượng dự kiến không hợp lệ.';
                }
                $unit = null;
                if ($checkSKU && !empty($checkSKU) && !empty($codeUnit)) {
                    $units = $checkSKU->units();
                    $unit = $units->where('Code', $codeUnit)->first();
                    if (!$unit || empty($unit)) {
                        $errorMessage[] = 'Sản phẩm không có mã đơn vị: '.$codeUnit.'.';
                    }
                    $nameUnit = $unit->Name ?? '';
                };
                
                //Check unique
                $uniqueKey = Str::slug(trim($sku . '-' . $codeUnit));
                if (isset($this->uniqueProduct[$uniqueKey])) {
                    $index = $this->uniqueProduct[$uniqueKey];
                    $this->data[$index]['ExpectQuantity'] += $sldk;
                } else {
                    $this->uniqueProduct[$uniqueKey] = count($this->data);
                }

                //Export data            
                $this->data[] = Collection::make([
                    'ProductId' => ($checkSKU && !empty($checkSKU)) ? $checkSKU->ProductId : 0,
                    'SKU' => $sku,
                    'Barcode' => ($checkSKU && !empty($checkSKU)) ? $checkSKU->Barcode : '',
                    'ProductName' => ($checkSKU && !empty($checkSKU)) ? $checkSKU->ProductName : '',
                    'UnitName' => ($unit && !empty($unit)) ? $unit->Name : $nameUnit,
                    'UnitId' => ($unit && !empty($unit)) ? $unit->UnitId : 0,
                    'UnitCode' => ($unit && !empty($unit)) ? $unit->Code : $codeUnit,
                    'ExpectQuantity' => $sldk,
                    'IsError' => count($errorMessage) > 0 ? 1 : 0,
                    'ErrorMessage' => implode(" ", $errorMessage),
                ]);
                if (count($errorMessage) > 0) {
                    $this->invalidCounts++;
                }
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            throw new ImportExcelException('Có lỗi xảy ra trong quá trình xử lý dữ liệu. Vui lòng thử lại.');
        }

        // Check dòng rỗng
        if (count($this->data) < 1) {
            throw new ImportExcelException('File không có dữ liệu hoặc dữ liệu không đúng. Vui lòng kiểm tra lại');
        }
    }
    
    public function startRow(): int
    {
        return 2;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                $totalRows = $event->getReader()->getTotalRows();
                foreach ($totalRows as $key => $totalRow) {
                    if ($totalRow < 2) {
                        throw new ImportExcelException('File không có dữ liệu hoặc dữ liệu không đúng. Vui lòng kiểm tra lại');
                    }
                }
            }
        ];
    }

    public function getData()
    {
        return $this->data;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getInvalidCounts()
    {
        return $this->invalidCounts ?? 0;
    }
}
