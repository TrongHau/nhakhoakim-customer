<?php


namespace App\Imports;

use App\Category;
use App\Exceptions\ImportExcelException;
use App\Libs\Helper;
use App\Repositories\ProductManagerRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeImport;

class ProductManagerImport implements ToCollection, WithChunkReading, WithStartRow, WithHeadingRow, WithEvents
{
    protected $file;
    protected $clientGroupId;
    protected $validRows = [];
    protected $invalidRows = [];
    protected $dup = [];
    protected $invalidCounts = 0;
    protected $countSKU = 0;
    protected $validCounts = 0;
    protected $fileName = '';
    protected $data;
    protected $generateSKU = [];

    public function __construct($file = null, $fileName = '')
    {
        $this->file = $file;
        $this->fileName = $fileName;
        $this->clientGroupId = Auth::user()['ClientGroupId'] ?? 0;
    }

    public function collection(Collection $rows)
    {
        $productRepo = new ProductManagerRepository();
        $dupRecords = [];
        $dupRecords = $this->mergeData($rows);
        foreach ($rows as $key => $row) {
            if (
                (!isset($row['ma_vach']) || empty($row['ma_vach'])) &&
                (!isset($row['ten_san_pham']) || empty($row['ten_san_pham'])) &&
                (!isset($row['ten_danh_muc']) || empty($row['ten_danh_muc'])) &&
                (!isset($row['ma_don_vi']) || empty($row['ma_don_vi'])) &&
                (!isset($row['ten_don_vi']) || empty($row['ten_don_vi'])) &&
                (!isset($row['so_luong_quy_doi']) || empty($row['so_luong_quy_doi'])) &&
                (!isset($row['don_vi_nho_nhat']) || empty($row['don_vi_nho_nhat'])) &&
                (!isset($row['mo_ta']) || empty($row['mo_ta']))
            ) {
                unset($rows[$key]);
                continue;
            }
            $isValid = true;
            $validate = $productRepo->validateProducts($row);
            $this->data[] = $validate;
            if (isset($validate['error_list'])) {
                $this->invalidCounts++;
                $this->invalidRows[] = $validate;
                $this->dup[$validate['ten_san_pham']] = $validate;
                continue;
            } else {
                $this->validCounts++;
                $this->validRows[] = $validate;
            }
        }
        foreach ($dupRecords as $sku => $product) {
            if (!isset($product['ProductName']) || empty($product['ProductName'])) {
                continue;
            }

            if (count($product['Category']) < 1) {
                if ($this->invalidCounts < 1) {
                    $this->invalidCounts++;
                }
                $this->handleMsgRows(trim($sku), 'CategoryName', 'Vui lòng nhập tên danh mục');
            } else {
                $categ = [];
                foreach ($product['Category'] as $category) {
                    $keyCateg = Str::slug(trim($product['ProductName']));
                    if (isset($categ[$keyCateg]) && $categ[$keyCateg] == Str::slug(trim($category))) {
                        if ($this->invalidCounts < 1) {
                            $this->invalidCounts++;
                        }
                        $this->handleMsgRows(trim($sku), 'CategoryName', "Danh mục $category bị trùng lặp");
                    } else {
                        $categ[$keyCateg] = Str::slug(trim($category));
                        $checkCateg = Category::where('NameUnsign', Str::slug($category))->where('IsActive', 1)->first();
                        if (!$checkCateg || empty($checkCateg)) {
                            if ($this->invalidCounts < 1) {
                                $this->invalidCounts++;
                            }
                            $this->handleMsgRows(trim($sku), 'CategoryName', "Danh mục $category không tồn tại");
                            // IsNotExistCategory: params cho FE check danh muc
                            $this->setIsNotExistCategory(trim($sku), false);
                        }
                    }
                }
            }
            
            $this->countSKU++;
            $baseUnitCount = 0;
            foreach ($product['Units'] as $unit) {
                if ($unit['IsBaseUnit'] == 1) {
                    $baseUnitCount++;
                }
            }
            if ($baseUnitCount !== 1) {
                if ($this->invalidCounts < 1) {
                    $this->invalidCounts++;
                }
                $this->handleMsgRows($sku, 'IsBaseUnit', "Vui lòng chọn 1 đơn vị tính nhỏ nhất");
            }
        }
        Log::info('---------End Import Product--------------');
    }
    
    public function startRow(): int
    {
        return 2;
    }
    
    public function chunkSize(): int
    {
        return 500;
    }

    public function getData($saveFile = null)
    {
        $invalidRows = $this->invalidRows;
        $validRows = $this->validRows;
        $dataAfterImports = array_merge($invalidRows, $validRows);
        $dataAfterImports[0]['SF'] = $saveFile;

        $arr = $this->formatImportProductData($dataAfterImports, true);

        return $arr;
    }

    public function getResults()
    {
        return [
            'TotalImport' => $this->countSKU,
            'TotalSucceed' => $this->validCounts,
            'TotalError' => $this->invalidCounts,
            'InvalidRecords' => $this->invalidRows,
        ];
    }

    public function isClearnData()
    {
        if ($this->invalidCounts > 0) {
            return false;
        }

        return true;
    }

    public function getInvalidRecords()
    {
        return $this->invalidRows;
    }

    public function getValidRecords()
    {
        return $this->validRows;
    }

    public function getDataBeforeHandle()
    {
        return $this->data;
    }

    public function formatImportProductData($data, $isError = false): array
    {
        $errorArr = [];
        $normalArr = [];

        foreach ($data as $key => $value) {
            $stt = $value['stt'] ?? ($value['STT'] ?? '');
            $barcode = $value['ma_vach'] ?? ($value['Barcode'] ?? '');
            $productName = $value['ten_san_pham'] ?? ($value['ProductName'] ?? '');
            $categoryName = $value['ten_danh_muc'] ?? ($value['CategoryName'] ?? '');
            $unitCode = $value['ma_don_vi'] ?? ($value['Code'] ?? '');
            $unitName = $value['ten_don_vi'] ?? ($value['Name'] ?? '');
            $qtyPerCase = $value['so_luong_quy_doi'] ?? ($value['QtyPerCase'] ?? 0);
            $dvnn = $value['don_vi_nho_nhat'] ??($value['IsBaseUnit'] ?? '');
            $description = $value['mo_ta'] ?? ($value['Description'] ?? '');
            if (
                (!isset($stt) || empty($stt)) &&
                (!isset($barcode) || empty($barcode)) &&
                (!isset($productName) || empty($productName)) &&
                (!isset($categoryName) || empty($categoryName)) &&
                (!isset($unitCode) || empty($unitCode)) &&
                (!isset($unitName) || empty($unitName)) &&
                (!isset($qtyPerCase) || empty($qtyPerCase)) &&
                (!isset($dvnn) || empty($dvnn)) &&
                (!isset($description) || empty($description))
            ) {
                unset($value[$key]);
                continue;
            }
            $row = [
                'Code' => $unitCode,
                'Name' => $unitName,
                'Barcode' => $barcode,
                'ProductName' => $productName,
                'Description' => $description,
                'IsActive' => 1,
                'CreatedBy' => Auth::user()['StaffId'] ?? 0,
                'CreatedDate' => Carbon::now()->toDateTimeString(),
                'UnitId' => $value['UnitId'] ?? 0,
                'CategoryName' => $categoryName,
                'IsBaseUnit' => $dvnn,
                'QtyPerCase' => $qtyPerCase,
            ];

            if ($isError) {
                $row['ErrorList'] = $value['error_list'] ?? '';
                $row['IsNotExistProductName'] = $value['IsNotExistProductName'] ?? true;
                $row['IsNotExistCategory'] = $value['IsNotExistCategory'] ?? true;
                if (!empty($row['ErrorList'])) {
                    $errorArr[$key] = $row;
                    $errorArr[$key]['SF'] = $data[0]['SF'];
                } else {
                    $normalArr[$key] = $row;
                }
            } else {
                $normalArr[$key] = $row;
            }
        }

        $errorRow = [];
        $passRow = [];
        foreach ($errorArr as $row) {
            $errorRow[] = $row;
        }
        foreach ($normalArr as $row) {
            $passRow[] = $row;
        }

        return array_merge($errorRow, $passRow);
    }

    public function formatImportUnitData($data, $isError = false): array
    {
        $errorArr = [];
        $normalArr = [];
        $staffId = Auth::user()['StaffId'];
        $repo = new ProductManagerRepository();

        foreach ($data as $value) {
            $product = $repo->getProductByProductCode($value['SKU'] ?? $value['ma_san_pham'] ?? 0);
            if (!isset($product) || empty($product)) {
                continue;
            }
            $row = [
                'ProductId' => $product->ProductId ?? 0,
                'ActualWeight' => $value['ActualWeight'] ?? $value['trong_luong'] ?? 0,
                'Length' => $value['Length'] ?? $value['chieu_cao'] ?? 0,
                'Width' => $value['Width'] ?? $value['chieu_dai'] ?? 0,
                'Height' => $value['Height'] ?? $value['chieu_rong'] ?? 0,
                'UnitId' => $value['UnitId'] ?? 0,
                'CreatedBy' => $staffId,
                'CreatedDate' => Carbon::now()->toDatetimeString(),
            ];


            if ($isError) {
                $row['ErrorList'] = $value['error_list'] ?? '';
                if (!empty($row['ErrorList'])) {
                    $errorArr[] = $row;
                } else {
                    $normalArr[] = $row;
                }
            } else {
                $normalArr[] = $row;
            }
        }

        return array_merge($errorArr, $normalArr);
    }

    public function mergeData($rows)
    {
        $mergedData = [];
        $prodRepo = new ProductManagerRepository();
        try {
            foreach ($rows as $row) {
                if (!isset($row['ten_san_pham']) || empty($row['ten_san_pham'])) {
                    continue;
                }
    
                $unique = trim($row['ten_san_pham']);
                $explodeCategory = [];

                if (isset($row['don_vi_nho_nhat']) && !is_numeric($row['don_vi_nho_nhat'])) {
                    $slugSeri = Str::slug($row['don_vi_nho_nhat']);
                    $row['don_vi_nho_nhat'] = $slugSeri == 'co' ? 1 : 0;
                }
    
                if (isset($row['ten_danh_muc']) && !empty($row['ten_danh_muc'])) {
                    $explodeCategory = explode(',', $row['ten_danh_muc']);
                }
    
                if (!isset($mergedData[$unique])) {
                    $mergedData[$unique] = [
                        'stt' => $row['stt'] ?? '',
                        'Barcode' => $row['ma_vach'],
                        'ProductName' => $row['ten_san_pham'],
                        'Description' => $row['mo_ta'],
                        'Units' => [],
                        'Category' => []
                    ];
                }
                
                $mergedData[$unique]['Units'][] = [
                    'UnitId' => $row['UnitId'] ?? 0,
                    'Code' => $row['ma_don_vi'],
                    'Name' => $row['ten_don_vi'],
                    'QtyPerCase' => $row['so_luong_quy_doi'],
                    'IsBaseUnit' => $row['don_vi_nho_nhat'],
                ];
                
                if (count($explodeCategory) > 0) {
                    foreach ($explodeCategory as $categoryName) {
                        $exists = false;
                        if (in_array($categoryName, $mergedData[$unique]['Category'])) {
                            $exists = true;
                            break;
                        }
        
                        if (!$exists) {
                            $mergedData[$unique]['Category'][] = trim($categoryName);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Product Manager Import No Data v2: ', [$e->getMessage()]);
            throw new ImportExcelException("File import sai nội dung. Vui lòng kiểm tra lại");
        }

        return $mergedData;
    }

    protected function handleMsgRows($key, $column, $msg)
    {
        $setted = false;
        foreach ($this->invalidRows as $invalidRow) {
            $productName = trim($invalidRow['ProductName'] ?? ($invalidRow['ten_san_pham'] ?? ''));
            if ($key != $productName) {
                continue;
            }
            $this->setErrorMsg($invalidRow, $column, $msg);
        }

        if ($setted) {
            return;
        }

        foreach ($this->validRows as $validRow) {
            $productName = trim($validRow['ProductName'] ?? ($validRow['ten_san_pham'] ?? ''));
            if ($key != $productName) {
                continue;
            }
            $this->setErrorMsg($validRow, $column, $msg);
        }
    }

    protected function setErrorMsg(&$row, $column, $msg)
    {
        if (!empty($row) && isset($row['error_list_non_encode'])) {
            $errorListNonEncode = (object) $row['error_list_non_encode'];
            $errorList = json_decode($row['error_list'], true);

            if (isset($errorListNonEncode->$column) && !empty($errorListNonEncode->$column)) {
                $errorListNonEncode->$column .= ". $msg";
            } else {
                $errorListNonEncode->$column = "$msg";
            }
            $row['error_list_non_encode'] = $errorListNonEncode;
    
            if (isset($errorList["$column"]) && !empty($errorList["$column"])) {
                $errorList["$column"] .= ". $msg";
            } else {
                $errorList["$column"] = "$msg";
            }
            
            $row['error_list'] = json_encode($errorList);
        } else {
            $row['error_list_non_encode'] = (object) ["$column" => "$msg"];
            $row['error_list'] = json_encode(["$column" => "$msg"]);
        }
    }

    protected function setIsNotExistCategory($key, $bool)
    {
        $setted = false;
        foreach ($this->invalidRows as $key2 => $invalidRow) {
            $productName = trim($invalidRow['ProductName'] ?? ($invalidRow['ten_san_pham'] ?? ''));
            if ($key == $productName) {
                $invalidRow['IsNotExistCategory'] = $bool;
            }
        }

        if ($setted) {
            return;
        }

        foreach ($this->validRows as $key3 => $validRow) {
            $productName = trim($validRow['ProductName'] ?? ($validRow['ten_san_pham'] ?? ''));
            if ($key != $productName) {
                $validRow['IsNotExistCategory'] = $bool;
            }
        }
    }

    protected function getUniqueData($value)
    {
        if (isset($value['ten_san_pham']) || isset($value['ProductName'])) {
            return trim($value['ten_san_pham'] ?? ($value['ProductName'] ?? ''));
        }

        return '';
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
}
