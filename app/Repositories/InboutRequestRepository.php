<?php

namespace App\Repositories;

use App\Inventory;
use App\InboutRequest;
use App\InboutRequestDetail;
use App\InventoryTransaction;
use App\PurchaseOrder;
use App\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Repositories\Abstracts\EloquentRepository;

class InboutRequestRepository extends EloquentRepository
{
    protected function getModel()
    {
        return InboutRequest::class;
    }

    public function search(array $conditions = [])
    {
        $query = InboutRequest::query()->with('purchaseOrder.supplier');

        if (!empty($conditions['PurchaseOrderId'])) {
            $query->where('PurchaseOrderId', $conditions['PurchaseOrderId']);
        }

        if (isset($conditions['Status'])) {
            $query->where('Status', $conditions['Status']);
        }

        if (!empty($conditions['FromDate'])) {
            $query->where('CreatedDate', '>=', $conditions['FromDate']);
        }

        if (!empty($conditions['ToDate'])) {
            $query->where('CreatedDate', '<=', $conditions['ToDate'] . ' 23:59:59');
        }

        $limit   = min((int) ($conditions['limit'] ?? 20), 100);
        $lmstart = max((int) ($conditions['lmstart'] ?? 0), 0);

        $query->orderByDesc('InboutRequestId');
        $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);

        return $result;
    }

    public function getDetail($id)
    {
        return InboutRequest::query()
            ->with(['purchaseOrder.supplier', 'details.product', 'details.unit'])
            ->find($id);
    }

    protected $allowedColumns = [
        'PurchaseOrderId', 'RelatedType', 'RelatedId', 'Note', 'ActualArrivalDate',
        'IRCode', 'Status', 'RefOutboutRequestId', 'TotalSKU',
        'CreatedBy', 'CreatedDate',
    ];

    public function store(array $data, array $details)
    {
        $now             = Carbon::now();
        $filtered        = array_intersect_key($data, array_flip($this->allowedColumns));
        $inboutRequestId = (int) InboutRequest::insertGetId($filtered);

        $rows = [];
        foreach ($details as $d) {
            $rows[] = array_merge($d, [
                'InboutRequestId' => $inboutRequestId,
                'ExceptionQty'    => ($d['ActualQty'] ?? 0) - ($d['ExpectedQty'] ?? 0),
                'CreatedBy'       => $data['CreatedBy'],
                'CreatedDate'     => $now,
            ]);
        }

        InboutRequestDetail::insert($rows);

        // Lấy kho trung tâm (WarehouseType = 1)
        $centralWarehouse = Warehouse::query()
            ->where('WarehouseType', 1)
            ->where('Status', 1)
            ->first();

        if ($centralWarehouse) {
            foreach ($details as $detail) {
                $productId   = $detail['ProductId'];
                $actualQty   = (int) ($detail['ActualQty'] ?? 0);
                $warehouseId = $centralWarehouse->WarehouseId;

                // Upsert Inventory
                $inventory = Inventory::query()
                    ->where('ProductId', $productId)
                    ->where('WarehouseId', $warehouseId)
                    ->first();

                if ($inventory) {
                    Inventory::query()
                        ->where('InventoryId', $inventory->InventoryId)
                        ->update([
                            'Quantity'    => DB::raw("Quantity + {$actualQty}"),
                            'UpdatedBy'   => $data['CreatedBy'],
                            'UpdatedDate' => $now,
                        ]);
                    $inventoryId = $inventory->InventoryId;
                } else {
                    $inventoryId = (int) Inventory::insertGetId([
                        'ProductId'   => $productId,
                        'WarehouseId' => $warehouseId,
                        'Quantity'    => $actualQty,
                        'MinStock'    => 0,
                        'Status'      => 1,
                        'CreatedBy'   => $data['CreatedBy'],
                        'CreatedDate' => $now,
                        'UpdatedBy'   => $data['CreatedBy'],
                        'UpdatedDate' => $now,
                    ]);
                }

                // Ghi InventoryTransaction (TransactionType = 1: Nhập kho)
                InventoryTransaction::insert([
                    'InventoryId'     => $inventoryId,
                    'TransactionType' => 1,
                    'ProductId'       => $productId,
                    'UnitId'          => $detail['UnitId'] ?? null,
                    'Quantity'        => $actualQty,
                    'ReferenceType'   => 'InboutRequest',
                    'ReferenceId'     => $inboutRequestId,
                    'Note'            => $detail['Note'] ?? null,
                    'CreatedBy'       => $data['CreatedBy'],
                    'CreatedDate'     => $now,
                ]);
            }
        }

        // Cập nhật Status PO = 3 (Đã nhận một phần)
        if (!empty($data['PurchaseOrderId'])) {
            PurchaseOrder::query()
                ->where('PurchaseOrderId', $data['PurchaseOrderId'])
                ->whereIn('Status', [2, 3])
                ->update([
                    'Status'      => 3,
                    'UpdatedBy'   => $data['CreatedBy'],
                    'UpdatedDate' => $now,
                ]);
        }

        return $inboutRequestId;
    }

    public function generateCode()
    {
        $prefix = 'NK-' . Carbon::now()->format('ym') . '-';

        $last = InboutRequest::query()
            ->where('IRCode', 'like', $prefix . '%')
            ->orderBy('IRCode', 'desc')
            ->value('IRCode');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
