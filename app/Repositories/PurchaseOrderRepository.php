<?php

namespace App\Repositories;

use App\PurchaseOrder;
use App\PurchaseOrderDetail;
use App\Inventory;
use App\Staff;
use App\OrderRequest;
use App\ProductSkuAttribute;
use App\ProductAttributeType;
use App\ProductAttributeValue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Repositories\Abstracts\EloquentRepository;

class PurchaseOrderRepository extends EloquentRepository
{
    protected function getModel()
    {
        return PurchaseOrder::class;
    }

    public function search(array $conditions = [])
    {
        $query = PurchaseOrder::query()->with('supplier');

        if (!empty($conditions['SupplierId'])) {
            $query->where('SupplierId', $conditions['SupplierId']);
        }

        if (!empty($conditions['Status'])) {
            $query->where('Status', $conditions['Status']);
        }

        if (!empty($conditions['FromDate'])) {
            $query->where('CreatedDate', '>=', $conditions['FromDate']);
        }

        if (!empty($conditions['ToDate'])) {
            $query->where('CreatedDate', '<=', $conditions['ToDate'] . ' 23:59:59');
        }

        if (!empty($conditions['Keyword'])) {
            $query->where('PurchaseOrderCode', 'like', '%' . $conditions['Keyword'] . '%');
        }

        $limit   = min((int) ($conditions['limit'] ?? 20), 100);
        $lmstart = max((int) ($conditions['lmstart'] ?? 0), 0);

        $query->orderByDesc('PurchaseOrderId');

        $results = $query->paginate($limit,['*'],'page', round((int) $lmstart/ (int) $limit) + 1);

        return $results;
    }

    public function getListForExport(array $conditions = [])
    {
        $query = PurchaseOrder::query()->with(['supplier', 'details.product', 'details.unit']);

        if (!empty($conditions['PurchaseOrderId'])) {
            $query->where('PurchaseOrderId', $conditions['PurchaseOrderId']);
        }

        return $query->get()->toArray();
    }

    public function getDetail($id)
    {
        $po = PurchaseOrder::query()
            ->with([
                'supplier',
                'details.product',
                'details.unit',
                'inboutRequests.details.product:ProductId,SKU,Name,Specification,BaseName',
                'inboutRequests.details.unit:UnitId,Code,Name',
            ])
            ->find($id);

        if (!$po) {
            return null;
        }

        // Load người nhập kho (cross-database: mysql_in)
        foreach ($po->inboutRequests as $inbout) {
            if ($inbout->CreatedBy) {
                $staff = Staff::where('StaffId', $inbout->CreatedBy)
                    ->select('StaffId', 'StaffCode', 'FullName')
                    ->first();
                $inbout->setRelation('createdBy', $staff);
            }
        }

        // Lấy tồn kho cho các sản phẩm trong details
        $productIds = $po->details->pluck('ProductId')->unique()->toArray();
        if (!empty($productIds)) {
            $inventoryMap = Inventory::whereIn('ProductId', $productIds)
                ->selectRaw('ProductId, SUM(Quantity) as TotalQuantity')
                ->groupBy('ProductId')
                ->get()
                ->pluck('TotalQuantity', 'ProductId')
                ->toArray();

            foreach ($po->details as $detail) {
                $detail->StockQuantity = (int) ($inventoryMap[$detail->ProductId] ?? 0);
            }

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

            // Gắn ProductBrand vào từng product trong details
            foreach ($po->details as $detail) {
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
            foreach ($po->details as $detail) {
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

            // Gắn attributes và ProductBrand cho inboutRequests.details nếu có
            if ($po->inboutRequests) {
                foreach ($po->inboutRequests as $inbout) {
                    if ($inbout->details) {
                        foreach ($inbout->details as $detail) {
                            // Gắn ProductBrand
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

                            // Gắn Attributes
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

        return (object) $po->toArray();
    }

    /**
     * Tạo nhiều PO cùng lúc.
     * @param array $orders  [{data: [], details: []}]
     * @return array danh sách PurchaseOrderId vừa tạo
     */
    public function store(array $orders, array $orderRequestIds)
    {
        $createdIds = [];
        $now        = Carbon::now();
        $staffId    = Auth::user()['StaffId'] ?? 0;

        foreach ($orders as $order) {
            $data    = $order['data'];
            $details = $order['details'];

            $data['PurchaseOrderCode'] = $this->generateCode();

            $totalAmount = 0;
            $detailRows  = [];

            foreach ($details as $detail) {
                $quantity   = (int) $detail['Quantity'];
                $unitPrice  = (float) $detail['UnitPrice'];
                $amount     = $quantity * $unitPrice;
                $totalAmount += $amount;

                $detailRows[] = [
                    'ProductId'        => $detail['ProductId'],
                    'UnitId'           => $detail['UnitId'] ?? null,
                    'Specification'    => $detail['Specification'] ?? null,
                    'Quantity'         => $quantity,
                    'UnitPrice'        => $unitPrice,
                    'Amount'           => $amount,
                    'ReceivedQuantity' => 0,
                    'Note'             => $detail['Note'] ?? null,
                    'Status'           => 1,
                    'CreatedBy'        => $staffId,
                    'CreatedDate'      => $now,
                    'UpdatedBy'        => $staffId,
                    'UpdatedDate'      => $now,
                ];
            }

            $purchaseOrderId = (int) PurchaseOrder::insertGetId([
                'PurchaseOrderCode'    => $data['PurchaseOrderCode'],
                'SupplierId'           => $data['SupplierId'] ?? null,
                'OrderDate'            => $now,
                'ExpectedDeliveryDate' => $data['ExpectedDeliveryDate'] ?? null,
                'TotalAmount'          => $totalAmount,
                'Status'               => 1,
                'Note'                 => $data['Note'] ?? null,
                'CreatedBy'            => $staffId,
                'CreatedDate'          => $now,
                'UpdatedBy'            => $staffId,
                'UpdatedDate'          => $now,
            ]);

            foreach ($detailRows as &$row) {
                $row['PurchaseOrderId'] = $purchaseOrderId;
            }
            unset($row);

            if (!empty($detailRows)) {
                PurchaseOrderDetail::insert($detailRows);
            }
            $createdIds[] = $purchaseOrderId;
        }

        if (!empty($orderRequestIds)) {
            OrderRequest::whereIn('OrderRequestId', $orderRequestIds)->update([
                'Status'      => 2,
                'UpdatedBy'   => $staffId,
                'UpdatedDate' => $now,
            ]);
        }
        return $createdIds;
    }

    public function updatePO($id, array $data, array $details)
    {
        $po = PurchaseOrder::query()->find($id);

        if (!$po || $po->Status !== 1) {
            return false;
        }

        $now        = Carbon::now();
        $staffId    = Auth::user()['StaffId'] ?? 0;
        $totalAmount = 0;
        $incomingIds = [];

        foreach ($details as $detail) {
            $quantity    = (int) $detail['Quantity'];
            $unitPrice   = (float) $detail['UnitPrice'];
            $amount      = $quantity * $unitPrice;
            $totalAmount += $amount;

            if (!empty($detail['PurchaseOrderDetailId'])) {
                PurchaseOrderDetail::query()
                    ->where('PurchaseOrderDetailId', $detail['PurchaseOrderDetailId'])
                    ->update([
                        'ProductId'     => $detail['ProductId'],
                        'UnitId'        => $detail['UnitId'] ?? null,
                        'Specification' => $detail['Specification'] ?? null,
                        'Quantity'      => $quantity,
                        'UnitPrice'     => $unitPrice,
                        'Amount'        => $amount,
                        'Note'          => $detail['Note'] ?? null,
                        'Status'        => 1,
                        'UpdatedBy'     => $staffId,
                        'UpdatedDate'   => $now,
                    ]);
                $incomingIds[] = $detail['PurchaseOrderDetailId'];
            } else {
                $newId = PurchaseOrderDetail::insertGetId([
                    'PurchaseOrderId'  => $id,
                    'ProductId'        => $detail['ProductId'],
                    'UnitId'           => $detail['UnitId'] ?? null,
                    'Specification'    => $detail['Specification'] ?? null,
                    'Quantity'         => $quantity,
                    'UnitPrice'        => $unitPrice,
                    'Amount'           => $amount,
                    'ReceivedQuantity' => 0,
                    'Note'             => $detail['Note'] ?? null,
                    'Status'           => 1,
                    'CreatedBy'        => $staffId,
                    'CreatedDate'      => $now,
                    'UpdatedBy'        => $staffId,
                    'UpdatedDate'      => $now,
                ]);
                $incomingIds[] = $newId;
            }
        }

        // Xóa mềm các dòng không có trong payload
        PurchaseOrderDetail::where('PurchaseOrderId', $id)
            ->where('Status', 1)
            ->whereNotIn('PurchaseOrderDetailId', $incomingIds)
            ->update([
                'Status'      => 0,
                'UpdatedBy'   => $staffId,
                'UpdatedDate' => $now,
            ]);

        PurchaseOrder::where('PurchaseOrderId', $id)->update([
            'OrderDate'            => $data['OrderDate'] ?? $po->OrderDate,
            'ExpectedDeliveryDate' => $data['ExpectedDeliveryDate'] ?? null,
            'Note'                 => $data['Note'] ?? null,
            'TotalAmount'          => $totalAmount,
            'UpdatedBy'            => $staffId,
            'UpdatedDate'          => $now,
        ]);

        return true;
    }

    public function send($id, $note)
    {
        $po = PurchaseOrder::query()->find($id);

        if (!$po || $po->Status !== 1) {
            return false;
        }

        $hasActiveDetail = PurchaseOrderDetail::query()
            ->where('PurchaseOrderId', $id)
            ->where('Status', 1)
            ->exists();

        if (!$hasActiveDetail) {
            return false;
        }

        return (bool) PurchaseOrder::query()->where('PurchaseOrderId', $id)->update([
            'Status'      => 2,
            'Note'        => $note ?? $po->Note,
            'UpdatedBy'   => Auth::user()['StaffId'] ?? 0,
            'UpdatedDate' => Carbon::now(),
        ]);
    }

    public function generateCode()
    {
        $prefix = 'PO-' . Carbon::now()->format('ym') . '-';

        $last = PurchaseOrder::where('PurchaseOrderCode', 'like', $prefix . '%')
            ->orderBy('PurchaseOrderCode', 'desc')
            ->value('PurchaseOrderCode');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
