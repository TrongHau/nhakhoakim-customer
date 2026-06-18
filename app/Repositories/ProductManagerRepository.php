<?php

namespace App\Repositories;

use App\Category;
use App\Exports\ReportExport;
use App\Repositories\Abstracts\EloquentRepository;
use App\Imports\ProductManagerImport;
use App\InvProduct;
use App\InvProductExpired;
use App\IR;
use App\IRDetail;
use App\Libs\Helper;
use App\ORDetail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Product;
use App\ProductBrand;
use App\ProductCategory;
use App\ProductHistory;
use App\ProductImage;
use App\ProductLog;
use App\ProductOR;
use App\ProductPricing;
use App\ProductTracking;
use App\ProductUnit;
use App\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use PDO;

class ProductManagerRepository extends EloquentRepository
{
    public const TYPE_WARNING_EXPIRED = [
        'lessThanThreeMonth' => 1,
        'lessThanSixMonth' => 2,
        'lessThanNineMonth' => 3,
        'expired' => 100,
    ];

    protected $productCodes = [];
    private $isBaseUnit = [];
    private $countBaseUnit = 0;
    private $debug = true;
    private $unit = [];
    private $latestTrackingId = 1;
    private $productCategoryKey = [];

    protected function getModel()
    {
        return Product::class;
    }

    public function checkProductBySku($sku)
    {
        $query = $this->_model->where('IsActive', 1)->where('SKU', $sku)->first();
        return $query;
    }

    public function checkProductUpdateById($productId, $updateData)
    {
        return $this->_model->where('ProductId', $productId)->where('IsActive', 1)->where('UpdatedDate', $updateData)->first();
    }

    public function checkProductByName($name, $productId = null)
    {
        $query = $this->_model->where('IsActive', 1)
            ->where('ProductName', trim($name));
        if ($productId) {
            $query->where('ProductId', '!=', $productId);
        }

        return $query->orderbyDesc('ProductId')->first();
    }

    public function save($data)
    {
        $branchId = $data['BranchId'] ?? 0;
        $currentBranchId = $data['CurrentBranchId'] ?? 0;
        $staffId = Auth::user()['StaffId'] ?? 0;

        if (!$branchId || empty($branchId)) {
            $branchId = $currentBranchId ?? 0;
        }

        try {
            if (!isset($data['BrandId']) || empty($data['BrandId']) || !is_numeric($data['BrandId']) ) {
                $data['BrandId'] = 0;
            }
            if (!isset($data['Unit'])) {
                $data['Unit'] = [];
            }
            $createData = [
                'Barcode' => $data['Barcode'] ?? '',
                'ProductName' => $data['ProductName'] ?? '',
                'Description' => $data['Description'] ?? '',
                'IsTrackingSerial' => $data['SerialType'] ?? 0,
                'IsExpiryDate' => $data['ExpirationManagement'] ?? 0,
                'RefPrice' => $data['RefPrice'] ?? 0,
                'NameUnsign' => Str::slug($data['ProductName'], "-"),
                'IsActive' => 1,
                'CreatedBy' => $staffId,
                'UpdatedBy' => $staffId,
                'CreatedDate' => Carbon::now()->toDatetimeString(),
                'UpdatedDate' => Carbon::now()->toDatetimeString(),
                'BrandId' => $data['BrandId'] ?? 0,
                'ProductConditionId' => 1,
            ];

            DB::beginTransaction();
            $productId = DB::table('sale.Product')->insertGetId($createData);
            if ($productId) {
                foreach ($data['Unit'] as $v) {
                    $baseUnitCount = 1;
                    if (!isset($v['IsBaseUnit']) || empty($v['IsBaseUnit'])) {
                        $v['IsBaseUnit'] = 0;
                    }
                    if (isset($v['QtyPerCase']) && $v['QtyPerCase'] == 1) {
                        if ($baseUnitCount > 1) {
                            DB::rollBack();
                            return false;
                        }
                        $baseUnitCount += 1;
                    }
                    $prodUnitData = [
                        'ProductId' => $productId,
                        'QtyPerCase' => $v['QtyPerCase'] ?? 0,
                        'UnitId' => $v['UnitId'] ?? 0,
                        'SalePrice' => $v['SalePrice'] ?? 0,
                        'CostPrice' => $v['CostPrice'] ?? 0,
                        'IsBaseUnit' => $v['IsBaseUnit'] ?? 0,
                        'CreatedBy' => $staffId,
                        'CreatedDate' => Carbon::now()->toDatetimeString(),
                        'LatestTrackingId' => 0
                    ];
                    ProductUnit::insert($prodUnitData);
                }
                $categoryIds = $data['Category'] ?? [];
                $categoryIds = array_unique($categoryIds); //remove Category duplicate
                foreach ($categoryIds as $v) {
                    $categories = [
                        'ProductId' => $productId,
                        'CategoryId' => $v,
                        'CreatedBy' => $staffId,
                        'CreatedDate' => Carbon::now()->toDatetimeString(),
                        'IsDeleted' => 0,
                    ];
                    ProductCategory::insert($categories);
                }
                DB::commit();

                $prod = Product::where('ProductId', $productId)->where('IsActive', 1)->first();
                if ($prod && !empty($prod)) {
                    $sku = $this->generateSKU();
                    $prod->update([
                        'SKU' => $sku
                    ]);
                }
            }
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("CreateProduct error", [$e->getMessage()]);
            return false;
        }

        return false;
    }

    public function saveImport($data)
    {
        $branchId = $data['BranchId'] ?? 0;
        $staffId = Auth::user()['StaffId'] ?? 0;

        DB::beginTransaction();
        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
        try {
            $products = [];
            foreach ($data as $value) {
                $unitId = $this->getOrInsertUnitByCode($value['Name'], $value['Code']) ?? 0;
                
                $productId = NULL;
                $product = Product::where('ProductName', trim($value['ProductName']))
                    ->where('IsActive', 1)
                    ->orderByDesc('ProductId')
                    ->first();
                if (isset($product) && !empty($product)) {
                    $productId = $product->ProductId;
                } else {
                    $productId = $this->createProductGetId($value);
                }
                if ($productId) {
                    $products[] = [
                        "ProductId" => $productId
                    ];
                    $checkUnitId = ProductUnit::where('ProductId', $productId)
                        ->where('UnitId', $unitId)
                        ->first();
                    if (
                        !$checkUnitId
                        && (!$this->saveImportPU($productId, $value, $unitId) || !$this->saveImportPC($productId, $value))
                    ) {
                        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
                        DB::rollBack();
                        return false;
                    }
                }
            }
            $uniqueProduct = [];
            
            foreach ($products as $item) {
                $productId = $item['ProductId'];
                
                if (!in_array($productId, $uniqueProduct)) {
                    $uniqueProduct[] = $productId;
                }
            }
            $count = count($uniqueProduct);
            $importHistory = [
                'BranchId' => $branchId,
                'TotalBill' => $count,
                'TotalSuccess' => $count,
                'TotalFail' => 0,
                'LinkSource' => $data[0]['SF'] ?? '',
                'LinkError' => null,
                'CreatedBy' => $staffId ?? 0,
                'CreatedDate' => Carbon::now()->toDateTimeString(),
            ];
            $productHistoryRepo = new ProductHistoryRepository();
            $tracking = $productHistoryRepo->trackingImport($importHistory);
            if ($tracking) {
                DB::commit();
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
                foreach ($products as $productData) {
                    $prod = Product::where('ProductId', $productData['ProductId'])->first();
                    if ($prod && !empty($prod)) {
                        $sku = $this->generateSKU();
                        $prod->update([
                            'SKU' => $sku
                        ]);
                    }
                }
                return true;
            }
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
            DB::rollBack();
            Log::error("Tracking error");
            return false;
        } catch (\Exception $e) {
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
            DB::rollBack();
            Log::error("CreateProduct error", [$e->getMessage()]);
            return false;
        }
        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        DB::rollBack();
        return false;
    }

    // insert product unit in saveImport
    public function saveImportPU($productId, $value, $unitId)
    {
        $staffId = Auth::user()['StaffId'] ?? 0;
        try {

            $productUnitData = [
                'ProductId' => $productId,
                'QtyPerCase' => $value['QtyPerCase'],
                'UnitId' => $unitId ?? 0,
                'IsBaseUnit' => $value['IsBaseUnit'],
                'CreatedBy' => $staffId,
                'CreatedDate' => Carbon::now()->toDatetimeString(),
                'LatestTrackingId' => 0,
            ];
            return ProductUnit::insert($productUnitData);
        } catch (\Exception $e) {
            Log::error("Error save ProductUnit", [$e->getMessage()]);
        }

        return false;
    }

    public function saveImportPC($productId, $value)
    {
        $staffId = Auth::user()['StaffId'] ?? 0;
        try {
            $explode = array_unique(explode(',', $value['CategoryName']));
            $productCategory = [];
            foreach ($explode as $categoryName) {
                $checkCategory = Category::where('NameUnsign', Str::slug($categoryName))->where('IsActive', 1)->first();
                if ($checkCategory && !empty($checkCategory)) {
                    $exists = $checkCategory->products()
                        ->where('ProductCategory.IsDeleted', 0)
                        ->first();
                    $categoryKey = $productId . '-' . $checkCategory->CategoryId;
                    if (isset($this->productCategoryKey[$productId]) && $this->productCategoryKey[$productId] === $categoryKey) {
                        return true;
                    }
                    if (!$exists) {
                        $this->productCategoryKey[$productId] = $categoryKey;
                        $productCategory[] = [
                            'ProductId' => $productId,
                            'CategoryId' => $checkCategory->CategoryId,
                            'CreatedBy' => $staffId,
                            'CreatedDate' => Carbon::now()->toDateTimeString(),
                            'IsDeleted' => 0,
                        ];
                    }
                }
            }

            if (!empty($productCategory)) {
                return ProductCategory::insert($productCategory);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error save ProductCategory", [$e->getMessage()]);
        }
        DB::rollBack();
        return false;
    }

    public function createProductGetId($value)
    {
        $staffId = Auth::user()['StaffId'] ?? 0;
        $dataInsert = [
            'SKU' => $this->generateSKU(),
            'Barcode' => $value['Barcode'] ?? '',
            'ProductName' => $value['ProductName'] ?? '',
            'Description' => $value['Description'] ?? '',
            'IsTrackingSerial' => $value['IsTrackingSerial'] ?? 0,
            'IsExpiryDate' => $value['IsExpiryDate'] ?? 0,
            'AvatarURL' => DEFAULT_IMG_URL,
            'NameUnsign' => Str::slug($value['ProductName'], "-"),
            'IsActive' => 1,
            'CreatedBy' => $staffId,
            'CreatedDate' => Carbon::now()->toDatetimeString(),
            'UpdatedBy' => $staffId,
            'UpdatedDate' => Carbon::now()->toDatetimeString(),
            'ProductConditionId' => 1,
        ];
        $productId = Product::insertGetId($dataInsert);

        return $productId;
    }

    public function getListProduct($data)
    {
        $keyword = $data['Keyword'] ?? '';
        $searchType = $data['SearchType'] ?? 0;
        $type = $data['Type'] ?? 1;
        $lmstart = $data['lmstart'] ?? 0;
        $limit = $data['limit'] ?? 20;

        $query = $this->_model->newQuery();

        $query->select(
            'Product.ProductId',
            'Product.SKU',
            'Product.Barcode',
            'Product.ProductName',
            'Product.Description',
            'Product.IsTrackingSerial',
            'Product.IsExpiryDate',
            'Product.RefPrice',
            'Product.NameUnsign',
            'Product.IsActive',
            'Product.CreatedBy',
            'Product.CreatedDate',
            'Product.UpdatedBy',
            'Product.UpdatedDate',
            'Product.AvatarURL',
            'Product.BrandId',
            'Product.ProductConditionId'
        )->from('Product');

        if ($type == 2) {
            $query->leftJoin('ProductUnit as pu', 'pu.ProductId', 'Product.ProductId')
                ->leftJoin('Unit as u', 'u.UnitId', 'pu.UnitId')
                ->addSelect('u.UnitId', 'u.Code as UnitCode', 'u.Name as UnitName', 'pu.CostPrice', 'pu.SalePrice');
        }

        if (isset($keyword) && !empty($keyword)) {
            // anh Binh yeu cau filter chinh xac
            switch ($searchType) {
                case 1:
                    $query->where('Product.SKU', 'LIKE', '%' . $keyword . '%');
                    break;
                case 2:
                    $query->where('Product.ProductName', 'LIKE', '%' . $keyword . '%');
                    break;
                case 3:
                    $query->where('Product.Barcode', 'LIKE', '%' . $keyword . '%');
                    break;

                default:
                    $query->where(function ($q) use ($keyword) {
                        $q->where('Product.SKU', 'LIKE', '%' . $keyword . '%')
                            ->orWhere('Product.ProductName', 'LIKE', '%' . $keyword . '%')
                            ->orWhere('Product.Barcode', 'LIKE', '%' . $keyword . '%');
                    });
                    break;
            }
        }
        $query->where('Product.IsActive', 1);

        if ($type == 1) {
            $query->with([
                'productUnit.unit' => function ($query) {
                    $query->select();
                },
            ]);
        }

        $query->orderByDesc('Product.ProductId');

        if (isset($lmstart) && empty($lmstart)) {
            $lmstart = 0;
        }
        if (isset($limit) && empty($limit)) {
            $limit = 20;
        }

        $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);
        $productIds = $query->pluck('ProductId')->toArray();
        $orderDetails = DB::table('sale.ORDetail')
            ->whereIn('ProductId', $productIds)
            ->get()
            ->groupBy('ProductId');

        foreach ($result as $value) {
            if (!isset($value->AvatarURL) || empty($value->AvatarURL)) {
                $value->AvatarURL = DEFAULT_IMG_URL;
            }
            $imgDetails = $this->getProductImageByProduct($value->ProductId);
            $value->ImageDetails = $imgDetails;

            $units = DB::table('sale.ProductUnit as pu')->join('sale.Unit as u', 'u.UnitId', 'pu.UnitId')->where('pu.ProductId', $value->ProductId);
            if ($units->count() > 0 && $type != 2) {
                $unitNames = $units->get()->pluck('Name')->toArray();
                $value->UnitNameFormat = implode(', ', $unitNames);
            }

            $categ = DB::table('sale.ProductCategory as pc')
                ->select('c.CategoryId', 'c.CategoryName')
                ->join('sale.Category as c', 'c.CategoryId', 'pc.CategoryId')
                ->where('pc.ProductId', $value->ProductId)
                ->where('pc.IsDeleted', 0)
                ->get();
            $value->CategoryNameFormat = '';
            if ($categ && !empty($categ) && $categ->count() > 0 && $type != 2) {
                $categoryName = $categ->pluck('CategoryName')->toArray();
                $value->CategoryNameFormat = implode(', ', $categoryName);
            }

            $newQuery = DB::table('sale.InvProduct')
                ->where('InvProduct.ProductId', $value->ProductId);
            if ($type == 2) {
                $value->inv_product = $newQuery->join('Unit as u', 'u.UnitId', 'InvProduct.UnitId')
                    ->select('InvProduct.ProductId', 'InvProduct.AvailableQty', 'InvProduct.PendingInQty', 'InvProduct.UnitId', 'u.Code', 'u.Name')
                    ->where('InvProduct.UnitId', $value->UnitId)
                    ->first();
            } else {
                $ip = $newQuery->get();
                $availableQty = 0;
                $pendingOutQty = 0;
                foreach ($ip as $v) {
                    $availableQty += ($v->AvailableQty ?? 0);
                    $pendingOutQty += ($v->PendingOutQty ?? 0);
                }
                $value->TotalQuantity = $availableQty + $pendingOutQty;
                $value->AvailableQty = $availableQty;
                $value->PendingOutQty = $pendingOutQty;
            }

            $orDetail = $orderDetails->get($value->ProductId, []);
            $value->IsDelete = empty($orDetail) && !$this->isEditBlocked($value->ProductId);
        }
        return $result;
    }

    public function updateProduct($data, $image)
    {
        $staffId = Auth::user()['StaffId'] ?? 0;
        $productId = $data['ProductId'] ?? 0;
        $productName = $data['ProductName'] ?? '';
        $unit = $data['Unit'] ?? [];
        $category = $data['Category'] ?? [];
        $description = $data['Description'] ?? '';
        $barcode = $data['Barcode'] ?? '';
        $images = $data['Image'] ?? '';
        $serialType = $data['SerialType'] ?? 0;
        $expirationManagement = $data['ExpirationManagement'] ?? 0;

        $prodTracking = Product::where('ProductId', $productId)->first()->toArray();
        $prodUnitTracking = ProductUnit::where('ProductId', $productId)->get()->toArray();
        $prodCategTracking = ProductCategory::where('ProductId', $productId)->get()->toArray();

        $updateData = [
            'UpdatedBy' => $staffId,
            'UpdatedDate' => Carbon::now()->toDateTimeString()
        ];
        $query = $this->_model->newQuery();
        $currentProduct = $query->join('ProductUnit as pu', 'pu.ProductId', 'Product.ProductId')
            ->where('Product.ProductId', $productId)
            ->where('IsActive', 1)
            ->get();
        // Kiểm tra trùng lặp tên sản phẩm trong client
        $firtsRecord = $currentProduct->first();
        $this->latestTrackingId = ($firtsRecord->LatestTrackingId ?? 0) + 1;
        if ($firtsRecord && !empty($firtsRecord) && $firtsRecord->ProductName != $productName) {
            $updateData['ProductName'] = $productName;
        }
        if (isset($description) && !empty($description)) {
            $updateData['Description'] = $description;
        }
        
        // Kiểm tra tồn kho và phiếu nhập xuất
        // Nếu 1 trong 2 tồn tại thì hàm trả true và không cho phép sửa Barcode, IsTrackingSerial, IsExpiryDate
        $isEditBlocked = $this->isEditBlocked($productId);
        if (!$isEditBlocked) {
            $updateData['Barcode'] = $barcode;
            if (isset($serialType) && is_numeric($serialType)) {
                $updateData['IsTrackingSerial'] = $serialType;
            }
            if (isset($expirationManagement) && is_numeric($expirationManagement)) {
                $updateData['IsExpiryDate'] = $expirationManagement;
            }
        }
        $updateData['AvatarURL'] = DEFAULT_IMG_URL;
        DB::beginTransaction();
        try {
            $query = $this->_model->newQuery();
            $result = $query->where('ProductId', $productId)->update($updateData);
            if (!$result || empty($result)) {
                return false;
            }
            // Nếu có hình ảnh thì cập nhật
            // Chưa có thì thêm mới, không còn thì xóa
            if (isset($images) && !empty($images) && is_array($images)) {
                $this->syncImage($productId, $images);
            } else {
                ProductImage::where('ProductId', $productId)
                    ->update(['IsDeleted' => 1, 'UpdatedBy' => $staffId, 'UpdatedDate' => Carbon::now()->toDateTimeString()]);
            }
            if (isset($unit) && !empty($unit) && is_array($unit)) {
                $unitUpdate = $this->mapUpdateData($unit); // loại bỏ các field không update
                $this->updateUnit($productId, $unitUpdate); // cập nhật đơn vị
            }
            if (isset($category) && !empty($category) && is_array($category)) {
                foreach ($category as $key => $cat) {
                    $category[$key] = [
                        'ProductId' => $productId,
                        'CategoryId' => $cat,
                        'IsDeleted' => 0,
                    ];
                }
                $categoriesUpdate = $this->mapUpdateData($category);
                $this->reloadCategories($productId, $categoriesUpdate); // xóa danh mục cũ và tạo danh mục mới
            }
            if ($this->productTracking($productId, [
                    'Product' => $prodTracking, 
                    'ProductCategory' => $prodCategTracking, 
                    'ProductUnit' => $prodUnitTracking
                ])) {
                $query = $this->_model->newQuery();
                $result = ProductUnit::where('ProductId', $productId)->update([
                    'LatestTrackingId' => $this->latestTrackingId
                ]);
                DB::commit();
                return true;
            }
        } catch (\Exception $e) {
            Log::error("Error update product", [$e->getMessage()]);
        }
        
        DB::rollBack();
        return false;
    }

    protected function productTracking($productId, $oldData = [])
    {
        if (empty($productId) || empty($oldData)) {
            return false;
        }
        
        $staffId = Auth::user()['StaffId'] ?? 0;
        $trackingData = [];
        
        DB::beginTransaction();
        try {
            // Lấy dữ liệu cũ đã lưu (mảng gồm 3 phần: sản phẩm, danh mục, đơn vị)
            $oldProd    = $oldData['Product'] ?? [];
            $oldCateg   = $oldData['ProductCategory'] ?? [];
            $oldUnit    = $oldData['ProductUnit'] ?? [];
            
            // Lấy dữ liệu mới từ DB sau khi update
            $newProdObj = Product::where('ProductId', $productId)
                ->first();
            if (!$newProdObj) {
                return false;
            }
            $newProd = $newProdObj->toArray();
    
            $newUnits  = ProductUnit::where('ProductId', $productId)->get()->toArray();
            $newCateg  = ProductCategory::where('ProductId', $productId)->get()->toArray();
    
            // Compare từng cột của sản phẩm
            foreach ($newProd as $column => $newValue) {
                $oldValue = $oldProd[$column] ?? NULL;
                if ($oldValue != $newValue && $column != '') {
                    $trackingData[] = [
                        'ProductId'     => $productId,
                        'UnitId'        => NULL,
                        'CreatedBy'     => $staffId,
                        'CreatedDate'   => Carbon::now()->toDateTimeString(),
                        'TrackingId'    => $this->latestTrackingId,
                        'Name'          => $column,
                        'OldValue'      => $oldValue,
                        'NewValue'      => $newValue,
                        'Action'        => 'UPDATE',
                    ];
                }
            }
    
            // Compare từng cột của các đơn vị (Unit)
            // Tạo mảng index theo UnitId từ dữ liệu cũ
            $oldUnitIndexed = [];
            foreach ($oldUnit as $unit) {
                if (isset($unit['UnitId'])) {
                    $oldUnitIndexed[$unit['UnitId']] = $unit;
                }
            }
            foreach ($newUnits as $unit) {
                $unitId = $unit['UnitId'] ?? NULL;
                if (!$unitId) {
                    continue;
                }
                $oldUnitData = $oldUnitIndexed[$unitId] ?? [];
                foreach ($unit as $column => $newValue) {
                    $oldValue = $oldUnitData[$column] ?? NULL;
                    if ($oldValue != $newValue) {
                        $trackingData[] = [
                            'ProductId'     => $productId,
                            'UnitId'        => $unitId,
                            'CreatedBy'     => $staffId,
                            'CreatedDate'   => Carbon::now()->toDateTimeString(),
                            'TrackingId'    => $this->latestTrackingId,
                            'Name'          => $column,
                            'OldValue'      => $oldValue,
                            'NewValue'      => $newValue,
                            'Action'        => 'UPDATE UNIT',
                        ];
                    }
                }
            }
    
            // Compare từng cột của các danh mục (Category)
            // Tạo mảng index theo CategoryId từ dữ liệu cũ
            $oldCategIndexed = [];
            foreach ($oldCateg as $cat) {
                if (isset($cat['CategoryId'])) {
                    $oldCategIndexed[$cat['CategoryId']] = $cat;
                }
            }
            foreach ($newCateg as $cat) {
                $catId = $cat['CategoryId'] ?? NULL;
                if (!$catId) {
                    continue;
                }
                $oldCatData = $oldCategIndexed[$catId] ?? [];
                foreach ($cat as $column => $newValue) {
                    $oldValue = $oldCatData[$column] ?? NULL;
                    if ($oldValue != $newValue) {
                        $trackingData[] = [
                            'ProductId'     => $productId,
                            'UnitId'        => NULL,
                            'CreatedBy'     => $staffId,
                            'CreatedDate'   => Carbon::now()->toDateTimeString(),
                            'TrackingId'    => $this->latestTrackingId,
                            'Name'          => $column,
                            'OldValue'      => $oldValue,
                            'NewValue'      => $newValue,
                            'Action'        => 'UPDATE CATEGORY ' . $catId,
                        ];
                    }
                }
            }

            $saveTracking = ProductTracking::insert($trackingData);

            // Cập nhật version tracking
            $upVersion = ProductUnit::where('ProductId', $productId)
                ->update(['LatestTrackingId' => $this->latestTrackingId]);
    
            if ($saveTracking && $upVersion) {
                DB::commit();
                return true;
            }
        } catch (\Exception $e) {
            Log::error("Error tracking data", [$e->getMessage()]);
        }
        DB::rollBack();
        return false;
    }

    public function isEditBlocked($productId)
    {
        if (!isset($productId) || empty($productId)) {
            return false;
        }
        $checkInventory = InvProduct::where('ProductId', $productId)->where('AvailableQty', '>', 0)->exists(); // kiểm tra tồn kho
        $checkIR = IR::join('IRDetail as ird', 'IR.IRId', 'ird.IRId')->where('ird.ProductId', $productId)->where('IR.IRStatus', '!=', 4)->exists(); // kiểm tra phiếu nhập xuất
        return $checkInventory || $checkIR; // chỉ cần 1 trong 2 tồn tại thì trả ra true
    }

    protected function mapUpdateData($arr)
    {
        $staffId = Auth::user()['StaffId'] ?? 0;
        $data = [];
        foreach ($arr as $key => $value) {
            $data[$key] = [];
            // loại bỏ các field không update
            foreach ($value as $k => $v) {
                if (isset($v) && !empty($v) && trim(strtolower($v)) != 'null') {
                    $data[$key] = array_merge($data[$key], [$k => $v]);
                }
            }
            if (empty($data[$key])) {
                unset($data[$key]);
            }
            $data[$key] = array_merge($data[$key], [
                'UpdatedBy' => $staffId,
                'UpdatedDate' => Carbon::now()->toDateTimeString()
            ]);
        }
        return $data;
    }

    protected function updateUnit($productId, $values)
    {
        if (!isset($values) || empty($values) || !isset($productId) || empty($productId)) {
            return false;
        }

        if (!is_array($values)) {
            $values = [$values];
        }
        $flatData = [];
        $fields = ['QtyPerCase', 'Volume', 'IsBaseUnit', 'UpdatedBy', 'UpdatedDate'];

        foreach ($values as $newValue) {
            $unitId = $newValue['UnitId'];
        
            foreach ($fields as $field) {
                if (isset($newValue[$field]) && !empty($newValue[$field])) {
                    $flatData[$unitId][$field] = $newValue[$field];
                }
            }
        }

        $rs = false;
        if (!empty($flatData)) {
            foreach ($flatData as $unitId => $data) {
                $rs = ProductUnit::where('ProductId', $productId)->where('UnitId', $unitId)->update($data);
            }
        }
        return $rs;
    }

    protected function reloadCategories($productId, $values)
    {
        if (!isset($values) || empty($values) || !isset($productId) || empty($productId)) {
            return false;
        }

        if (!is_array($values)) {
            $values = [$values];
        }
        $staffId = Auth::user()['StaffId'] ?? 0;
        $now = Carbon::now()->toDateTimeString() ?? '';
        $resultUpdate = $resultDelete = $resultInsert = true;

        // Lấy danh sách CategoryId hiện có của sản phẩm
        $existingCategories = ProductCategory::where('ProductId', $productId)
            ->pluck('CategoryId')
            ->toArray();

        // Lấy danh sách CategoryId mới từ values
        $newCategories = array_column($values, 'CategoryId');

        // Xác định danh mục cần xóa (không có trong danh sách mới)
        $categoriesToDelete = array_diff($existingCategories, $newCategories);
        if (!empty($categoriesToDelete)) {
            $resultDelete = ProductCategory::where('ProductId', $productId)
                ->whereIn('CategoryId', $categoriesToDelete)
                ->delete();
        }

        // Xác định danh mục cần cập nhật lại (có trong cả hai danh sách)
        $categoriesToUpdate = array_intersect($existingCategories, $newCategories);
        if (!empty($categoriesToUpdate)) {
            $resultUpdate = ProductCategory::where('ProductId', $productId)
                ->whereIn('CategoryId', $categoriesToUpdate)
                ->update(['UpdatedBy' => $staffId, 'UpdatedDate' => $now]);
        }

        // Xác định danh mục cần thêm mới
        $categoriesToAdd = array_diff($newCategories, $existingCategories);
        if (!empty($categoriesToAdd)) {
            $insertData = [];
            foreach ($categoriesToAdd as $categoryId) {
                $insertData[] = [
                    'ProductId' => $productId,
                    'CategoryId' => $categoryId,
                    'CreatedBy' => $staffId,
                    'CreatedDate' => $now,
                    'UpdatedBy' => $staffId,
                    'UpdatedDate' => $now,
                ];
            }
            $resultInsert = ProductCategory::insert($insertData);
        }

        return ($resultUpdate && $resultDelete && $resultInsert) ?? false;        
    }

    protected function syncImage($productId, $images)
    {
        $staffId = Auth::user()['StaffId'] ?? 0;

        // Lấy danh sách hình ảnh hiện tại theo Priority
        $existingImages = ProductImage::where('ProductId', $productId)
        ->where('IsDeleted', 0)
        ->pluck('ProductImageId', 'Priority')
        ->toArray();

        try {
            foreach ($images as $key => $imageURL) {
                $priority = $key + 1; // Key 0 -> Priority 1, Key 1 -> Priority 2, Key 2 -> Priority 3
                if (is_array($imageURL) && isset($imageURL['URLCDN']) && !empty($imageURL['URLCDN'])) {
                    continue;
                }
                $responeMedia = Helper::uploadFileToServer($imageURL, 'Product');
                $img = API_MEDIA . '/' . $responeMedia;
    
                if (isset($existingImages[$priority])) {
                    ProductImage::where('ProductImageId', $existingImages[$priority])
                        ->update([
                            'URLCDN' => $img,
                            'UpdatedBy' => $staffId,
                            'UpdatedDate' => Carbon::now()->toDateTimeString(),
                        ]);
                } else {
                    $imgInsertData = [
                        'ProductId' => $productId,
                        'URLCDN' => $img,
                        'Priority' => $priority,
                        'CreatedBy' => $staffId,
                        'CreatedDate' => Carbon::now()->toDateTimeString(),
                        'UpdatedBy' => $staffId,
                        'UpdatedDate' => Carbon::now()->toDateTimeString(),
                        'IsDeleted' => 0,
                    ];
                    ProductImage::insert($imgInsertData);
                }
            }
    
            // Xóa ảnh có Priority không có trong uploadedFiles
            $receivedPriorities = array_map(function ($key) {
                return $key + 1;
            }, array_keys($images));
            $prioritiesToDelete = array_diff(array_keys($existingImages), $receivedPriorities);
    
            if (!empty($prioritiesToDelete)) {
                ProductImage::where('ProductId', $productId)
                    ->whereIn('Priority', $prioritiesToDelete)
                    ->update(['IsDeleted' => 1, 'UpdatedBy' => $staffId, 'UpdatedDate' => Carbon::now()->toDateTimeString()]);
            }
        } catch (\Exception $e) {
            Log::error("Sync Image error: ", [$e->getMessage()]);
            return false;
        }

        return true;
    }

    public function getListProductTreatment($data) 
    {
        $isCheckInventory = false;
        $keyword = $data['Keyword'] ?? '';
        $branchId = $data['BranchId'] ?? 0;
        $lmstart = $data['lmstart'] ?? 0;
        $limit = $data['limit'] ?? 20;
        $now = Carbon::now()->toDateTimeString();

        if (!isset($branchId) || empty($branchId)) {
            $branchId = $currentBranchId ?? 0;
        }
        $query = $this->_model->newQuery();

        $query->select(
            'p.ProductId',
            'p.SKU',
            'p.Barcode',
            'p.ProductName',
            'p.IsActive',
            'ip.UnitId',
            'ip.ConditionTypeId as ProductConditionId'
        )->from('sale.Product as p'); 
        $query->leftJoin('sale.InvProduct as ip', 'ip.ProductId', 'p.ProductId')
            ->addSelect('ip.AvailableQty'); 
        if ($isCheckInventory) { 
            $query->where('ip.AvailableQty', '>', 0);
        } 
        if (is_numeric($branchId) && $branchId >= 0) {
            $query->where('ip.BranchId', $branchId);
        } else {
            return [];
        }
        if (isset($keyword) && !empty($keyword)) {
            $query->where(function ($q) use ($keyword) {
                $q->where('p.SKU', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('p.ProductName', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('p.Barcode', 'LIKE', '%' . $keyword . '%');
            });
        }
        $query->where('p.IsActive', 1);

        $query->orderByDesc('p.ProductId');

        $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart/ (int) $limit) + 1);

        foreach ($result as $key => $value) {
            $data = self::getUnitName($value['ProductId'], $value['UnitId']);
            $salePrice = 0;
            $name = '';
            if ($data) {
                $salePrice = $data->SalePrice;
                $name = $data->Name;
            }
            $result[$key]['SalePrice'] = $salePrice;
            $result[$key]['UnitName'] = $name;
            
        }
        return $result; 
    }

    public function getUnitName($productId, $unitId)
    {
        $units = DB::table('sale.ProductUnit as pu')->select(['pu.SalePrice', 'u.Name'])->join('sale.Unit as u', 'u.UnitId', 'pu.UnitId')->where('pu.ProductId', $productId)->where('pu.UnitId', $unitId)->first();
        return $units;
    }

    public function getDetailProduct($id)
    {
        $query = $this->_model->newQuery()
            ->select(
                'ProductId',
                'Title',
                'SKU',
                'Barcode',
                'ProductName',
                'Description',
                'IsTrackingSerial',
                'IsExpiryDate',
                'RefPrice',
                'NameUnsign',
                'IsActive',
                'CreatedBy',
                'CreatedDate',
                'UpdatedBy',
                'UpdatedDate',
                'AvatarURL',
                'BrandId'
            )
            ->where('ProductId', $id)
            ->where('IsActive', 1);

        $result = $query->first();

        if (!isset($result) || empty($result)) {
            return false;
        }
        $unitQuery = $this->_model->newQuery();
        $unitQuery->select(
            'pu.ProductId',
            'pu.UnitId',
            'pu.QtyPerCase',
            'pu.Volume',
            'pu.Length',
            'pu.Width',
            'pu.Height',
            'pu.ActualWeight',
            'pu.CostPrice',
            'pu.SalePrice',
            'pu.IsBaseUnit',
            'pu.CreatedBy',
            'pu.CreatedDate',
            'pu.UpdatedBy',
            'pu.UpdatedDate',
            'um.Code',
            'um.Name',
            'um.NameUnsign'
        )->from('ProductUnit as pu')
            ->join('Unit as um', 'um.UnitId', 'pu.UnitId')
            ->where('pu.ProductId', $id)
            ->orderBy('pu.IsBaseUnit', 'DESC');

        $unit = $unitQuery->get()->toArray();
        if (isset($unit['UnitId']) && !empty($unit['UnitId'])) {
            $unit = $unitQuery->get()->toArray();
        }
        $result->Unit = $unit;

        $categories = $this->_model->newQuery()
            ->from('Category as c')
            ->join('ProductCategory as pc', 'pc.CategoryId', 'c.CategoryId')
            ->where('c.IsActive', 1)
            ->where('pc.ProductId', $id)
            ->where('pc.IsDeleted', 0)
            ->get();
        $categoryName = '';
        foreach ($categories as $value) {
            $categ = DB::table('sale.ProductCategory as pc')
                ->select('c.CategoryId', 'c.CategoryName')
                ->join('sale.Category as c', 'c.CategoryId', 'pc.CategoryId')
                ->where('pc.ProductId', $value->ProductId)
                ->where('pc.IsDeleted', 0);
            if ($categ->count() > 0) {
                $categoryName = $categ->get()->pluck('CategoryName')->toArray();
            }
        }

        $result->Category = $categories;
        $categoryName ? $result->CategoryNameFormat = implode(', ', $categoryName) : '';

        $result->ImageDetails = $this->getProductImageByProduct($id);
        $invProds = DB::table('sale.InvProduct as ip')
            ->select('ip.*', 'b.BranchCode', 'b.Name as BranchName', 'u.Code', 'u.Name')
            ->leftJoin('in.Branch as b', 'b.BranchId', 'ip.BranchId')
            ->leftJoin('sale.Unit as u', 'u.UnitId', 'ip.UnitId')
            ->where('ip.ProductId', $id)
            ->get();

        $dataInvProds = [];
        foreach ($invProds as $key => $invProd) {
            if ($invProd->AvailableQty == 0 && $invProd->PendingOutQty == 0) {
                unset($invProds[$key]); // Ẩn tồn kho nếu không có số lượng khả dụng hay chờ xuất
                continue;
            }
            $invProd->TotalQty = $invProd->AvailableQty + $invProd->PendingOutQty;
            $dataInvProds[] = $invProd;
        }
        $result->InvProduct = $dataInvProds;
        $result->AllowFullUpdate = !$this->isEditBlocked($id);
        $result->HistoryTrade = [];

        if (isset($result->Unit) && !empty($result->Unit)) {
            $result->Dimension = ((int)$result->Unit[0]['Length'] ?? 0) . '*' . ((int)$result->Unit[0]['Width'] ?? 0) . '*' . ((int)$result->Unit[0]['Height'] ?? 0);
        }

        $result->AvatarURL = DEFAULT_IMG_URL;
        $result->AvatarDefaultURL = DEFAULT_IMG_URL;
        return $result;
    }

    public function getProductLog($data)
    {
        $productId = $data['ProductId'] ?? 0;
        $lmstart = $data['lmstart'] ?? 0;
        $limit = $data['limit'] ?? 20;

        $query = ProductLog::select(['UpdatedDate as CreatedDate', 'UpdatedBy as CreatedBy', 'Type'])->where('ProductId', $productId)->orderByDesc('ProductLogId');
        $query->with(['createdByStaff' => function ($query) {
            $query->select('StaffId', 'FullName', 'StaffCode');
        }]);
        $results = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);
        foreach ($results as $result) {
            switch ($result['Type']) {
                case 'Insert':
                    $result['Type'] = 'Tạo mới';
                    break;

                case 'Update':
                    $result['Type'] = 'Chỉnh sửa';
                    break;
                default:
                    break;
            }
        }
        return $results;
    }

    public function getProductImageByProduct($id)
    {
        $query = $this->_model->newQuery();
        return $query->select('ProductImageId', 'ProductId', 'URLCDN', 'IsDeleted')
            ->from('ProductImage')
            ->where('ProductId', $id)
            ->where('IsDeleted', 0)->get();
    }

    public function importFile($file)
    {
        $results = [
            "code" => false,
            "data" => [],
            "msg" => ''
        ]; 
        $staffId = Auth::user()['StaffId'] ?? 0;
        DB::beginTransaction();
        try {
            Log::info('-------Begin Import Product-----');
            try {
                $import = new ProductManagerImport($file);
                Excel::import($import, $file);
            } catch (\App\Exceptions\ImportExcelException $iee) {
                DB::rollBack();
                Log::error('Product Manager Import No Data: ', [$iee->getMessage()]);
                $results["msg"] = $iee->getMessage();
                return $results;
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('File Import fail: ', [$e->getMessage()]);
                $results["msg"] = "File import sai định dạng. Vui lòng kiểm tra lại";
                return $results;
            }
            $invalidRecords = $import->getInvalidRecords();
            if (!$invalidRecords) {
                $invalidRecords = $import->getValidRecords();
            }
            $exportValue = [];
            $importResults = $import->getResults();
            $savedFile = Helper::uploadFileToServer($file, 'SourceProducts');
            if ($savedFile && !empty($savedFile)) {
                $savedFile = API_MEDIA . '/' . $savedFile;
            }
            $exportFile = null;
            $reportExport = null;
            if ($importResults['TotalError'] > 0) {
                $fileExportName = 'Import_Product_' . '_report_' . date('Y_m') . '_' . time() . '_' . (Auth::user()['StaffId'] ?? 0) . '.xlsx';
                $filePathExport = storage_path('app/excel') . '/' . $fileExportName;
                foreach ($invalidRecords as $value) {
                    $errorMsg = '';
                    try {
                        foreach ($value['error_list_non_encode'] as $v) {
                            $errorMsg .= $v . '. ';
                        }
                        $exportValue[] = [
                            "stt" => $value['stt'],
                            'ma_vach' => $value['ma_vach'],
                            'ten_san_pham' => $value['ten_san_pham'],
                            'ten_danh_muc' => $value['ten_danh_muc'],
                            'ma_don_vi' => $value['ma_don_vi'],
                            'ten_don_vi' => $value['ten_don_vi'],
                            'so_luong_quy_doi' => $value['so_luong_quy_doi'],
                            'don_vi_nho_nhat' => isset($value['don_vi_nho_nhat']) && $value['don_vi_nho_nhat'] == 1 ? 'Có': '',
                            'mo_ta' => $value['mo_ta'],
                            'error_list_non_encode' => $errorMsg,
                        ];
                        if (isset($value['don_vi_nho_nhat']) && !empty($value['don_vi_nho_nhat']) && !is_numeric($value['don_vi_nho_nhat'])) {
                            $slugBaseUnit = Str::slug($value['don_vi_nho_nhat']);
                            $value['don_vi_nho_nhat'] = $slugBaseUnit == 'co' ? 1 : 0;
                        }
                    } catch (\Exception $e) {
                        Log::error('Report Export fail: ', [$e->getMessage()]);
                        continue;
                    }
                }
                $reportExport = Excel::store(new ReportExport($exportValue), 'excel/' . $fileExportName);

                if ($reportExport && file_exists($filePathExport)) {
                    $filePathExport = new UploadedFile($filePathExport, $fileExportName, mime_content_type($filePathExport));
                    $responeMediaExport = Helper::uploadFileToServer($filePathExport, 'Exports');
                    if ($responeMediaExport && !empty($responeMediaExport)) {
                        $exportFile = API_MEDIA . '/' . $responeMediaExport;
                    }
                }
            }

            $prodHistoryRepo = new ProductHistoryRepository();
            $importHistory = [
                'BranchId' => 1,
                'TotalBill' => $importResults['TotalImport'] ?? 0,
                'TotalSuccess' => $importResults['TotalError'] < 1 ? $importResults['TotalImport'] : 0,
                'TotalFail' => $importResults['TotalError'] > 0 ? $importResults['TotalImport'] : 0,
                'LinkSource' => $savedFile ?? '',
                'LinkError' => $exportFile ?? null,
                'CreatedBy' => $staffId ?? 0,
                'CreatedDate' => Carbon::now()->toDateTimeString(),
            ];
            $prodHistoryRepo->trackingImport($importHistory);
            if (!empty($importResults) && is_array($importResults) && $importResults['TotalError'] > 0) {
                DB::commit();
                $errorData = $import->getData($savedFile);
                $results["data"] = $errorData;
                return $results;
            }
            $dataBeforeHandle = $import->getDataBeforeHandle();
            $dataHandle = $import->mergeData($dataBeforeHandle);
            $data = [];
            if (count($dataHandle) > 0) {
                $productDatas = [];

                foreach ($dataHandle as $item) {
                    $data = [
                        'Barcode' => $item['Barcode'] ?? '',
                        'ProductName' => trim($item['ProductName'] ?? ''),
                        'Description' => $item['Description'] ?? '',
                        'NameUnsign' => Str::slug($item['ProductName'], '-'),
                        'IsActive' => 1,
                        'CreatedBy' => $staffId ?? 0,
                        'CreatedDate' => Carbon::now()->toDateTimeString(),
                        'UpdatedBy' => $staffId ?? 0,
                        'UpdatedDate' => Carbon::now()->toDateTimeString(),
                        'ProductConditionId' => 1,
                    ];
                    $insertDataId = Product::insertGetId($data);
                    if ($insertDataId) {
                        $productDatas[] =[
                            "ProductId" => $insertDataId
                        ];
                        foreach ($item['Units'] as $dh) {
                            if (isset($dh['IsBaseUnit']) && !empty($dh['IsBaseUnit']) && !is_numeric($dh['IsBaseUnit'])) {
                                $slugBaseUnit = Str::slug($dh['IsBaseUnit']);
                                $dh['IsBaseUnit'] = $slugBaseUnit == 'co' ? 1 : 0;
                            }
                            $checkUnitId = DB::table('sale.ProductUnit')
                                ->where('ProductId', $insertDataId)
                                ->where('UnitId', $dh['UnitId'])
                                ->first();
                            if (!$checkUnitId) {
                                $productUnit = [
                                    'ProductId' => $insertDataId,
                                    'QtyPerCase' => $dh['QtyPerCase'],
                                    'UnitId' => $dh['UnitId'],
                                    'IsBaseUnit' => $dh['IsBaseUnit'],
                                    'CreatedBy' => $staffId ?? 0,
                                    'CreatedDate' => Carbon::now()->toDateTimeString(),
                                ];
                                ProductUnit::insert($productUnit);
                            }
                        }

                        foreach ($item['Category'] as $category) {
                            $checkCategory = Category::where('NameUnsign', Str::slug($category))->where('IsActive', 1)->first();
                            if ($checkCategory && !empty($checkCategory)) {
                                ProductCategory::insert([
                                    'ProductId' => $insertDataId,
                                    'CategoryId' => $checkCategory->CategoryId,
                                    'CreatedBy' => $staffId,
                                    'CreatedDate' => Carbon::now()->toDateTimeString(),
                                    'IsDeleted' => 0,
                                ]);
                            }
                        }
                    }

                }
                DB::commit();
                foreach ($productDatas as $productData) {
                    $sku = $this->generateSKU();
                    $prod = Product::where('ProductId', $productData['ProductId'])->first();
                    if (isset($prod) && !empty($prod)) {
                        $prod->update([
                            'SKU' => $sku
                        ]);
                    }
                }
                Log::info('-------Insert Product Successfully-----');
                $results["code"] = true;
                return $results;
            }
            DB::rollBack();
            $results["code"] = false;
            return $results;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product Manager Import fail: ', [$e->getMessage()]);
            return $results;
        }
        DB::rollBack();
        return $results;
    }

    public function removeProduct($id, $currentUpdateDate)
    {
        $query = $this->_model->newQuery();
        $query->where('ProductId', $id)->where('IsActive', 0)->where('UpdatedDate', '==', $currentUpdateDate);

        $product = $query->first();
        if ($product) {
            return false;
        }

        $staffId = Auth::user()['StaffId'] ?? 0;
        try {
            $prodTracking = Product::where('ProductId', $id)->first()->toArray();
            $prodCategTracking = ProductCategory::where('ProductId', $id)->get()->toArray();
            $prodUnitTracking = ProductUnit::where('ProductId', $id)->get()->toArray();
            $this->latestTrackingId = ($prodUnitTracking[0]['LatestTrackingId'] ?? 0) + 1;

            DB::beginTransaction();
            Product::where('ProductId', $id)
                ->where('IsActive', 1)
                ->update([
                    "IsActive" => 0,
                    "UpdatedDate" => Carbon::now()->toDateTimeString(),
                    "UpdatedBy" => $staffId
                ]);

            ProductImage::where('ProductId', $id)
                ->where('IsDeleted', 0)
                ->update([
                    "IsDeleted" => 1,
                    "UpdatedDate" => Carbon::now()->toDateTimeString(),
                    "UpdatedBy" => $staffId
                ]);

            $this->productTracking($id, [
                'Product' => $prodTracking, 
                'ProductCategory' => $prodCategTracking, 
                'ProductUnit' => $prodUnitTracking
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("removeProduct error", [$e->getMessage()]);
            return false;
        }
        return true;
    }

    public function getListImport($conditions)
    {
        $today = Carbon::now();
        $fromDate = $today->clone()->subDays(5);
        $toDate = $today->clone()->addDay(5);
        $query = $this->_model
            ->select('TotalBill', 'TotalSuccess', 'TotalFail', 'LinkSource', 'LinkError', 'CreatedBy', 'CreatedDate')
            ->from('sale.ProductHistory')
            ->where('CreatedDate', '>=', $fromDate)
            ->where('CreatedDate', '<=', $toDate)
            ->orderBy('CreatedDate', 'DESC');

        $query->with(['createdBy' => function ($query) {
            $query->select('StaffId', 'FullName');
        }]);
        $results = $query->get();

        return $results;
    }

    public function getOptionLists()
    {
        $rs = [];
        $query = $this->_model->newQuery()
            ->select('UnitId', 'Code', 'Name', 'NameUnsign', 'IsBaseUnit')
            ->from('Unit')
            ->orderByDESC('IsBaseUnit');
        $codes = $query->get()->pluck('Code')->toArray();
        $names = $query->get()->pluck('Name')->toArray();
        $rs['Unit'] = $query->get();

        foreach ($rs['Unit'] as $key => $v) {
            if ($v->Code == $codes[$key]) {
                $v->Name = $codes[$key] . ' - ' . $names[$key];
            }
        }

        $categRepo = new CategoryRepository();
        $categ = $categRepo->getCategoryParentChild();

        $rs['Category'] = $categ;

        return $rs;
    }

    public function getProductByProductCode($productCodes = '', $multiple = false)
    {
        if (!is_array($productCodes)) {
            $productCodes = [$productCodes];
        }
        if (count($productCodes) < 1) {
            return [];
        }
        $query = $this->_model->where('IsActive', 1)->whereIn('SKU', $productCodes);
        if (!$multiple) {
            return $query->first();
        }
        return $query->get();
    }

    public function getCategByNameUnsign($nameUnsign = '')
    {
        $cgNames = explode(',', $nameUnsign);
        $categories = [];
        foreach ($cgNames as $cgName) {
            $nameUnsign = Str::slug($cgName);
            $query = Category::where('IsActive', 1)->where('NameUnsign', $nameUnsign);
            $categories[] = $query->first();
        }
        return $categories;
    }

    public function getOrInsertBrandByBrandCode($brandName)
    {
        $staffId = Auth::user()['StaffId'] ?? 0;
        $brandName = trim($brandName);
        $brand = ProductBrand::where('Name', $brandName)->first();

        if (!isset($brand) || empty($brand)) {
            $this->logDebug("Tạo nhãn hàng: ", [$brandName]);
            DB::beginTransaction();
            $max = ProductBrand::select(DB::raw('MAX(CAST(SUBSTRING(Code, 1) AS UNSIGNED)) as Code'))->first();
            $maxOrCode = $max->Code;

            $stt = sprintf('%03d', ($maxOrCode ?? 0) + 1);
            $brandId = ProductBrand::insertGetId([
                'Code' => $stt,
                'Name' => $brandName,
                'Status' => 1,
                'CreatedBy' => $staffId,
                'CreatedDate' => Carbon::now()->toDateTimeString(),
            ]);
            DB::commit();
            return $brandId;
        }

        return $brand['ProductBrandId'];
    }

    public function getOrInsertUnitByCode($unitName, $unitCode)
    {
        $staffId = Auth::user()['StaffId'] ?? 0;
        $unit = Unit::where('Code', $unitCode)->first();

        if (
            (!isset($unit) || empty($unit))
            && !isset($this->unit[Str::slug($unitName . '-' . $unitCode)])
        ) {
            $unitId = Unit::insertGetId([
                'Code' => $unitCode,
                'Name' => $unitName,
                'NameUnsign' => Str::slug($unitName),
                'CreatedBy' => $staffId,
                'CreatedDate' => Carbon::now()->toDateTimeString(),
            ]);
            $this->unit[Str::slug($unitName . '-' . $unitCode)] = $unitId;
            return $unitId;
        }

        if (!isset($this->unit[Str::slug($unitName . '-' . $unitCode)])) {
            $this->unit[Str::slug($unitName . '-' . $unitCode)] = $unit['UnitId'];
        }
        return $this->unit[Str::slug($unitName . '-' . $unitCode)];
    }

    public function validateProducts($data)
    {
        $brandId = 0;
        $unitId = 0;
        $isNotExistProductName = true;
        $isNotExistCateg = true;

        $productName = $unique = trim($data['ten_san_pham']) ?? (trim($data['ProductName']) ?? '');
        $unitCode = $data['ma_don_vi'] ?? ($data['Code'] ?? '');
        $unitName = $data['ten_don_vi'] ?? ($data['Name'] ?? '');
        $categoryName = $data['ten_danh_muc'] ?? ($data['CategoryName'] ?? '');
        $qtyPerCase = $data['so_luong_quy_doi'] ?? 0;
        $isBaseUnit = $data['don_vi_nho_nhat'] ?? 0;

        $isValid = true;
        $_reason = (object)[];

        if (!trim($unitCode)) {
            $_reason->Code = 'Vui lòng nhập mã đơn vị';
            if ($isValid) {
                $isValid = false;
            }
        }

        if (!trim($unitName)) {
            $_reason->Name = 'Vui lòng nhập tên đơn vị';
            if ($isValid) {
                $isValid = false;
            }
        }

        if (!trim($productName)) {
            $_reason->ProductName = 'Vui lòng nhập tên sản phẩm';
            $isNotExistProductName = false;
            if ($isValid) {
                $isValid = false;
            }
        }

        if (!trim($qtyPerCase)) {
            $_reason->QtyPerCase = 'Vui lòng nhập số lượng quy đổi';
            if ($isValid) {
                $isValid = false;
            }
            $this->logDebug("Số lượng quy đổi rỗng: ", [$qtyPerCase]);
        } else if (!is_numeric($qtyPerCase)) {
            $_reason->QtyPerCase = 'Số lượng quy đổi phải là số';
            if ($isValid) {
                $isValid = false;
            }
            $this->logDebug("Số lượng quy đổi: ", [$qtyPerCase]);
        } else if ($qtyPerCase < 1) {
            $_reason->QtyPerCase = 'Số lượng quy đổi phải phải lớn hơn 0';
            if ($isValid) {
                $isValid = false;
            }
            $this->logDebug("Số lượng quy đổi: ", [$qtyPerCase]);
        }

        if (!is_numeric($isBaseUnit)) {
            $slugSeri = Str::slug($data['don_vi_nho_nhat']);
            $data['don_vi_nho_nhat'] = $slugSeri == 'co' ? 'Có' : 'Không';
        }

        $this->isBaseUnit[$unique] = ($isBaseUnit === 'có' || $isBaseUnit === 'Có') ? 1 : 0;
        if (isset($this->isBaseUnit[$unique]) && !empty($this->isBaseUnit[$unique]) && $this->isBaseUnit[$unique] == 1) {
            $this->countBaseUnit += 1;
        }
        if ($this->countBaseUnit > 1) {
            $_reason->IsBaseUnit = 'Đã tồn tại đơn vị nhỏ nhất';
            if ($isValid) {
                $isValid = false;
            }
            $this->logDebug("Đã tồn tại đơn vị nhỏ nhất: ", [$this->isBaseUnit[$unique]]);
        }
        if (($isBaseUnit === 'có' || $isBaseUnit === 'Có' || $isBaseUnit == 1) && $qtyPerCase != 1) {
            $_reason->IsBaseUnit = 'Số lượng quy đổi của đơn vị nhỏ nhất phải là 1';
            if ($isValid) {
                $isValid = false;
            }
            $this->logDebug("Số lượng quy đổi của đơn vị nhỏ nhất phải là 1: ", [$data['don_vi_nho_nhat']]);
        }

        // check duplicate unit in file
        $formatUnitAndCode = Str::slug($unique . '-' . $unitName . '-' . $unitCode);
        if (((isset($unitCode) && !empty($unitName)) || (isset($unitName) && !empty($unitName))) && isset($this->productCodes[$formatUnitAndCode]) && !empty($this->productCodes[$formatUnitAndCode])) {
            $_reason->Name = 'Trùng tên đơn vị và mã đơn vị';
            $_reason->Code = 'Trùng tên đơn vị và mã đơn vị';
            if ($isValid) {
                $isValid = false;
            }
            $this->logDebug("Trùng tên đơn vị và mã đơn vị: ", [$formatUnitAndCode]);
        }
        $this->productCodes[$formatUnitAndCode] = $formatUnitAndCode;

        
        $product = Product::where('ProductName', trim($productName))
            ->where('IsActive', 1)
            ->first();
        if (isset($product) && !empty($product)) {
            $_reason->ProductName = 'Tên sản phẩm đã tồn tại';
            $isNotExistProductName = false;
            if ($isValid) {
                $isValid = false;
            }
            $this->logDebug("Tên sản phẩm đã tồn tại ở database: ", [$productName]);
        }
        $categ = $this->getCategByNameUnsign($categoryName);
        if (!isset($categ) || empty($categ)) {
            $_reason->CategoryName = 'Danh mục không tồn tại';
            $isNotExistCateg = false;
            if ($isValid) {
                $isValid = false;
            }
            $this->logDebug("Danh mục không tồn tại: ", [$categoryName]);
        }

        if ($unitCode) {
            $unitId = $this->getOrInsertUnitByCode($unitName, $unitCode);
        }

        $data['BrandId'] = $brandId;
        $data['UnitId'] = $unitId;
        $data['IsBaseUnit'] = $this->isBaseUnit[$unique];
        if ($isValid) {
            return $data;
        }
        $data['error_list'] = json_encode($_reason);
        $data['error_list_non_encode'] = $_reason;
        $data['IsNotExistProductName'] = $isNotExistProductName;
        $data['IsNotExistCategory'] = $isNotExistCateg;

        return $data;
    }

    public function generateSKU()
    {
        $code = "SP" . date('ym');
        DB::select("CALL pos.usp_AutoGenerate_Generate('InboundOrder', :Code, @pSeekNumber)", ['Code' => $code]);
        $seekNumber = DB::select("SELECT @pSeekNumber")[0]->{'@pSeekNumber'};

        $stt = sprintf('%04d', $seekNumber);
        return  $code . $stt;
    }

    public function getPricingToDay($data)
    {
        $productId = $data->ProductId;
        $unitId = $data->UnitId;
        $now = Carbon::now()->toDateTimeString();
        $staffId = Auth::user()['StaffId'] ?? 0;

        $productPricing = ProductPricing::where('ProductId', $productId)
            ->where('UnitId', $unitId)
            ->where('StartDate', '<=', $now)
            ->where('EndDate', '>=', $now)
            ->orderBy('StartDate', 'DESC')
            ->first();

        if (!$productPricing) {
            return 1;
        }
        
        return $productPricing->Price ?? 1;
    }

    public function logDebug($title, $message)
    {
        if ($this->debug) {
            if (!is_array($message)) {
                $message = [$message];
            }

            Log::info($title, $message);
        }
    }

    public function getListImportExportHistories($productId, $conditions) 
    {
        $fromDate = $conditions['FromDate'] ?? null;
        $toDate = $conditions['ToDate'] ?? null;
        $branchIds = $conditions['BranchIds'] ?? [];
        $limit = $conditions['limit'] ?? 20;
        $lmstart = $conditions['lmstart'] ?? 0;

        $result = [];

        // Get import history
        $queryImportHistory = IR::where('IRStatus', 3); //Completed status

        $queryImportHistory->whereHas('productDetailIR', function ($query) use ($productId) {
            $query->where('IRDetail.ProductId', $productId);
        });
        if ($fromDate && !empty($fromDate)) {
            $queryImportHistory->where('FinishedDate', '>=', $fromDate . ' 00:00:00');
        }
        if ($toDate && !empty($toDate)) {
            $queryImportHistory->where('FinishedDate', '<=', $toDate . ' 23:59:59');
        }
        if ($branchIds && !empty($branchIds) && count($branchIds) > 0) {
            $queryImportHistory->whereIn('BranchId', $branchIds);
        }
        
        //Relationships IR
        $queryImportHistory->with(['productDetailIR' => function ($query) use ($productId) {
            $query->select('IRDetail.*');
            $query->where('IRDetail.ProductId', $productId);
            $query->with(['unit']);
        }]);

        $queryImportHistory->with(['createdByStaff' => function ($query) {
            $query->select('StaffId', 'FullName', 'StaffCode');
        }]);

        $queryImportHistory->with(['branch' => function ($query) {
            $query->select('BranchId', 'Name', 'BranchCode');
        }]);

        $queryImportHistory->orderByDesc('FinishedDate');

        $importHistories = $queryImportHistory->get();
        
        if ($importHistories && !empty($importHistories)) {
            $result = array_merge($result, $importHistories->toArray());
        }

        // Get export history
        $queryExportHistory = ProductOR::where('ORStatus', 99); //Completed status

        $queryExportHistory->whereHas('orderDetail', function ($query) use ($productId) {
            $query->where('ORDetail.ProductId', $productId);
        });
        if ($fromDate && !empty($fromDate)) {
            $queryExportHistory->where('ConfirmedDate', '>=', $fromDate . ' 00:00:00');
        }
        if ($toDate && !empty($toDate)) {
            $queryExportHistory->where('ConfirmedDate', '<=', $toDate . ' 23:59:59');
        }
        if ($branchIds && !empty($branchIds) && count($branchIds) > 0) {
            $queryExportHistory->whereIn('BranchId', $branchIds);
        }

        //Relationships OR
        $queryExportHistory->with(['orderDetail' => function ($query) use ($productId) {
            $query->select('ORDetail.*');
            $query->where('ORDetail.ProductId', $productId);
            $query->with(['unit']);
        }]);

        $queryExportHistory->with(['createdByStaff' => function ($query) {
            $query->select('StaffId', 'FullName', 'StaffCode');
        }]);

        $queryExportHistory->with(['branch' => function ($query) {
            $query->select('BranchId', 'Name', 'BranchCode');
        }]);

        $queryExportHistory->orderByDesc('ConfirmedDate');

        $exportHistories = $queryExportHistory->get();

        if ($exportHistories && !empty($exportHistories)) {
            $result = array_merge($result, $exportHistories->toArray());
        }

        $result = array_values($result);

        //Sort
        usort($result, function ($a, $b) {
            $aSortDate= date('Y-m-d H:i:s');
            $bSortDate= date('Y-m-d H:i:s');
            if (isset($a['IRId']) && isset($a['FinishedDate'])) {
                $aSortDate = $a['FinishedDate'] ?? date('Y-m-d H:i:s');
            } elseif (isset($a['ORId']) && isset($a['ConfirmedDate'])) {
                $aSortDate = $a['ConfirmedDate'] ?? date('Y-m-d H:i:s');
            }
            if (isset($b['IRId']) && isset($b['FinishedDate'])) {
                $bSortDate = $b['FinishedDate'] ?? date('Y-m-d H:i:s');
            } elseif (isset($b['ORId']) && isset($b['ConfirmedDate'])) {
                $bSortDate = $b['ConfirmedDate'] ?? date('Y-m-d H:i:s');
            }

            return strtotime($bSortDate) <=> strtotime($aSortDate);
        });

        return new LengthAwarePaginator($result, count($result), $limit, floor((int) $lmstart / $limit) + 1);

    }

    public function getImportHistoryDetail($productId, $iRId) 
    {
        // Get import history
        $queryImportHistory = IR::where('IRId', $iRId); //Check IRId

        $queryImportHistory->whereHas('productDetailIR', function ($query) use ($productId) {
            $query->where('IRDetail.ProductId', $productId);
        });
        
        //Relationships IR
        $queryImportHistory->with(['productDetailIR' => function ($query) use ($productId) {
            $query->select('IRDetail.*');
            $query->where('IRDetail.ProductId', $productId);
            $query->with(['product' => function ($subQuery) {
                $subQuery->select([
                    'ProductId',
                    'Barcode',
                    'SKU',
                    'ProductName',
                    'IsTrackingSerial',
                    'IsExpiryDate',
                ]);
            }]);
            $query->with(['unit']);
        }]);

        $queryImportHistory->with(['createdByStaff' => function ($query) {
            $query->select('StaffId', 'FullName', 'StaffCode');
        }]);

        $queryImportHistory->with(['branch' => function ($query) {
            $query->select('BranchId', 'Name', 'BranchCode');
        }]);

        $import = $queryImportHistory->first();

        return $import;
    }

    public function getExportHistoryDetail($productId, $oRId) 
    {
        // Get export history
        $queryExportHistory = ProductOR::where('ORId', $oRId); //Check ORId

        $queryExportHistory->whereHas('orderDetail', function ($query) use ($productId) {
            $query->where('ORDetail.ProductId', $productId);
        });

        //Relationships OR
        $queryExportHistory->with(['orderDetail' => function ($query) use ($productId) {
            $query->select('ORDetail.*');
            $query->where('ORDetail.ProductId', $productId);
            $query->with(['product' => function ($subQuery) {
                $subQuery->select([
                    'ProductId',
                    'Barcode',
                    'SKU',
                    'ProductName',
                    'IsTrackingSerial',
                    'IsExpiryDate',
                ]);
            }]);
            $query->with(['unit']);
        }]);

        $queryExportHistory->with(['createdByStaff' => function ($query) {
            $query->select('StaffId', 'FullName', 'StaffCode');
        }]);

        $queryExportHistory->with(['branch' => function ($query) {
            $query->select('BranchId', 'Name', 'BranchCode');
        }]);

        $export = $queryExportHistory->first();

        return $export;
    }

    public function getListImportExportHistoriesV2($conditions)
    {
        $typeData = $conditions['Type'] ?? 0; // 1: import, 2: export, other: all
        $limit = $conditions['limit'] ?? 20;
        $lmstart = $conditions['lmstart'] ?? 0;

        $result = [];

        $iRData = $this->getListIRDetail($conditions);
        if ($typeData == 1) {
            return $iRData;
        }
        $oRData = $this->getListORDetail($conditions);
        if ($typeData == 2) {
            return $oRData;
        }
        if ($iRData && !empty($iRData)) {
            $result = array_merge($result, $iRData->toArray());
        }
        if ($oRData && !empty($oRData)) {
            $result = array_merge($result, $oRData->toArray());
        }

        $result = array_values($result);
        $items = array_slice($result, $lmstart, $limit);

        //Sort
        usort($items, function ($a, $b) {
            $aSortDate= date('Y-m-d H:i:s');
            $bSortDate= date('Y-m-d H:i:s');
            if (isset($a['IRId']) && isset($a['FinishedDate'])) {
                $aSortDate = $a['FinishedDate'] ?? date('Y-m-d H:i:s');
            } elseif (isset($a['ORId']) && isset($a['ConfirmedDate'])) {
                $aSortDate = $a['ConfirmedDate'] ?? date('Y-m-d H:i:s');
            }
            if (isset($b['IRId']) && isset($b['FinishedDate'])) {
                $bSortDate = $b['FinishedDate'] ?? date('Y-m-d H:i:s');
            } elseif (isset($b['ORId']) && isset($b['ConfirmedDate'])) {
                $bSortDate = $b['ConfirmedDate'] ?? date('Y-m-d H:i:s');
            }

            return strtotime($bSortDate) <=> strtotime($aSortDate);
        });

        return new LengthAwarePaginator($items, count($result), $limit, floor((int) $lmstart / $limit) + 1);
    }

    public function getListIRDetail($conditions)
    {
        $productId = $conditions['ProductId'] ?? null;
        $fromDate = $conditions['FromDate'] ?? null;
        $toDate = $conditions['ToDate'] ?? null;
        $branchIds = $conditions['BranchIds'] ?? [];
        $typeData = $conditions['Type'] ?? 0;
        $limit = $conditions['limit'] ?? 20;
        $lmstart = $conditions['lmstart'] ?? 0;

        $query = IRDetail::select('IR.IRId', 'IR.CreatedDate', 'IR.FinishedDate', 'IR.IRCode', 'IR.IRType', 'IRDetail.ActualQty', 'IRDetail.UnitId', 'b.BranchCode', 'b.Name as BranchName')
            ->join('sale.IR', 'IRDetail.IRId', 'IR.IRId')
            ->join('in.Branch as b', 'b.BranchId', 'IR.BranchId');

        $query->where('ProductId', $productId);

        if ($fromDate && !empty($fromDate)) {
            $query->where('FinishedDate', '>=', $fromDate . ' 00:00:00');
        }
        if ($toDate && !empty($toDate)) {
            $query->where('FinishedDate', '<=', $toDate . ' 23:59:59');
        }
        if ($branchIds && !empty($branchIds) && count($branchIds) > 0) {
            $query->whereIn('b.BranchId', $branchIds);
        }

        // Relationships
        $query->with(['unit']);

        if ($typeData == 1) {
            return $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);
        }

        return $query->get();
    }

    public function getListORDetail($conditions)
    {
        $productId = $conditions['ProductId'] ?? null;
        $fromDate = $conditions['FromDate'] ?? null;
        $toDate = $conditions['ToDate'] ?? null;
        $branchIds = $conditions['BranchIds'] ?? [];
        $typeData = $conditions['Type'] ?? 0;
        $limit = $conditions['limit'] ?? 20;
        $lmstart = $conditions['lmstart'] ?? 0;

        $query = ORDetail::select('OR.ORId', 'OR.CreatedDate', 'OR.ConfirmedDate', 'OR.ORCode', 'OR.ORType', 'ORDetail.OrderQty', 'ORDetail.UnitId', 'b.BranchCode', 'b.Name as BranchName')
            ->join('sale.OR', 'ORDetail.ORId', 'OR.ORId')
            ->join('in.Branch as b', 'b.BranchId', 'OR.BranchId');

        $query->where('ORDetail.ProductId', $productId);

        if ($fromDate && !empty($fromDate)) {
            $query->where('ConfirmedDate', '>=', $fromDate . ' 00:00:00');
        }
        if ($toDate && !empty($toDate)) {
            $query->where('ConfirmedDate', '<=', $toDate . ' 23:59:59');
        }
        if ($branchIds && !empty($branchIds) && count($branchIds) > 0) {
            $query->whereIn('b.BranchId', $branchIds);
        }

        // Relationships
        $query->with(['unit']);

        if ($typeData == 2) {
            return $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);
        }

        return $query->get();
    }

    public function getQuantityProductsAboutToExpire($conditions)
    {
        $branchId = $conditions['BranchId'] ?? 0;
        $groups = [
            'EXPIRED' => 0,
            'INTERVAL3MONTH' => 0,
            'INTERVAL6MONTH' => 0,
            'INTERVAL9MONTH' => 0,
        ];

        try {
            $query = "CALL sale.usp_GetProductExpiredWarning($branchId)";
            $data = DB::select($query);

            if (!isset($data) || empty($data)) {
                return $groups;
            }

            foreach ($data as $value) {
                if ($value->ExpiredGroup == 'lessThanThreeMonth') {
                    $groups['INTERVAL3MONTH'] += $value->Quantity;
                } elseif ($value->ExpiredGroup == 'lessThanSixMonth') {
                    $groups['INTERVAL6MONTH'] += $value->Quantity;
                } elseif ($value->ExpiredGroup == 'lessThanNineMonth') {
                    $groups['INTERVAL9MONTH'] += $value->Quantity;
                } elseif ($value->ExpiredGroup == 'expired') {
                    $groups['EXPIRED'] += $value->Quantity;
                }
            }
        } catch (\Exception $e) {
            Log::error("getQuantityProductsAboutToExpire error", [$e->getMessage()]);
            return $groups;
        }

        return $groups;
    }

    public function getListProductExpired($conditions)
    {
        $branchId = $conditions['BranchId'] ?? 0;
        $configOtherBranch = config('constants.branch.otherBranch');
        $type = $conditions['Type'] ?? 1; // 1: < 3 tháng, 2: < 6 tháng, 3: < 9 tháng
        $lmstart = $conditions['lmstart'] ?? 0;
        $limit = $conditions['limit'] ?? 20;
        switch ($type) {
            case self::TYPE_WARNING_EXPIRED['lessThanThreeMonth']:
                $fromTime = Carbon::now()->startOfDay()->toDateTimeString();
                $toTime = Carbon::now()->addMonths(3)->endOfDay()->toDateTimeString();
                break;
            case self::TYPE_WARNING_EXPIRED['lessThanSixMonth']:
                $fromTime = Carbon::now()->addMonths(3)->startOfDay()->toDateTimeString();
                $toTime = Carbon::now()->addMonths(6)->endOfDay()->toDateTimeString();
                break;
            case self::TYPE_WARNING_EXPIRED['lessThanNineMonth']:
                $fromTime = Carbon::now()->addMonths(6)->startOfDay()->toDateTimeString();
                $toTime = Carbon::now()->addMonths(9)->endOfDay()->toDateTimeString();
                break;
            case self::TYPE_WARNING_EXPIRED['expired']:
                $fromTime = Carbon::now()->startOfDay()->toDateTimeString();
                $toTime = Carbon::now()->startOfDay()->toDateTimeString();
                break;
            default:
                $fromTime = Carbon::now()->startOfDay()->toDateTimeString();
                $toTime = Carbon::now()->startOfDay()->toDateTimeString();
                break;
        }

        $query = InvProductExpired::select(DB::raw('SUM(InvProductExpired.AvailableQty) AS AvailableQty, InvProductExpired.ExpiredDate, p.ProductName, p.SKU, u.Name as UnitName, u.Code as UnitCode'))
            ->join('sale.Product as p', 'InvProductExpired.ProductId', 'p.ProductId')
            ->join('sale.Unit as u', 'InvProductExpired.UnitId', 'u.UnitId');

        if (isset($branchId) && !empty($branchId) && $branchId > 0 && $branchId != $configOtherBranch) {
            $query->where('InvProductExpired.BranchId', $branchId);
        }
        if (isset($branchId) && !empty($branchId) && $branchId == $configOtherBranch) {
            $query->whereNull('InvProductExpired.BranchId');
        }
        $query->where('InvProductExpired.AvailableQty', '>', 0);

        if ($type == self::TYPE_WARNING_EXPIRED['expired']) {
            $query->where('InvProductExpired.ExpiredDate', '<', $toTime);
        } else if ($type == self::TYPE_WARNING_EXPIRED['lessThanThreeMonth']) {
            $query->where('InvProductExpired.ExpiredDate', '>=', $fromTime);
            $query->where('InvProductExpired.ExpiredDate', '<=', $toTime);
        } else {
            $query->where('InvProductExpired.ExpiredDate', '>', $fromTime);
            $query->where('InvProductExpired.ExpiredDate', '<=', $toTime);
        }
        $query->groupBy('InvProductExpired.ExpiredDate', 'p.ProductName', 'p.SKU', 'u.Name', 'u.Code');
        $query->orderBy('InvProductExpired.ExpiredDate');

        $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);

        // thêm cột đếm ngày còn lại tính từ ExpiredDate, có thể trả về số âm
        // Now nhưng lấy ngày và thời gian là 00:00:00
        foreach ($result as $value) {
            $now = Carbon::now()->startOfDay();
            $value->RemainingDays = $now->diffInDays(Carbon::parse($value->ExpiredDate)->startOfDay(), false);
        }

        return $result;
    }
}
