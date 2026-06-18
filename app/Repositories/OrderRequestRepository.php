<?php

namespace App\Repositories;

use App\OrderRequest;
use App\OrderRequestDetail;
use App\OrderRequestTracking;
use App\Branch;
use App\Inventory;
use App\DepartmentDemand;
use App\DepartmentDemandLog;
use App\OutboutRequest;
use App\Warehouse;
use App\InventoryTransaction;
use App\OutboutRequestDetail;
use App\ProductSkuAttribute;
use App\ProductAttributeType;
use App\ProductAttributeValue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Repositories\Abstracts\EloquentRepository;
use Illuminate\Support\Facades\Log;

class OrderRequestRepository extends EloquentRepository
{
    protected function getModel()
    {
        return OrderRequest::class;
    }

    public function search(array $conditions = [])
    {
        $query = OrderRequest::query()
            ->with([
                'details:OrderRequestId,OrderRequestDetailId',
                'createdBy:StaffId,StaffCode,FullName',
                'branch:BranchId,BranchCode,Name'
            ]);

        if (!empty($conditions['DepartmentId'])) {
            $query->whereIn('DepartmentId', $conditions['DepartmentId']);
        }

        if (!empty($conditions['Keyword'])) {
            $query->where('OrderRequestCode', 'like', '%' . $conditions['Keyword'] . '%');
        }

        if (isset($conditions['RequestType'])) {
            $query->where('RequestType', $conditions['RequestType']);
        }

        if (isset($conditions['Status']) && $conditions['Status'] > 0 ) {
            $query->where('Status', $conditions['Status']);
        }

        $limit = min((int) ($conditions['limit'] ?? 20), 100);
        $lmstart = max((int) ($conditions['lmstart'] ?? 0), 0);
        $query->orderByDesc('OrderRequestId');
        $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);

        return $result;
    }

    public function listOrderRequest()
    {
        $result = OrderRequest::query()
            ->with([
                'details:OrderRequestId,OrderRequestDetailId,ProductId,UnitId,Specification,RequestQuantity,ReceivedQuantity,Note',
                'details.product:ProductId,SKU,Name,Price,SupplierId,ProductCategoryId,Specification,BaseName',
                'details.product.productCategory:ProductCategoryId,Name',
                'details.unit:UnitId,Code,Name',
                'details.product.supplier:SupplierId,Name',
                'createdBy:StaffId,StaffCode,FullName',
                'branch:BranchId,BranchCode,Name'
            ])
            ->where('Status', 10)
            ->orderByDesc('OrderRequestId')
            ->get();

        // Lấy tất cả ProductId trong details
        $productIds = [];
        foreach ($result as $orderRequest) {
            foreach ($orderRequest->details as $detail) {
                $productIds[] = $detail->ProductId;
            }
        }
        $productIds = array_unique($productIds);

        // Query tồn kho theo ProductId (tổng tất cả kho)
        $inventoryMap = [];
        if (!empty($productIds)) {
            $inventories = \App\Inventory::whereIn('ProductId', $productIds)
                ->selectRaw('ProductId, SUM(Quantity) as TotalQuantity')
                ->groupBy('ProductId')
                ->get();
            foreach ($inventories as $inv) {
                $inventoryMap[$inv->ProductId] = (int) $inv->TotalQuantity;
            }
        }

        // Gán StockQuantity vào từng detail
        foreach ($result as $orderRequest) {
            foreach ($orderRequest->details as $detail) {
                $detail->StockQuantity = $inventoryMap[$detail->ProductId] ?? 0;
            }
        }

        // Lấy danh sách nhà cung cấp ưu tiên theo ProductSupplierMapping
        $supplierMappings = [];
        if (!empty($productIds)) {
            $mappings = \App\ProductSupplierMapping::whereIn('ProductId', $productIds)
                ->where('Status', 1)
                ->orderBy('ProductId')
                ->orderBy('Priority')
                ->get(['ProductId', 'SupplierId', 'Priority', 'Status']);

            // Lấy thông tin Supplier
            $supplierIds = $mappings->pluck('SupplierId')->unique()->toArray();
            $suppliers   = [];
            if (!empty($supplierIds)) {
                $supplierList = \App\Supplier::whereIn('SupplierId', $supplierIds)
                    ->select('SupplierId', 'Name', 'Code as SupplierCode', 'TaxCode', 'Phone')
                    ->get()
                    ->keyBy('SupplierId');
                foreach ($supplierList as $sid => $s) {
                    $suppliers[$sid] = $s;
                }
            }

            foreach ($mappings as $mapping) {
                $supplierMappings[$mapping->ProductId][] = [
                    'SupplierId'   => $mapping->SupplierId,
                    'Priority'     => $mapping->Priority,
                    'Status'       => $mapping->Status,
                    'SupplierName' => isset($suppliers[$mapping->SupplierId]) ? $suppliers[$mapping->SupplierId]->Name : null,
                    'SupplierCode' => isset($suppliers[$mapping->SupplierId]) ? $suppliers[$mapping->SupplierId]->SupplierCode : null,
                    'TaxCode'      => isset($suppliers[$mapping->SupplierId]) ? $suppliers[$mapping->SupplierId]->TaxCode : null,
                    'Phone'        => isset($suppliers[$mapping->SupplierId]) ? $suppliers[$mapping->SupplierId]->Phone : null,
                ];
            }
        }

        // Lấy ProductBrand và ProductAttributeType và ProductAttributeValue cho các sản phẩm
        if (!empty($productIds)) {
            // Lấy ProductBrand cho các sản phẩm
            $products = \App\Product::whereIn('ProductId', $productIds)
                ->select('ProductId', 'ProductBrandId')
                ->get()
                ->keyBy('ProductId');

            $brandIds = $products->pluck('ProductBrandId')->filter()->unique()->toArray();
            $brands = [];
            if (!empty($brandIds)) {
                $brands = \App\ProductBrand::whereIn('ProductBrandId', $brandIds)
                    ->select('ProductBrandId', 'Name', 'Code')
                    ->get()
                    ->keyBy('ProductBrandId');
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

            // Gán Suppliers, ProductBrand và Attributes vào từng detail
            foreach ($result as $orderRequest) {
                foreach ($orderRequest->details as $detail) {
                    $detail->Suppliers = $supplierMappings[$detail->ProductId] ?? [];
                    
                    // Gán ProductBrand
                    $product = $products[$detail->ProductId] ?? null;
                    if ($product && $product->ProductBrandId) {
                        $brand = $brands[$product->ProductBrandId] ?? null;
                        if ($brand) {
                            $detail->product->ProductBrand = [
                                'ProductBrandId' => $brand->ProductBrandId,
                                'Name' => $brand->Name,
                                'Code' => $brand->Code,
                            ];
                        } else {
                            $detail->product->ProductBrand = null;
                        }
                    } else {
                        $detail->product->ProductBrand = null;
                    }
                    
                    // Gán Attributes
                    $productAttributes = [];
                    if (isset($skuAttributes[$detail->ProductId])) {
                        foreach ($skuAttributes[$detail->ProductId] as $skuAttr) {
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
                    
                    $detail->product->Attributes = $productAttributes;
                }
            }
        } else {
            // Gán Suppliers vào từng detail nếu không có productIds
            foreach ($result as $orderRequest) {
                foreach ($orderRequest->details as $detail) {
                    $detail->Suppliers = $supplierMappings[$detail->ProductId] ?? [];
                }
            }
        }

        return $result;
    }

    public function searchForWarehouse(array $conditions = [])
    {
        $query = OrderRequest::query();

        if (!empty($conditions['Keyword'])) {
            $query->where('OrderRequestCode', 'like', '%' . $conditions['Keyword'] . '%');
        }

        if (!empty($conditions['DepartmentId'])) {
            $query->where('DepartmentId', $conditions['DepartmentId']);
        }

        if (isset($conditions['RequestType'])) {
            $query->where('RequestType', $conditions['RequestType']);
        }

        if (isset($conditions['Status'])) {
            $query->where('Status', $conditions['Status']);
        }

        $limit = min((int) ($conditions['limit'] ?? 20), 100);
        $lmstart = max((int) ($conditions['lmstart'] ?? 0), 0);
        $query->orderByDesc('OrderRequestId');
        $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);

        return $result;
    }

    public function getDetail($id)
    {
        $orderRequest = OrderRequest::query()
            ->with([
                'details:OrderRequestId,OrderRequestDetailId,ProductId,UnitId,Specification,RequestQuantity,ReceivedQuantity,Note',
                'details.product:ProductId,SKU,Name,Price,Specification,BaseName,ProductBrandId',
                'details.product.brand:ProductBrandId,Name',
                'details.unit:UnitId,Code,Name',
                'outboutRequests.details',
                'outboutRequests.details.product:ProductId,SKU,Name,Price,ProductBrandId',
                'outboutRequests.details.product.brand:ProductBrandId,Name',
                'outboutRequests.details.unit:UnitId,Code,Name',
                'createdBy:StaffId,StaffCode,FullName',
                'branch:BranchId,BranchCode,Name'
            ])
            ->find($id);

        if (!$orderRequest) {
            return null;
        }

        // Lấy tồn kho cho các sản phẩm trong details
        $productIds = $orderRequest->details->pluck('ProductId')->unique()->toArray();
        if (!empty($productIds)) {
            $inventoryMap = Inventory::whereIn('ProductId', $productIds)
                ->selectRaw('ProductId, SUM(Quantity) as TotalQuantity')
                ->groupBy('ProductId')
                ->get()
                ->pluck('TotalQuantity', 'ProductId')
                ->toArray();

            foreach ($orderRequest->details as $detail) {
                $detail->StockQuantity = (int) ($inventoryMap[$detail->ProductId] ?? 0);
            }

            // Lấy ProductAttributeType và ProductAttributeValue cho các sản phẩm
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

            // Gắn attributes vào từng product trong details
            foreach ($orderRequest->details as $detail) {
                $productAttributes = [];
                
                if (isset($skuAttributes[$detail->ProductId])) {
                    foreach ($skuAttributes[$detail->ProductId] as $skuAttr) {
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
                
                $detail->product->Attributes = $productAttributes;
            }

            // Gắn attributes cho outboutRequests.details nếu có
            if ($orderRequest->outboutRequests) {
                foreach ($orderRequest->outboutRequests as $outboutRequest) {
                    if ($outboutRequest->details) {
                        foreach ($outboutRequest->details as $detail) {
                            $productAttributes = [];
                            
                            if (isset($skuAttributes[$detail->ProductId])) {
                                foreach ($skuAttributes[$detail->ProductId] as $skuAttr) {
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
                            
                            $detail->product->Attributes = $productAttributes;
                        }
                    }
                }
            }
        }

        return $orderRequest;
    }

    public function getDetailForWarehouse($id)
    {
        return OrderRequest::query()
            ->with([
                'details:OrderRequestId,OrderRequestDetailId,ProductId,UnitId,Specification,RequestQuantity,ReceivedQuantity,Note',
                'details.product:ProductId,SKU,Name,Price',
                'details.unit:UnitId,Code,Name',
                'outboutRequests.details',
                'outboutRequests.details.product:ProductId,SKU,Name,Price',
                'outboutRequests.details.unit:UnitId,Code,Name',
            ])
            ->find($id);
    }

    protected $allowedColumns = [
        'OrderRequestCode', 'DepartmentType', 'DepartmentId', 'RequestDate',
        'RequestType', 'ExpectedDeliveryDate', 'Status', 'RequestedBy',
        'Note', 'MaterialGroupId', 'ProcessType',
        'CreatedBy', 'CreatedDate', 'UpdatedBy', 'UpdatedDate',
    ];

    public function store(array $data, array $details)
    {
        DB::beginTransaction();
        try {
            $filtered       = array_intersect_key($data, array_flip($this->allowedColumns));
            $orderRequestId = (int) OrderRequest::insertGetId($filtered);

            $createdBy = $data['CreatedBy'] ?? 0;
            $createdDate = $data['CreatedDate'] ?? Carbon::now();

            if (!empty($details)) {
                $rows = [];
                foreach ($details as $d) {
                    $rows[] = array_merge($d, [
                        'OrderRequestId'   => $orderRequestId,
                        'ReceivedQuantity' => 0,
                        'CreatedBy'        => $createdBy,
                        'CreatedDate'      => $createdDate,
                    ]);
                }
                OrderRequestDetail::insert($rows);

                // KHÔNG xử lý DepartmentDemand ở đây
                // DepartmentDemand chỉ được xử lý khi updateStatus với Status = 10 (Tiếp nhận)
            }

            // Ghi tracking
            $this->logTracking($orderRequestId, 'create', null, 1, null, $filtered, null, $createdBy);

            DB::commit();
            return $orderRequestId;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('OrderRequestRepository@store: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateOrderRequest($id, array $data, $details = null)
    {
        $allowed = [
            'Note', 'ExpectedDeliveryDate', 'MaterialGroupId',
            'UpdatedBy', 'UpdatedDate',
        ];
        $filtered = array_intersect_key($data, array_flip($allowed));

        // Lấy data cũ trước khi update
        $oldData = OrderRequest::find($id);
        $oldDataArr = $oldData ? $oldData->toArray() : null;

        OrderRequest::where('OrderRequestId', $id)->update($filtered);

        // Ghi tracking
        $this->logTracking($id, 'update', null, null, $oldDataArr, $filtered, null, $data['UpdatedBy'] ?? null);

        // Nếu có truyền Details thì sync lại
        if ($details !== null) {
            $now        = Carbon::now();
            $staffId    = $data['UpdatedBy'] ?? 0;
            $incomingIds = [];

            foreach ($details as $d) {
                if (!empty($d['OrderRequestDetailId'])) {
                    // Cập nhật dòng cũ
                    OrderRequestDetail::where('OrderRequestDetailId', $d['OrderRequestDetailId'])
                        ->where('OrderRequestId', $id)
                        ->update([
                            'ProductId'       => $d['ProductId'] ?? null,
                            'UnitId'          => $d['UnitId'] ?? null,
                            'Specification'   => $d['Specification'] ?? null,
                            'RequestQuantity' => (int) ($d['RequestQuantity'] ?? 0),
                            'Note'            => $d['Note'] ?? null,
                            'UpdatedBy'       => $staffId,
                            'UpdatedDate'     => $now,
                        ]);
                    $incomingIds[] = (int) $d['OrderRequestDetailId'];
                } else {
                    // Thêm dòng mới
                    $newId = OrderRequestDetail::insertGetId([
                        'OrderRequestId'   => $id,
                        'ProductId'        => $d['ProductId'] ?? null,
                        'UnitId'           => $d['UnitId'] ?? null,
                        'Specification'    => $d['Specification'] ?? null,
                        'RequestQuantity'  => (int) ($d['RequestQuantity'] ?? 0),
                        'ReceivedQuantity' => 0,
                        'Note'             => $d['Note'] ?? null,
                        'CreatedBy'        => $staffId,
                        'CreatedDate'      => $now,
                    ]);
                    $incomingIds[] = $newId;
                }
            }

            // Xóa các dòng không còn trong payload
            if (!empty($incomingIds)) {
                OrderRequestDetail::where('OrderRequestId', $id)
                    ->whereNotIn('OrderRequestDetailId', $incomingIds)
                    ->delete();
            }
        }

        return true;
    }

    public function updateExpectedDeliveryDate($id, $expectedDeliveryDate, $staffId)
    {
        $old = OrderRequest::where('OrderRequestId', $id)->value('ExpectedDeliveryDate');

        $result = (bool) OrderRequest::where('OrderRequestId', $id)->update([
            'ExpectedDeliveryDate' => $expectedDeliveryDate,
            'UpdatedBy'            => $staffId,
            'UpdatedDate'          => Carbon::now(),
        ]);

        $this->logTracking($id, 'update_expected_delivery_date', null, null,
            ['ExpectedDeliveryDate' => $old],
            ['ExpectedDeliveryDate' => $expectedDeliveryDate],
            null, $staffId
        );

        return $result;
    }

    public function updateStatus($id, $status, $note, $staffId, $details, $expectedDeliveryDate)
    {
        $orderRequest = OrderRequest::query()->find($id);

        if (!$orderRequest) {
            return false;
        }

        $now = Carbon::now();
        $oldStatus = $orderRequest->Status;

        // Khi tiếp nhận yêu cầu (status = 10): Xử lý DepartmentDemand
        if ($status === 10) {
            DB::beginTransaction();
            try {
                $departmentId = $orderRequest->DepartmentId;
                $departmentType = $orderRequest->DepartmentType ?? 1;
                $orderRequestCode = $orderRequest->OrderRequestCode;
                $expectedReceiptDate = $orderRequest->ExpectedDeliveryDate;
                $expectedDeliveryDate = $expectedDeliveryDate;

                // Lấy tất cả OrderRequestDetail
                $orderDetails = OrderRequestDetail::where('OrderRequestId', $id)->get();

                foreach ($orderDetails as $detail) {
                    $productId = $detail->ProductId;
                    $unitId = $detail->UnitId;
                    $requestQty = $detail->RequestQuantity;

                    if ($productId > 0 && $requestQty > 0) {
                        // Tìm hoặc tạo DepartmentDemand
                        $demand = DepartmentDemand::where('DepartmentId', $departmentId)
                            ->where('ProductId', $productId)
                            ->where('UnitId', $unitId)
                            ->first();

                        $qtyBefore = 0;
                        if ($demand) {
                            // Update existing
                            $qtyBefore = $demand->PendingQty;
                            $demand->PendingQty += $requestQty;
                            $demand->TotalRequestedQty += $requestQty;
                            $demand->ExpectedReceiptDate = $demand->ExpectedReceiptDate < $now ? $expectedReceiptDate : min($demand->ExpectedReceiptDate, $expectedReceiptDate);
                            if($demand->ExpectedDeliveryDate == ''){
                                $demand->ExpectedDeliveryDate = $expectedDeliveryDate;
                            }
                            $demand->UpdatedDate = $now;
                            $demand->save();
                        } else {
                            // Create new
                            $demand = DepartmentDemand::create([
                                'DepartmentId'       => $departmentId,
                                'DepartmentType'     => $departmentType,
                                'ProductId'          => $productId,
                                'UnitId'             => $unitId,
                                'PendingQty'         => $requestQty,
                                'TotalRequestedQty'  => $requestQty,
                                'TotalDeliveredQty'  => 0,
                                'ExpectedReceiptDate'  => $expectedReceiptDate,
                                'ExpectedDeliveryDate' => $expectedDeliveryDate,
                                'UpdatedDate'        => $now,
                            ]);
                        }

                        // Insert DepartmentDemandLog
                        DepartmentDemandLog::create([
                            'DepartmentDemandId' => $demand->DepartmentDemandId,
                            'DepartmentId'       => $departmentId,
                            'ProductId'          => $productId,
                            'UnitId'             => $unitId,
                            'ChangeType'         => 'Confirmed',
                            'ChangeQty'          => $requestQty,
                            'QtyBefore'          => $qtyBefore,
                            'QtyAfter'           => $qtyBefore + $requestQty,
                            'RefType'            => 'PurchaseRequestItem',
                            'RefId'              => $id,
                            'Note'               => 'Tiếp nhận yêu cầu cấp phát: ' . $orderRequestCode,
                            'CreatedDate'        => $now,
                            'CreatedBy'          => $staffId,
                        ]);
                    }
                }

                // Cập nhật Status của OrderRequest
                OrderRequest::where('OrderRequestId', $id)->update([
                    'Status'      => $status,
                    'Note'        => $note,
                    'UpdatedBy'   => $staffId,
                    'UpdatedDate' => $now,
                ]);

                $this->logTracking($id, 'update_status', $oldStatus, $status, null, null, null, $staffId);

                DB::commit();
                return true;

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('OrderRequestRepository@updateStatus (Status=10): ' . $e->getMessage());
                throw $e;
            }
        }

        // Khi nhận hàng (status = 5): cộng dồn ReceivedQuantity, tự xác định status thực tế
        if ($status === 5 && !empty($details)) {
            foreach ($details as $detail) {
                if (empty($detail['OrderRequestDetailId'])) {
                    continue;
                }
                $addQty = max(0, (int) ($detail['ReceivedQuantity'] ?? 0));
                if ($addQty <= 0) continue;

                // Cộng dồn ReceivedQuantity
                OrderRequestDetail::where('OrderRequestDetailId', $detail['OrderRequestDetailId'])
                    ->where('OrderRequestId', $id)
                    ->update([
                        'ReceivedQuantity' => DB::raw("ReceivedQuantity + {$addQty}"),
                        'UpdatedBy'        => $staffId,
                        'UpdatedDate'      => $now,
                    ]);
            }

            // Kiểm tra tất cả sản phẩm đã nhận đủ chưa
            $allDetails = OrderRequestDetail::where('OrderRequestId', $id)->get();
            $allCompleted = true;
            foreach ($allDetails as $d) {
                if ($d->ReceivedQuantity < $d->RequestQuantity) {
                    $allCompleted = false;
                    break;
                }
            }

            // Nếu chưa đủ → giữ status = 4 (đang vận chuyển), đủ → status = 5
            $status = $allCompleted ? 5 : 4;
        }

        // Cập nhật Status cho các trường hợp khác
        $result = (bool) OrderRequest::where('OrderRequestId', $id)->update([
            'Status'      => $status,
            'Note'        => $note,
            'UpdatedBy'   => $staffId,
            'UpdatedDate' => $now,
        ]);

        $this->logTracking($id, 'update_status', $oldStatus, $status, null, null, null, $staffId);

        return $result;
    }

    private function logTracking($orderRequestId, $actionType, $oldStatus, $newStatus, $oldData, $newData, $note, $createdBy)
    {
        OrderRequestTracking::insert([
            'OrderRequestId' => $orderRequestId,
            'ActionType'     => $actionType,
            'OldStatus'      => $oldStatus,
            'NewStatus'      => $newStatus,
            'OldData'        => $oldData ? json_encode($oldData) : null,
            'NewData'        => $newData ? json_encode($newData) : null,
            'Note'           => $note,
            'CreatedBy'      => $createdBy,
            'CreatedDate'    => Carbon::now(),
        ]);
    }

    public function generateCode($branchId)
    {
        $branchCode = Branch::where('BranchId', $branchId)->value('BranchCode');
        $branchSuffix = $branchCode ? strtoupper(substr($branchCode, -3)) : 'YC';

        $prefix = $branchSuffix . '-' . Carbon::now()->format('ym') . '-';

        $last = OrderRequest::query()
            ->where('OrderRequestCode', 'like', $prefix . '%')
            ->orderBy('OrderRequestCode', 'desc')
            ->value('OrderRequestCode');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Lấy danh sách nhu cầu vật tư theo phòng khám
     * Dựa vào bảng DepartmentDemand và in.Branch
     * 
     * @param array $conditions
     * @return array
     */
    public function getDepartmentDemandList(array $conditions = [])
    {
        $query = DB::connection('mysql_inventory')
            ->table('DepartmentDemand as dd')
            ->join('in.Branch as b', 'b.BranchId', '=', 'dd.DepartmentId')
            ->select(
                'b.BranchId as DepartmentId',
                'b.BranchCode',
                'b.Name as BranchName',
                DB::raw('COUNT(DISTINCT dd.ProductId) as TotalProducts'),
                DB::raw('SUM(dd.PendingQty) as TotalPendingQty'),
                DB::raw('SUM(dd.TotalRequestedQty) as TotalRequestedQty'),
                DB::raw('SUM(dd.TotalDeliveredQty) as TotalDeliveredQty')
            )
            // ->where('dd.PendingQty', '>', 0)
            ->where('b.State', 1)
            ->groupBy('b.BranchId', 'b.BranchCode', 'b.Name');

        // Filter theo BranchId
        if (!empty($conditions['BranchId']) && $conditions['BranchId'] > 0) {
            $query->whereIn('dd.DepartmentId', $conditions['BranchId']);
        }

        // Sort
        $sortBy = 'TotalPendingQty';
        $sortOrder = 'DESC';
        
        if (in_array($sortBy, ['BranchCode', 'BranchName', 'TotalPendingQty', 'TotalRequestedQty'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $query->get()->toArray();
    }

    /**
     * Lấy chi tiết nhu cầu vật tư của một phòng khám
     * 
     * @param int $departmentId
     * @return array
     */
    public function getDepartmentDemandDetail($departmentId, $keyword = null)
    {
        // Lấy WarehouseId của kho trung tâm (WarehouseType = 1)
        $centralWarehouse = Warehouse::where('WarehouseType', 1)
            ->where('Status', 1)
            ->first();

        $centralWarehouseId = $centralWarehouse ? $centralWarehouse->WarehouseId : null;

        $query = DB::connection('mysql_inventory')
            ->table('DepartmentDemand as dd')
            ->join('Product as p', 'p.ProductId', '=', 'dd.ProductId')
            ->join('ProductBrand as pb', 'pb.ProductBrandId', '=', 'p.ProductBrandId')
            ->join('Unit as u', 'u.UnitId', '=', 'dd.UnitId')
            ->leftJoin('Inventory as inv', function ($join) use ($centralWarehouseId) {
                $join->on('inv.ProductId', '=', 'dd.ProductId')
                     ->where('inv.WarehouseId', '=', $centralWarehouseId)
                     ->where('inv.Status', '=', 1);
            })
            ->select(
                'dd.DepartmentDemandId',
                'dd.DepartmentId',
                'dd.ProductId',
                'p.SKU',
                'p.Name as ProductName',
                'p.BaseName',
                'p.Specification',
                'pb.ProductBrandId',
                'pb.Name as ProductBrandName',
                'dd.UnitId',
                'u.Name as UnitName',
                'dd.TotalRequestedQty',
                'dd.TotalDeliveredQty',
                'dd.ExpectedDeliveryDate',
                'dd.ExpectedReceiptDate',
                'dd.UpdatedDate',
                'dd.PendingQty',
                'dd.DeliveryDate',
                DB::raw('(dd.TotalRequestedQty - dd.TotalDeliveredQty) as PendingPurchaseQty'),
                DB::raw('COALESCE(inv.Quantity, 0) as StockQuantity'),
                DB::raw('COALESCE(inv.MinStock, 0) as MinStock'),
                'inv.WarehouseId as CentralWarehouseId'
            )
            // ->where('dd.PendingQty', '>', 0)
            ->where('dd.DepartmentId', $departmentId);

        // Nếu có keyword, search theo ProductBase
        if (!empty($keyword)) {
            $query->where(function ($q) use ($keyword) {
                $q->where('p.BaseName', 'like', '%' . $keyword . '%')
                  ->orWhere('p.Name', 'like', '%' . $keyword . '%')
                  ->orWhere('p.SKU', 'like', '%' . $keyword . '%');
            });
        }

        $query->orderBy('ExpectedReceiptDate', 'ASC');

        $results = $query->get()->toArray();

        // Lấy ProductAttributeType và ProductAttributeValue cho các sản phẩm
        $productIds = array_unique(array_column($results, 'ProductId'));
        
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
            foreach ($results as &$item) {
                $productAttributes = [];
                
                if (isset($skuAttributes[$item->ProductId])) {
                    foreach ($skuAttributes[$item->ProductId] as $skuAttr) {
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
                
                $item->Attributes = $productAttributes;
            }
        }

        return $results;
    }

    /**
     * Tạo phiếu giao hàng từ DepartmentDemand
     * 
     * @param array $data
     * @return int OutboutRequestId
     */
    public function createDeliveryFromDemand(array $data)
    {
        DB::beginTransaction();
        try {
            $departmentId = $data['DepartmentId'] ?? 0;
            $products = $data['Products'] ?? []; // [{ProductId, UnitId, DeliveryQty}]
            $createdBy = $data['CreatedBy'] ?? 0;
            $createdDate = $data['CreatedDate'] ?? Carbon::now();

            if (empty($products)) {
                throw new \Exception('Vui lòng chọn sản phẩm để giao hàng');
            }

            // Tạo OutboutRequest
            $outboutCode = $this->generateOutboutCode();
            $outboutData = [
                'OutboutCode'         => $outboutCode,
                'DepartmentType'      => $data['DepartmentType'] ?? 1,
                'DepartmentId'        => $departmentId,
                'RelatedType'         => 2, // 2 = Từ DepartmentDemand
                'RelatedId'           => null,
                'TotalSKU'            => count($products),
                'Status'              => 1, // 1 = Đang giao
                'ExpectedReceiptDate' => $data['ExpectedReceiptDate'] ?? null,
                'DeliveryStaff'       => $data['DeliveryStaff'] ?? null,
                'DeliveryDate'        => $data['DeliveryDate'] ?? null,
                'Note'                => $data['Note'] ?? null,
                'CreatedBy'           => $createdBy,
                'CreatedDate'         => $createdDate,
            ];

            $outboutRequestId = (int) OutboutRequest::insertGetId($outboutData);

            // Tạo OutboutRequestDetail và update DepartmentDemand
            $outboutDetails = [];
            foreach ($products as $product) {
                $productId = $product['ProductId'] ?? 0;
                $unitId = $product['UnitId'] ?? null;
                $deliveryQty = (int) ($product['DeliveryQty'] ?? 0);

                if ($productId <= 0 || $deliveryQty <= 0) {
                    continue;
                }

                // Insert OutboutRequestDetail
                $outboutDetails[] = [
                    'OutboutRequestId' => $outboutRequestId,
                    'ProductId'        => $productId,
                    'UnitId'           => $unitId,
                    'ExpectedQty'      => $deliveryQty,
                    'ActualQty'        => 0,
                    'CreatedBy'        => $createdBy,
                    'CreatedDate'      => $createdDate,
                ];

                // Update DepartmentDemand: giảm PendingQty
                $demand = DepartmentDemand::where('DepartmentId', $departmentId)
                    ->where('ProductId', $productId)
                    ->where('UnitId', $unitId)
                    ->first();

                if ($demand) {
                    $qtyBefore = $demand->PendingQty;
                    $totalRequestedQty = $demand->TotalRequestedQty;
                    $newPendingQty = max(0, $qtyBefore - $deliveryQty);
                    
                    $demand->PendingQty = $newPendingQty;
                    if($qtyBefore == $totalRequestedQty) {
                        $demand->ExpectedDeliveryDate = $data['ExpectedReceiptDate'];
                    }else {
                        $demand->ExpectedDeliveryDate = $demand->ExpectedDeliveryDate < $createdDate ? $data['ExpectedReceiptDate'] : min($demand->ExpectedDeliveryDate, $data['ExpectedReceiptDate']);
                    }
                    $demand->DeliveryDate = $data['DeliveryDate'];
                    $demand->UpdatedDate = $createdDate;
                    $demand->save();

                    // Insert DepartmentDemandLog
                    DepartmentDemandLog::create([
                        'DepartmentDemandId' => $demand->DepartmentDemandId,
                        'DepartmentId'       => $departmentId,
                        'ProductId'          => $productId,
                        'UnitId'             => $unitId,
                        'ChangeType'         => 'Delivered',
                        'ChangeQty'          => -$deliveryQty,
                        'QtyBefore'          => $qtyBefore,
                        'QtyAfter'           => $newPendingQty,
                        'RefType'            => 'DeliveryNoteItem',
                        'RefId'              => $outboutRequestId,
                        // 'Note'               => 'Tạo phiếu giao hàng: ' . $outboutCode,
                        'CreatedDate'        => $createdDate,
                        'CreatedBy'          => $createdBy,
                    ]);
                }

                // Trừ tồn kho tại kho trung tâm
                $centralWarehouse = Warehouse::where('WarehouseType', 1)
                    ->where('Status', 1)
                    ->first();

                if ($centralWarehouse) {
                    $inventory = Inventory::where('ProductId', $productId)
                        ->where('WarehouseId', $centralWarehouse->WarehouseId)
                        ->first();

                    if ($inventory) {
                        $inventory->Quantity = max(0, $inventory->Quantity - $deliveryQty);
                        $inventory->UpdatedBy = $createdBy;
                        $inventory->UpdatedDate = $createdDate;
                        $inventory->save();

                        // Log inventory transaction
                        InventoryTransaction::insert([
                            'InventoryId'     => $inventory->InventoryId,
                            'TransactionType' => 2, // 2 = Xuất 
                            'ProductId'       => $productId,
                            'UnitId'          => $unitId,
                            'Quantity'        => $deliveryQty,
                            'ReferenceType'         => 'OutboutRequest',
                            'ReferenceId'           => $outboutRequestId,
                            'Note'            => $data['Note'] ?? null,
                            'CreatedBy'       => $createdBy,
                            'CreatedDate'     => $createdDate,
                        ]);
                    }
                }
            }

            if (!empty($outboutDetails)) {
                OutboutRequestDetail::insert($outboutDetails);
            }

            DB::commit();
            return $outboutRequestId;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('OrderRequestRepository@createDeliveryFromDemand: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate OutboutCode
     */
    private function generateOutboutCode()
    {
        $prefix = 'GH-' . Carbon::now()->format('ym') . '-';

        $last = OutboutRequest::where('OutboutCode', 'like', $prefix . '%')
            ->orderBy('OutboutCode', 'desc')
            ->value('OutboutCode');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
