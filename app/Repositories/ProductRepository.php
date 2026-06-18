<?php

namespace App\Repositories;

use App\Product;
use App\ProductSupplierMapping;
use App\ProductSkuAttribute;
use App\ProductBrand;
use App\ProductAttributeType;
use App\ProductAttributeValue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Repositories\Abstracts\EloquentRepository;

class ProductRepository extends EloquentRepository
{
    protected function getModel()
    {
        return Product::class;
    }

    public function search(array $conditions = [])
    {
        $keyword = (string) ($conditions['Keyword'] ?? '');
        $limit   = min((int) ($conditions['limit'] ?? 20), 100);
        $lmstart = max((int) ($conditions['lmstart'] ?? 0), 0);

        $query = Product::query()
            ->leftJoin('ProductCategory as pc', 'pc.ProductCategoryId', '=', 'Product.ProductCategoryId')
            ->leftJoin('ProductBrand as pb', 'pb.ProductBrandId', '=', 'Product.ProductBrandId')
            ->leftJoin('Unit as u', 'u.UnitId', '=', 'Product.UnitId')
            ->leftJoin('Supplier as s', 's.SupplierId', '=', 'Product.SupplierId')
            ->leftJoin(DB::raw('(SELECT ProductId, SUM(Quantity) as StockQuantity FROM Inventory GROUP BY ProductId) as inv'), 'inv.ProductId', '=', 'Product.ProductId')
            ->select(
                'Product.*',
                'pb.Name as ProductBrandName',
                'pc.Name as ProductCategoryName',
                'u.Name as UnitName',
                's.Name as SupplierName',
                DB::raw('COALESCE(inv.StockQuantity, 0) as StockQuantity')
            );

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('Product.Name', 'like', "%{$keyword}%")
                  ->orWhere('Product.SKU', 'like', "%{$keyword}%")
                  ->orWhere('Product.NameUnsign', 'like', "%{$keyword}%")
                  ->orWhere('Product.Barcode', 'like', "%{$keyword}%");
            });
        }

        if (!empty($conditions['ProductCategoryId'])) {
            $query->where('Product.ProductCategoryId', $conditions['ProductCategoryId']);
        }

        if (!empty($conditions['SupplierId'])) {
            $query->where('Product.SupplierId', $conditions['SupplierId']);
        }

        if (isset($conditions['Status'])) {
            $query->where('Product.Status', $conditions['Status']);
        }

        if (!empty($conditions['FromDate'])) {
            $query->whereDate('Product.CreatedDate', '>=', $conditions['FromDate']);
        }

        if (!empty($conditions['ToDate'])) {
            $query->whereDate('Product.CreatedDate', '<=', $conditions['ToDate']);
        }

        $query->orderByDesc('Product.Status')->orderByDesc('Product.ProductId');
        $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);

        // Lấy danh sách ProductAttributeType và ProductAttributeValue cho từng product
        $productIds = $result->pluck('ProductId')->toArray();
        
        if (!empty($productIds)) {
            // Lấy ProductSkuAttribute mapping
            $skuAttributes = ProductSkuAttribute::whereIn('ProductId', $productIds)
                ->get()
                ->groupBy('ProductId');

            // Lấy tất cả ProductAttributeValueId
            $attributeValueIds = [];
            foreach ($skuAttributes as $attrs) {
                foreach ($attrs as $attr) {
                    $attributeValueIds[] = $attr->ProductAttributeValueId;
                }
            }
            $attributeValueIds = array_unique($attributeValueIds);

            // Lấy ProductAttributeValue
            $attributeValues = ProductAttributeValue::whereIn('ProductAttributeValueId', $attributeValueIds)
                ->where('Status', 1)
                ->get()
                ->keyBy('ProductAttributeValueId');

            // Lấy ProductAttributeTypeId từ values
            $attributeTypeIds = $attributeValues->pluck('ProductAttributeTypeId')->unique()->toArray();

            // Lấy ProductAttributeType
            $attributeTypes = ProductAttributeType::whereIn('ProductAttributeTypeId', $attributeTypeIds)
                ->where('Status', 1)
                ->get()
                ->keyBy('ProductAttributeTypeId');

            // Gắn attributes vào từng product
            foreach ($result->items() as $product) {
                $productAttributes = [];
                
                if (isset($skuAttributes[$product->ProductId])) {
                    foreach ($skuAttributes[$product->ProductId] as $skuAttr) {
                        $attrValue = $attributeValues[$skuAttr->ProductAttributeValueId] ?? null;
                        if ($attrValue) {
                            $attrType = $attributeTypes[$attrValue->ProductAttributeTypeId] ?? null;
                            if ($attrType) {
                                $productAttributes[] = [
                                    'ProductAttributeTypeId' => $attrType->ProductAttributeTypeId,
                                    'AttributeTypeName' => $attrType->Name,
                                    'AttributeTypeCode' => $attrType->Code,
                                    'DataType' => $attrType->DataType,
                                    'ProductAttributeValueId' => $attrValue->ProductAttributeValueId,
                                    'Value' => $attrValue->Value,
                                    'DisplayLabel' => $attrValue->DisplayLabel,
                                ];
                            }
                        }
                    }
                }
                
                $product->Attributes = $productAttributes;
            }

            // Group products by ProductBaseId
            $productBaseIds = $result->pluck('ProductBaseId')->filter()->unique()->toArray();

            if (!empty($productBaseIds)) {
                // Lấy thông tin ProductBase
                $productBases = \App\ProductBase::whereIn('ProductBaseId', $productBaseIds)
                    ->get()
                    ->keyBy('ProductBaseId');

                // Group products theo ProductBaseId
                $groupedByBase = [];
                foreach ($result->items() as $product) {
                    $baseId = $product->ProductBaseId;
                    
                    if ($baseId) {
                        if (!isset($groupedByBase[$baseId])) {
                            $base = $productBases[$baseId] ?? null;
                            $groupedByBase[$baseId] = [
                                'ProductBaseId' => $baseId,
                                'ProductBaseName' => $base ? $base->Name : null,
                                'ProductBaseCode' => $base ? $base->Code : null,
                                'ProductCategoryId' => $product->ProductCategoryId,
                                'ProductCategoryName' => $product->ProductCategoryName,
                                'ProductBrandId' => $product->ProductBrandId,
                                'ProductBrandName' => $product->ProductBrandName,
                                'Variants' => [],
                            ];
                        }
                        
                        $groupedByBase[$baseId]['Variants'][] = [
                            'ProductId' => $product->ProductId,
                            'SKU' => $product->SKU,
                            'Name' => $product->Name,
                            'Price' => $product->Price,
                            'StockQuantity' => $product->StockQuantity,
                            'Status' => $product->Status,
                            'Attributes' => $product->Attributes,
                        ];
                    }
                }
                
                // Gắn grouped data vào result
                $result->grouped = array_values($groupedByBase);
            }
        }

        return $result;
    }

    public function searchByProductBase(array $conditions = [])
    {
        $keyword = (string) ($conditions['Keyword'] ?? '');
        $limit   = min((int) ($conditions['limit'] ?? 20), 100);
        $lmstart = max((int) ($conditions['lmstart'] ?? 0), 0);

        // Query để lấy ProductBase thay vì Product
        $query = DB::connection('mysql_inventory')
            ->table('ProductBase as pb')
            ->leftJoin('ProductCategory as pc', 'pc.ProductCategoryId', '=', 'pb.ProductCategoryId')
            ->select(
                'pb.ProductBaseId',
                'pb.Code as ProductBaseCode',
                'pb.Name as ProductBaseName',
                'pb.NameUnsign',
                'pb.Description',
                'pb.ProductCategoryId',
                'pc.Name as ProductCategoryName',
                'pb.ProductBrandId',
                'pb.Status',
                'pb.CreatedDate'
            );

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('pb.Name', 'like', "%{$keyword}%")
                  ->orWhere('pb.Code', 'like', "%{$keyword}%")
                  ->orWhere('pb.NameUnsign', 'like', "%{$keyword}%");
            });
        }

        if (!empty($conditions['ProductCategoryId'])) {
            $query->where('pb.ProductCategoryId', $conditions['ProductCategoryId']);
        }

        if (isset($conditions['Status'])) {
            $query->where('pb.Status', $conditions['Status']);
        }

        if (!empty($conditions['FromDate'])) {
            $query->whereDate('pb.CreatedDate', '>=', $conditions['FromDate']);
        }

        if (!empty($conditions['ToDate'])) {
            $query->whereDate('pb.CreatedDate', '<=', $conditions['ToDate']);
        }

        $query->orderByDesc('pb.Status')->orderByDesc('pb.ProductBaseId');
        
        // Paginate ProductBase
        $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);

        // Lấy danh sách ProductBaseId
        $productBaseIds = collect($result->items())->pluck('ProductBaseId')->toArray();
        
        if (!empty($productBaseIds)) {
            // Lấy tất cả Products thuộc các ProductBase này
            $products = Product::whereIn('ProductBaseId', $productBaseIds)
                ->leftJoin('Unit as u', 'u.UnitId', '=', 'Product.UnitId')
                ->leftJoin('Supplier as s', 's.SupplierId', '=', 'Product.SupplierId')
                ->leftJoin('ProductBrand as pbr', 'pbr.ProductBrandId', '=', 'Product.ProductBrandId')
                ->leftJoin(DB::raw('(SELECT ProductId, SUM(Quantity) as StockQuantity FROM Inventory GROUP BY ProductId) as inv'), 'inv.ProductId', '=', 'Product.ProductId')
                ->select(
                    'Product.*',
                    'u.Name as UnitName',
                    's.Name as SupplierName',
                    'pbr.Name as ProductBrandName',
                    DB::raw('COALESCE(inv.StockQuantity, 0) as StockQuantity')
                )
                ->get()
                ->groupBy('ProductBaseId');

            $productIds = [];
            foreach ($products as $baseProducts) {
                foreach ($baseProducts as $product) {
                    $productIds[] = $product->ProductId;
                }
            }

            // Lấy ProductSkuAttribute mapping
            $skuAttributes = ProductSkuAttribute::whereIn('ProductId', $productIds)
                ->get()
                ->groupBy('ProductId');

            // Lấy tất cả ProductAttributeValueId
            $attributeValueIds = [];
            foreach ($skuAttributes as $attrs) {
                foreach ($attrs as $attr) {
                    $attributeValueIds[] = $attr->ProductAttributeValueId;
                }
            }
            $attributeValueIds = array_unique($attributeValueIds);

            // Lấy ProductAttributeValue
            $attributeValues = ProductAttributeValue::whereIn('ProductAttributeValueId', $attributeValueIds)
                ->where('Status', 1)
                ->get()
                ->keyBy('ProductAttributeValueId');

            // Lấy ProductAttributeTypeId từ values
            $attributeTypeIds = $attributeValues->pluck('ProductAttributeTypeId')->unique()->toArray();

            // Lấy ProductAttributeType
            $attributeTypes = ProductAttributeType::whereIn('ProductAttributeTypeId', $attributeTypeIds)
                ->where('Status', 1)
                ->get()
                ->keyBy('ProductAttributeTypeId');

            // Gắn Products và Attributes vào từng ProductBase
            foreach ($result->items() as $base) {
                $baseProducts = $products[$base->ProductBaseId] ?? collect();
                $variants = [];
                
                // Tạo map để gộp attributes theo AttributeTypeId
                $attributesMap = [];
                
                // Tạo map để gộp brands
                $brandsMap = [];

                foreach ($baseProducts as $product) {
                    $productAttributes = [];
                    
                    if (isset($skuAttributes[$product->ProductId])) {
                        foreach ($skuAttributes[$product->ProductId] as $skuAttr) {
                            $attrValue = $attributeValues[$skuAttr->ProductAttributeValueId] ?? null;
                            if ($attrValue) {
                                $attrType = $attributeTypes[$attrValue->ProductAttributeTypeId] ?? null;
                                if ($attrType) {
                                    $attrTypeId = $attrType->ProductAttributeTypeId;
                                    
                                    // Thêm vào productAttributes cho variant
                                    $productAttributes[] = [
                                        'ProductAttributeTypeId' => $attrTypeId,
                                        'AttributeTypeName' => $attrType->Name,
                                        'AttributeTypeCode' => $attrType->Code,
                                        'DataType' => $attrType->DataType,
                                        'ProductAttributeValueId' => $attrValue->ProductAttributeValueId,
                                        'Value' => $attrValue->Value,
                                        'DisplayLabel' => $attrValue->DisplayLabel,
                                    ];
                                    
                                    // Gộp vào attributesMap cho ProductBase
                                    if (!isset($attributesMap[$attrTypeId])) {
                                        $attributesMap[$attrTypeId] = [
                                            'ProductAttributeTypeId' => $attrTypeId,
                                            'AttributeTypeName' => $attrType->Name,
                                            'AttributeTypeCode' => $attrType->Code,
                                            'DataType' => $attrType->DataType,
                                            'Values' => []
                                        ];
                                    }
                                    
                                    // Thêm value vào danh sách (tránh trùng lặp)
                                    $valueKey = $attrValue->ProductAttributeValueId;
                                    if (!isset($attributesMap[$attrTypeId]['Values'][$valueKey])) {
                                        $attributesMap[$attrTypeId]['Values'][$valueKey] = [
                                            'ProductAttributeValueId' => $attrValue->ProductAttributeValueId,
                                            'Value' => $attrValue->Value,
                                            'DisplayLabel' => $attrValue->DisplayLabel,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                    
                    // Gộp Brand vào brandsMap (tránh trùng lặp)
                    if ($product->ProductBrandId && !isset($brandsMap[$product->ProductBrandId])) {
                        $brandsMap[$product->ProductBrandId] = [
                            'ProductBrandId' => $product->ProductBrandId,
                            'ProductBrandName' => $product->ProductBrandName,
                        ];
                    }

                    $variants[] = [
                        'ProductBrandId' => $product->ProductBrandId,
                        'ProductBrandName' => $product->ProductBrandName,
                        'ProductId' => $product->ProductId,
                        'SKU' => $product->SKU,
                        'SkuCode' => $product->SkuCode,
                        'Barcode' => $product->Barcode,
                        'Name' => $product->Name,
                        'BaseName' => $product->BaseName,
                        'Price' => $product->Price,
                        'InternalPrice' => $product->InternalPrice,
                        'UnitId' => $product->UnitId,
                        'UnitName' => $product->UnitName,
                        'SupplierId' => $product->SupplierId,
                        'SupplierName' => $product->SupplierName,
                        'StockQuantity' => $product->StockQuantity,
                        'Status' => $product->Status,
                        'Attributes' => $productAttributes,
                    ];
                }

                // Chuyển Values từ associative array sang indexed array
                $baseAttributes = [];
                foreach ($attributesMap as $attr) {
                    $attr['Values'] = array_values($attr['Values']);
                    $baseAttributes[] = $attr;
                }

                $base->Attributes = $baseAttributes;
                $base->Brand = array_values($brandsMap);
                $base->Variants = $variants;
                $base->TotalVariants = count($variants);
            }
        }

        return $result;
    }

    public function getDetail($id)
    {
        $product = Product::query()
            ->with(['productCategory:ProductCategoryId,Name', 'supplier:SupplierId,Name', 'unit:UnitId,Name', 'createdBy:StaffId,StaffCode,FullName'])
            ->where('ProductId', $id)
            ->where('Status', '!=', -1)
            ->first();

        if (!$product) {
            return null;
        }

        $data = $product->toArray();
        $data['ProductCategoryName'] = isset($product->productCategory) ? $product->productCategory->Name : null;
        $data['SupplierName']        = isset($product->supplier) ? $product->supplier->Name : null;
        $data['UnitName']            = isset($product->unit) ? $product->unit->Name : null;

        // Lấy danh sách nhà cung cấp từ ProductSupplierMapping
        $mappings = ProductSupplierMapping::where('ProductId', $id)
            ->where('Status', 1)
            ->orderBy('Priority')
            ->get(['ProductSupplierMappingId', 'SupplierId', 'Priority', 'Status']);

        $supplierIds = $mappings->pluck('SupplierId')->toArray();
        $suppliers   = [];
        if (!empty($supplierIds)) {
            $supplierList = \App\Supplier::whereIn('SupplierId', $supplierIds)
                ->select('SupplierId', 'Name', 'SupplierCode', 'TaxCode', 'Phone')
                ->get()
                ->keyBy('SupplierId');

            foreach ($mappings as $mapping) {
                $s = isset($supplierList[$mapping->SupplierId]) ? $supplierList[$mapping->SupplierId] : null;
                $suppliers[] = [
                    'ProductSupplierMappingId' => $mapping->ProductSupplierMappingId,
                    'SupplierId'               => $mapping->SupplierId,
                    'Priority'                 => $mapping->Priority,
                    'Status'                   => $mapping->Status,
                    'SupplierName'             => $s ? $s->Name : null,
                    'SupplierCode'             => $s ? $s->SupplierCode : null,
                    'TaxCode'                  => $s ? $s->TaxCode : null,
                    'Phone'                    => $s ? $s->Phone : null,
                ];
            }
        }
        $data['Suppliers'] = $suppliers;

        return (object) $data;
    }

    public function isSkuExists($sku, $excludeId = null)
    {
        $query = Product::where('SKU', $sku)->where('Status', '!=', -1);
        if ($excludeId) {
            $query->where('ProductId', '!=', $excludeId);
        }
        return $query->exists();
    }

    public function generateSKU()
    {
        $last = Product::query()
            ->where('SKU', 'like', 'SP%')
            ->orderByDesc('SKU')
            ->value('SKU');

        $next = $last ? ((int) substr($last, 2)) + 1 : 1;

        return 'SP' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    public function toggleState($id)
    {
        $product = Product::query()->where('ProductId', $id)->first();

        if (!$product) {
            return false;
        }

        return (bool) Product::query()
            ->where('ProductId', $id)
            ->update([
                'Status'      => $product->Status === 1 ? 0 : 1,
                'UpdatedBy'   => Auth::user()['StaffId'] ?? 0,
                'UpdatedDate' => Carbon::now(),
            ]);
    }

    // Các cột được phép insert/update
    protected $allowedColumns = [
        'SKU', 'Name', 'NameUnsign', 'Description', 'Priority',
        'ProductCategoryId', 'SupplierId', 'UnitId', 'IsTrackingSerial',
        'Price', 'PackageType', 'IsExpiryDate', 'Specification',
        'Barcode', 'Note', 'Status', 'CreatedBy', 'CreatedDate',
        'UpdatedBy', 'UpdatedDate',
    ];

    public function store(array $data)
    {
        $now       = Carbon::now();
        $filtered  = array_intersect_key($data, array_flip($this->allowedColumns));
        $productId = (int) Product::query()->insertGetId($filtered);

        if ($productId && !empty($data['SupplierIds'])) {
            $rows = [];
            foreach ($data['SupplierIds'] as $index => $supplier) {
                $supplierId = (int) ($supplier['SupplierId'] ?? 0);
                if (!$supplierId) continue;
                $rows[] = [
                    'ProductId'   => $productId,
                    'SupplierId'  => $supplierId,
                    'Status'      => 1,
                    'Priority'    => (int) ($supplier['Priority'] ?? ($index + 1)),
                    'CreatedDate' => $now,
                ];
            }
            if (!empty($rows)) {
                ProductSupplierMapping::insert($rows);
            }
        }

        return $productId;
    }

    public function updateSupplierMapping($productId, array $suppliers)
    {
        $now = Carbon::now();

        // Lấy danh sách SupplierId mới
        $newSupplierIds = array_column($suppliers, 'SupplierId');

        // Xóa các mapping không còn trong list mới
        ProductSupplierMapping::where('ProductId', $productId)
            ->whereNotIn('SupplierId', $newSupplierIds)
            ->delete();

        // Upsert từng supplier
        foreach ($suppliers as $index => $supplier) {
            $supplierId = (int) ($supplier['SupplierId'] ?? 0);
            if (!$supplierId) continue;

            $existing = ProductSupplierMapping::where('ProductId', $productId)
                ->where('SupplierId', $supplierId)
                ->first();

            if ($existing) {
                $existing->Status   = (int) ($supplier['Status'] ?? 1);
                $existing->Priority = (int) ($supplier['Priority'] ?? ($index + 1));
                $existing->save();
            } else {
                ProductSupplierMapping::insert([
                    'ProductId'   => $productId,
                    'SupplierId'  => $supplierId,
                    'Status'      => (int) ($supplier['Status'] ?? 1),
                    'Priority'    => (int) ($supplier['Priority'] ?? ($index + 1)),
                    'CreatedDate' => $now,
                ]);
            }
        }
    }

    public function updateById($id, array $data)
    {
        $filtered = array_intersect_key($data, array_flip($this->allowedColumns));
        return Product::query()
            ->where('ProductId', $id)
            ->update($filtered);
    }

    public function listProductBySupplier($conditions)
    {
        $keyword = (string) ($conditions['Keyword'] ?? '');
        $limit   = min((int) ($conditions['limit'] ?? 20), 100);
        $lmstart = max((int) ($conditions['lmstart'] ?? 0), 0);

        // Bước 1: Lấy danh sách ProductBaseId distinct trước
        $subQuery = DB::connection('mysql_inventory')
            ->table('ProductBase as pb')
            ->join('Product as p', 'p.ProductBaseId', '=', 'pb.ProductBaseId')
            ->join('ProductSupplierMapping as psm', 'psm.ProductId', '=', 'p.ProductId')
            ->where('psm.Status', 1)
            ->where('p.Status', 1)
            ->where('pb.Status', 1)
            ->select('pb.ProductBaseId')
            ->distinct();

        if ($keyword !== '') {
            $subQuery->where(function ($q) use ($keyword) {
                $q->where('pb.Name', 'like', "%{$keyword}%")
                  ->orWhere('pb.Code', 'like', "%{$keyword}%")
                  ->orWhere('pb.NameUnsign', 'like', "%{$keyword}%")
                  ->orWhere('p.Name', 'like', "%{$keyword}%")
                  ->orWhere('p.SKU', 'like', "%{$keyword}%")
                  ->orWhere('p.Barcode', 'like', "%{$keyword}%");
            });
        }

        if (!empty($conditions['SupplierId'])) {
            $subQuery->where('psm.SupplierId', $conditions['SupplierId']);
        }

        // Lấy tất cả ProductBaseId (không paginate ở đây)
        $allProductBaseIds = $subQuery->pluck('pb.ProductBaseId')->unique()->toArray();
        
        // Tính toán pagination manually
        $total = count($allProductBaseIds);
        $page = floor($lmstart / $limit) + 1;
        $offset = ($page - 1) * $limit;
        
        // Slice array để lấy ProductBaseIds cho trang hiện tại
        $productBaseIds = array_slice($allProductBaseIds, $offset, $limit);

        // Bước 2: Query thông tin ProductBase cho các IDs đã paginate
        $productBases = [];
        if (!empty($productBaseIds)) {
            $productBases = DB::connection('mysql_inventory')
                ->table('ProductBase as pb')
                ->leftJoin('ProductCategory as pc', 'pc.ProductCategoryId', '=', 'pb.ProductCategoryId')
                ->leftJoin('ProductBrand as pbr', 'pbr.ProductBrandId', '=', 'pb.ProductBrandId')
                ->whereIn('pb.ProductBaseId', $productBaseIds)
                ->select(
                    'pb.ProductBaseId',
                    'pb.Code as ProductBaseCode',
                    'pb.Name as ProductBaseName',
                    'pb.NameUnsign',
                    'pb.Description',
                    'pb.ProductCategoryId',
                    'pc.Name as ProductCategoryName',
                    'pb.ProductBrandId',
                    'pbr.Name as ProductBrandName',
                    'pb.Status',
                    'pb.CreatedDate'
                )
                ->orderByDesc('pb.Status')
                ->orderByDesc('pb.ProductBaseId')
                ->get();
        }

        // Tạo LengthAwarePaginator manually
        $result = new \Illuminate\Pagination\LengthAwarePaginator(
            $productBases,
            $total,
            $limit,
            $page,
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
        );
        
        if (!empty($productBaseIds)) {
            // Lấy tất cả Products thuộc các ProductBase này và có mapping với supplier
            $productsQuery = Product::whereIn('ProductBaseId', $productBaseIds)
                ->leftJoin('Unit as u', 'u.UnitId', '=', 'Product.UnitId')
                ->leftJoin('Supplier as s', 's.SupplierId', '=', 'Product.SupplierId')
                ->leftJoin(DB::raw('(SELECT ProductId, SUM(Quantity) as StockQuantity FROM Inventory GROUP BY ProductId) as inv'), 'inv.ProductId', '=', 'Product.ProductId')
                ->select(
                    'Product.*',
                    'u.Name as UnitName',
                    's.Name as SupplierName',
                    DB::raw('COALESCE(inv.StockQuantity, 0) as StockQuantity')
                )
                ->where('Product.Status', 1);

            // Nếu có filter theo SupplierId, chỉ lấy products có mapping với supplier đó
            if (!empty($conditions['SupplierId'])) {
                $productsQuery->join('ProductSupplierMapping as psm', function($join) use ($conditions) {
                    $join->on('psm.ProductId', '=', 'Product.ProductId')
                         ->where('psm.SupplierId', '=', $conditions['SupplierId'])
                         ->where('psm.Status', '=', 1);
                });
            }

            $products = $productsQuery->get()->groupBy('ProductBaseId');

            $productIds = [];
            foreach ($products as $baseProducts) {
                foreach ($baseProducts as $product) {
                    $productIds[] = $product->ProductId;
                }
            }

            // Lấy ProductSkuAttribute mapping
            $skuAttributes = ProductSkuAttribute::whereIn('ProductId', $productIds)
                ->get()
                ->groupBy('ProductId');

            // Lấy tất cả ProductAttributeValueId
            $attributeValueIds = [];
            foreach ($skuAttributes as $attrs) {
                foreach ($attrs as $attr) {
                    $attributeValueIds[] = $attr->ProductAttributeValueId;
                }
            }
            $attributeValueIds = array_unique($attributeValueIds);

            // Lấy ProductAttributeValue
            $attributeValues = ProductAttributeValue::whereIn('ProductAttributeValueId', $attributeValueIds)
                ->where('Status', 1)
                ->get()
                ->keyBy('ProductAttributeValueId');

            // Lấy ProductAttributeTypeId từ values
            $attributeTypeIds = $attributeValues->pluck('ProductAttributeTypeId')->unique()->toArray();

            // Lấy ProductAttributeType
            $attributeTypes = ProductAttributeType::whereIn('ProductAttributeTypeId', $attributeTypeIds)
                ->where('Status', 1)
                ->get()
                ->keyBy('ProductAttributeTypeId');

            // Gắn Products và Attributes vào từng ProductBase
            foreach ($result->items() as $base) {
                $baseProducts = $products[$base->ProductBaseId] ?? collect();
                $variants = [];
                
                // Tạo map để gộp attributes theo AttributeTypeId
                $attributesMap = [];

                foreach ($baseProducts as $product) {
                    $productAttributes = [];
                    
                    if (isset($skuAttributes[$product->ProductId])) {
                        foreach ($skuAttributes[$product->ProductId] as $skuAttr) {
                            $attrValue = $attributeValues[$skuAttr->ProductAttributeValueId] ?? null;
                            if ($attrValue) {
                                $attrType = $attributeTypes[$attrValue->ProductAttributeTypeId] ?? null;
                                if ($attrType) {
                                    $attrTypeId = $attrType->ProductAttributeTypeId;
                                    
                                    // Thêm vào productAttributes cho variant
                                    $productAttributes[] = [
                                        'ProductAttributeTypeId' => $attrTypeId,
                                        'AttributeTypeName' => $attrType->Name,
                                        'AttributeTypeCode' => $attrType->Code,
                                        'DataType' => $attrType->DataType,
                                        'ProductAttributeValueId' => $attrValue->ProductAttributeValueId,
                                        'Value' => $attrValue->Value,
                                        'DisplayLabel' => $attrValue->DisplayLabel,
                                    ];
                                    
                                    // Gộp vào attributesMap cho ProductBase
                                    if (!isset($attributesMap[$attrTypeId])) {
                                        $attributesMap[$attrTypeId] = [
                                            'ProductAttributeTypeId' => $attrTypeId,
                                            'AttributeTypeName' => $attrType->Name,
                                            'AttributeTypeCode' => $attrType->Code,
                                            'DataType' => $attrType->DataType,
                                            'Values' => []
                                        ];
                                    }
                                    
                                    // Thêm value vào danh sách (tránh trùng lặp)
                                    $valueKey = $attrValue->ProductAttributeValueId;
                                    if (!isset($attributesMap[$attrTypeId]['Values'][$valueKey])) {
                                        $attributesMap[$attrTypeId]['Values'][$valueKey] = [
                                            'ProductAttributeValueId' => $attrValue->ProductAttributeValueId,
                                            'Value' => $attrValue->Value,
                                            'DisplayLabel' => $attrValue->DisplayLabel,
                                        ];
                                    }
                                }
                            }
                        }
                    }

                    $variants[] = [
                        'ProductId' => $product->ProductId,
                        'SKU' => $product->SKU,
                        'SkuCode' => $product->SkuCode,
                        'Barcode' => $product->Barcode,
                        'Name' => $product->Name,
                        'BaseName' => $product->BaseName,
                        'Price' => $product->Price,
                        'InternalPrice' => $product->InternalPrice,
                        'UnitId' => $product->UnitId,
                        'UnitName' => $product->UnitName,
                        'SupplierId' => $product->SupplierId,
                        'SupplierName' => $product->SupplierName,
                        'StockQuantity' => $product->StockQuantity,
                        'Status' => $product->Status,
                        'Attributes' => $productAttributes,
                    ];
                }

                // Chuyển Values từ associative array sang indexed array
                $baseAttributes = [];
                foreach ($attributesMap as $attr) {
                    $attr['Values'] = array_values($attr['Values']);
                    $baseAttributes[] = $attr;
                }

                $base->Attributes = $baseAttributes;
                $base->Variants = $variants;
                $base->TotalVariants = count($variants);
            }
        }

        return $result;
    }
}
