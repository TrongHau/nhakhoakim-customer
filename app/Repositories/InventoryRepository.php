<?php

namespace App\Repositories;

use App\Inventory;
use App\InventoryTransaction;
use App\Warehouse;
use App\ProductSkuAttribute;
use App\ProductAttributeType;
use App\ProductAttributeValue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Repositories\Abstracts\EloquentRepository;

class InventoryRepository extends EloquentRepository
{
    protected function getModel()
    {
        return Inventory::class;
    }

    public function search(array $conditions = [])
    {
        $query = Inventory::query()
            ->join('Product as p', 'p.ProductId', '=', 'Inventory.ProductId')
            ->join('ProductBrand as pb', 'pb.ProductBrandId', '=', 'p.ProductBrandId')
            ->join('Warehouse as w', 'w.WarehouseId', '=', 'Inventory.WarehouseId')
            ->leftJoin('ProductCategory as pc', 'pc.ProductCategoryId', '=', 'p.ProductCategoryId')
            ->leftJoin('inventory.Unit as u', 'u.UnitId', '=', 'p.UnitId')
            ->select(
                'Inventory.*',
                'p.Name as ProductName',
                'p.SKU',
                'p.Price as UnitPrice',
                'p.BaseName',
                'pb.ProductBrandId',
                'pb.Name as ProductBrandName',
                'pc.Name as ProductCategoryName',
                'u.Name as UnitName',
                'w.Name as WarehouseName'
            );

        if (!empty($conditions['Keyword'])) {
            $kw = $conditions['Keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('p.Name', 'like', "%{$kw}%")
                  ->orWhere('p.SKU', 'like', "%{$kw}%");
            });
        }

        if (!empty($conditions['WarehouseId'])) {
            $query->where('Inventory.WarehouseId', $conditions['WarehouseId']);
        }

        if (!empty($conditions['ProductCategoryId'])) {
            $query->where('p.ProductCategoryId', $conditions['ProductCategoryId']);
        }

        if (!empty($conditions['FromDate'])) {
            $query->whereDate(DB::raw('COALESCE(Inventory.UpdatedDate, Inventory.CreatedDate)'), '>=', $conditions['FromDate']);
        }

        if (!empty($conditions['ToDate'])) {
            $query->whereDate(DB::raw('COALESCE(Inventory.UpdatedDate, Inventory.CreatedDate)'), '<=', $conditions['ToDate']);
        }

        $limit   = min((int) ($conditions['limit'] ?? 20), 100);
        $lmstart = max((int) ($conditions['lmstart'] ?? 0), 0);

        $query->orderByDesc('Inventory.InventoryId');

        $result = $query->paginate($limit,['*'],'page', round((int) $lmstart/ (int) $limit) + 1);

        // Lấy ProductAttributeType và ProductAttributeValue cho các sản phẩm
        $productIds = $result->pluck('ProductId')->unique()->toArray();
        
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

            // Gắn attributes vào từng inventory item
            foreach ($result->items() as $item) {
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

        return $result;
    }

    public function getSummary(array $conditions = [])
    {
        $base = Inventory::query();

        if (!empty($conditions['FromDate'])) {
            $base->whereDate(DB::raw('COALESCE(UpdatedDate, CreatedDate)'), '>=', $conditions['FromDate']);
        }

        if (!empty($conditions['ToDate'])) {
            $base->whereDate(DB::raw('COALESCE(UpdatedDate, CreatedDate)'), '<=', $conditions['ToDate']);
        }

        return (object) [
            'TotalProduct'          => (clone $base)->distinct()->count('ProductId'),
            'CentralWarehouseStock' => (clone $base)->where('WarehouseId', function ($q) {
                $q->select('WarehouseId')->from('Warehouse')->where('WarehouseType', 1)->limit(1);
            })->sum('Quantity'),
            'TotalValue'            => (clone $base)->sum('TotalValue'),
            'LowStockCount'         => (clone $base)->whereNotNull('MinStock')->whereColumn('Quantity', '<=', 'MinStock')->where('Quantity', '>', 0)->count(),
            'OutStockCount'         => (clone $base)->whereNotNull('MinStock')->where('Quantity', 0)->count(),
        ];
    }

    public function getHistory($inventoryId, array $conditions = [])
    {
        $query = InventoryTransaction::query()
            ->where('InventoryId', $inventoryId);

        $limit   = min((int) ($conditions['limit'] ?? 20), 100);
        $lmstart = max((int) ($conditions['lmstart'] ?? 0), 0);

        $query->orderByDesc('InventoryTransactionId');
        $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);

        // Lấy thông tin InboutRequest và OutboutRequest theo ReferenceType
        $inboutIds  = [];
        $outboutIds = [];

        foreach ($result->items() as $item) {
            if ($item->ReferenceType === 'InboutRequest' && $item->ReferenceId) {
                $inboutIds[] = $item->ReferenceId;
            } elseif ($item->ReferenceType === 'OutboutRequest' && $item->ReferenceId) {
                $outboutIds[] = $item->ReferenceId;
            }
        }

        $inboutMap  = [];
        $outboutMap = [];

        if (!empty($inboutIds)) {
            $inbouts = \App\InboutRequest::whereIn('InboutRequestId', array_unique($inboutIds))
                ->select('InboutRequestId', 'IRCode', 'CreatedDate', 'Status', 'Note')
                ->get()
                ->keyBy('InboutRequestId');
            foreach ($inbouts as $id => $inbout) {
                $inboutMap[$id] = $inbout;
            }
        }

        if (!empty($outboutIds)) {
            $outbouts = \App\OutboutRequest::whereIn('OutboutRequestId', array_unique($outboutIds))
                ->select('OutboutRequestId', 'OutboutCode', 'IRCode', 'CreatedDate', 'Status', 'Note', 'BranchNote')
                ->get()
                ->keyBy('OutboutRequestId');
            foreach ($outbouts as $id => $outbout) {
                $outboutMap[$id] = $outbout;
            }
        }

        foreach ($result->items() as $item) {
            if ($item->ReferenceType === 'InboutRequest' && isset($inboutMap[$item->ReferenceId])) {
                $item->ReferenceInfo = $inboutMap[$item->ReferenceId];
            } elseif ($item->ReferenceType === 'OutboutRequest' && isset($outboutMap[$item->ReferenceId])) {
                $item->ReferenceInfo = $outboutMap[$item->ReferenceId];
            } else {
                $item->ReferenceInfo = null;
            }
        }

        // Lấy ProductId từ các transactions
        $productIds = $result->pluck('ProductId')->unique()->filter()->toArray();
        
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

            // Gắn attributes vào từng transaction item
            foreach ($result->items() as $item) {
                $productAttributes = [];
                
                if ($item->ProductId && isset($skuAttributes[$item->ProductId])) {
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

        return $result;
    }

    public function updateStock($productId, $warehouseId, $quantity, $transactionType, $referenceType, $referenceId, $note, $userId)
    {
        $now       = Carbon::now();
        $inventory = Inventory::query()
            ->where('ProductId', $productId)
            ->where('WarehouseId', $warehouseId)
            ->first();

        if (!$inventory) {
            $inventoryId = (int) Inventory::insertGetId([
                'ProductId'   => $productId,
                'WarehouseId' => $warehouseId,
                'Quantity'    => 0,
                'MinStock'    => 0,
                'Status'      => 1,
                'CreatedBy'   => $userId,
                'CreatedDate' => $now,
            ]);
        } else {
            $inventoryId = $inventory->InventoryId;
        }

        $delta = $transactionType === 2 ? -abs($quantity) : abs($quantity);

        Inventory::query()->where('InventoryId', $inventoryId)->update([
            'Quantity'    => DB::raw("Quantity + {$delta}"),
            'UpdatedBy'   => $userId,
            'UpdatedDate' => $now,
        ]);

        InventoryTransaction::insert([
            'InventoryId'     => $inventoryId,
            'TransactionType' => $transactionType,
            'ProductId'       => $productId,
            'Quantity'        => $delta,
            'ReferenceType'   => $referenceType,
            'ReferenceId'     => $referenceId,
            'Note'            => $note,
            'CreatedBy'       => $userId,
            'CreatedDate'     => $now,
        ]);
    }
}
