<?php

namespace App\Repositories;

use App\OutboutRequest;
use App\OutboutRequestDetail;
use App\DepartmentDemand;
use App\DepartmentDemandLog;
use App\OrderRequest;
use App\OrderRequestDetail;
use App\ProductSkuAttribute;
use App\ProductAttributeType;
use App\ProductAttributeValue;
use Carbon\Carbon;
use App\Repositories\Abstracts\EloquentRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OutboutRequestRepository extends EloquentRepository
{
    protected function getModel()
    {
        return OutboutRequest::class;
    }

    public function search(array $conditions = [])
    {
        $query = OutboutRequest::query();

        if (!empty($conditions['RelatedId'])) {
            $query->where('RelatedId', $conditions['RelatedId']);
        }

        if (isset($conditions['Status'])) {
            $query->where('Status', $conditions['Status']);
        }

        $limit   = min((int) ($conditions['limit'] ?? 20), 100);
        $lmstart = max((int) ($conditions['lmstart'] ?? 0), 0);

        $query->orderByDesc('OutboutRequestId');
        $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);

        return $result;
    }

    public function getDetail($id)
    {
        return OutboutRequest::query()
            ->with(['details.product', 'details.unit', 'orderRequest'])
            ->find($id);
    }

    protected $allowedColumns = [
        'OutboutCode', 'PurchaseOrderId', 'DepartmentType', 'DepartmentId', 'RelatedType', 'RelatedId',
        'ActualArrivalDate', 'TotalSKU', 'IRCode', 'Status',
        'ExpectedReceiptDate', 'DeliveryStaff', 'DeliveryDate', 'Note',
        'CreatedBy', 'CreatedDate',
    ];

    public function store(array $data, array $details)
    {
        $now              = Carbon::now();
        $filtered         = array_intersect_key($data, array_flip($this->allowedColumns));
        $outboutRequestId = (int) OutboutRequest::insertGetId($filtered);

        $rows = [];
        foreach ($details as $d) {
            $rows[] = array_merge($d, [
                'OutboutRequestId' => $outboutRequestId,
                'CreatedBy'        => $data['CreatedBy'],
                'CreatedDate'      => $now,
            ]);
        }

        OutboutRequestDetail::insert($rows);

        return $outboutRequestId;
    }

    public function confirmByOrderRequest($orderRequestId, $staffId, $now)
    {
        return (bool) OutboutRequest::where('RelatedId', $orderRequestId)
            ->where('RelatedType', 1)
            ->where('Status', 1)
            ->update(['Status' => 2]);
    }

    public function confirm($id, array $data)
    {
        return (bool) OutboutRequest::query()->where('OutboutRequestId', $id)->update(array_merge($data, [
            'Status' => 2,
        ]));
    }

    public function generateCode()
    {
        $prefix = 'GH-' . Carbon::now()->format('ym') . '-';

        $last = OutboutRequest::query()
            ->where('OutboutCode', 'like', $prefix . '%')
            ->orderBy('OutboutCode', 'desc')
            ->value('OutboutCode');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Lấy danh sách phiếu giao hàng
     * 
     * @param array $conditions
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getDeliveryList(array $conditions = [])
    {
        $query = OutboutRequest::query()
            ->with(['details:OutboutRequestId,OutboutRequestDetailId'])
            ->select(
                'OutboutRequestId',
                'OutboutCode',
                'DepartmentId',
                'DeliveryDate',
                'ExpectedReceiptDate',
                'DeliveryStaff',
                'Status',
                'TotalSKU',
                'CreatedDate'
            );

        // Filter theo DepartmentId (Phòng khám)
        if (!empty($conditions['DepartmentId'])) {
            $query->whereIn('DepartmentId', $conditions['DepartmentId']);
        }

        // Filter theo Status (Trạng thái)
        if (isset($conditions['Status']) && $conditions['Status'] !== '') {
            $query->where('Status', $conditions['Status']);
        }

        // Filter theo Keyword (Tìm theo mã phiếu xuất)
        if (!empty($conditions['Keyword'])) {
            $query->where('OutboutCode', 'like', '%' . $conditions['Keyword'] . '%');
        }

        // Pagination
        $limit = min((int) ($conditions['limit'] ?? 20), 100);
        $lmstart = max((int) ($conditions['lmstart'] ?? 0), 0);
        
        $query->orderByDesc('OutboutRequestId');
        
        $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);

        // Load Branch info cho mỗi record
        foreach ($result as $item) {
            if ($item->DepartmentId) {
                $branch = \App\Branch::where('BranchId', $item->DepartmentId)
                    ->select('BranchId', 'BranchCode', 'Name')
                    ->first();
                $item->Branch = $branch;
            }
        }

        return $result;
    }

    /**
     * Lấy chi tiết phiếu giao hàng
     * 
     * @param int $id
     * @return OutboutRequest|null
     */
    public function getDeliveryDetail($id)
    {
        $outboutRequest = OutboutRequest::query()
            ->with([
                'details.product:ProductId,SKU,Name,Specification,BaseName,ProductBrandId',
                'details.product.brand:ProductBrandId,Name',
                'details.unit:UnitId,Code,Name'
            ])
            ->find($id);

        if (!$outboutRequest) {
            return null;
        }

        // Load Branch info
        if ($outboutRequest->DepartmentId) {
            $branch = \App\Branch::where('BranchId', $outboutRequest->DepartmentId)
                ->select('BranchId', 'BranchCode', 'Name')
                ->first();
            $outboutRequest->Branch = $branch;
        }

        // Lấy ProductAttributeType và ProductAttributeValue cho các sản phẩm
        $productIds = $outboutRequest->details->pluck('ProductId')->unique()->toArray();
        
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

            // Gắn attributes vào từng product trong details
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

        return $outboutRequest;
    }

    /**
     * Xác nhận phiếu giao hàng
     * 
     * @param int $id
     * @param array $details [{OutboutRequestDetailId, ActualQty}]
     * @param int $staffId
     * @return bool
     */
    public function confirmDelivery($id, $branchNote, array $details, $staffId)
    {
        DB::beginTransaction();
        try {
            $outboutRequest = OutboutRequest::find($id);

            if (!$outboutRequest) {
                throw new \Exception('Không tìm thấy phiếu giao hàng');
            }

            if ($outboutRequest->Status == 2) {
                throw new \Exception('Phiếu giao hàng đã được xác nhận');
            }

            $now = Carbon::now();
            $departmentId = $outboutRequest->DepartmentId;

            // Cập nhật ActualQty cho từng OutboutRequestDetail
            foreach ($details as $detail) {
                $detailId = $detail['OutboutRequestDetailId'] ?? 0;
                $actualQty = (int) ($detail['ActualQty'] ?? 0);

                if ($detailId > 0 && $actualQty >= 0) {
                    OutboutRequestDetail::where('OutboutRequestDetailId', $detailId)
                        ->where('OutboutRequestId', $id)
                        ->update([
                            'ActualQty' => $actualQty,
                        ]);
                }
            }

            // Cập nhật Status của OutboutRequest thành 2 (Hoàn thành)
            $outboutRequest->Status = 2;
            $outboutRequest->ActualArrivalDate = $now;
            $outboutRequest->BranchNote = $branchNote;
            $outboutRequest->save();

            // Lấy tất cả OutboutRequestDetail đã cập nhật
            $outboutDetails = OutboutRequestDetail::where('OutboutRequestId', $id)->get();

            // Xử lý từng sản phẩm
            foreach ($outboutDetails as $outboutDetail) {
                $productId = $outboutDetail->ProductId;
                $unitId = $outboutDetail->UnitId;
                $actualQty = $outboutDetail->ActualQty;

                if ($actualQty <= 0) {
                    continue; // Skip nếu không nhận được hàng
                }

                // 1. Cập nhật DepartmentDemand
                $demand = DepartmentDemand::where('DepartmentId', $departmentId)
                    ->where('ProductId', $productId)
                    ->where('UnitId', $unitId)
                    ->first();

                if ($demand) {
                    $deliveredQtyBefore = $demand->TotalDeliveredQty;
                    $pendingQtyBefore = $demand->PendingQty;
                    
                    // Tăng TotalDeliveredQty
                    $demand->TotalDeliveredQty += $actualQty;
                    
                    // KHÔNG trừ PendingQty ở đây vì đã trừ khi tạo phiếu giao (createDeliveryFromDemand)
                    // PendingQty chỉ trừ 1 lần duy nhất khi tạo phiếu giao
                    
                    $demand->UpdatedDate = $now;
                    $demand->save();

                    // Insert DepartmentDemandLog
                    DepartmentDemandLog::create([
                        'DepartmentDemandId' => $demand->DepartmentDemandId,
                        'DepartmentId'       => $departmentId,
                        'ProductId'          => $productId,
                        'UnitId'             => $unitId,
                        'ChangeType'         => 'Confirmed',
                        'ChangeQty'          => $actualQty,
                        'QtyBefore'          => $deliveredQtyBefore,
                        'QtyAfter'           => $deliveredQtyBefore + $actualQty,
                        'RefType'            => 'DeliveryNoteItem',
                        'RefId'              => $id,
                        'Note'               => 'Xác nhận nhận hàng: ' . $outboutRequest->OutboutCode . ' | PendingQty không đổi: ' . $pendingQtyBefore,
                        'CreatedDate'        => $now,
                        'CreatedBy'          => $staffId,
                    ]);
                }

                // 2. Phân bổ số lượng vào các OrderRequest của phòng khám (từ cũ đến mới)
                $remainingQty = $actualQty;

                // Lấy các OrderRequest của phòng khám, chưa hoàn thành, sắp xếp từ cũ đến mới
                $orderRequests = OrderRequest::where('DepartmentId', $departmentId)
                    ->whereIn('Status', [2, 4, 10]) // 10: Đã tiếp nhận, 2: Đang xử lý, 4: Đang vận chuyển
                    ->orderBy('OrderRequestId', 'ASC') // Từ cũ đến mới
                    ->get();

                foreach ($orderRequests as $orderRequest) {
                    if ($remainingQty <= 0) {
                        break; // Đã phân bổ hết
                    }

                    // Tìm OrderRequestDetail tương ứng với sản phẩm này
                    $orderRequestDetail = OrderRequestDetail::where('OrderRequestId', $orderRequest->OrderRequestId)
                        ->where('ProductId', $productId)
                        ->where('UnitId', $unitId)
                        ->first();

                    if (!$orderRequestDetail) {
                        continue; // OrderRequest này không có sản phẩm này
                    }

                    // Tính số lượng còn thiếu
                    $neededQty = $orderRequestDetail->RequestQuantity - $orderRequestDetail->ReceivedQuantity;

                    if ($neededQty <= 0) {
                        continue; // Sản phẩm này đã nhận đủ
                    }

                    // Phân bổ số lượng
                    $allocatedQty = min($remainingQty, $neededQty);

                    // Cập nhật ReceivedQuantity
                    $orderRequestDetail->ReceivedQuantity += $allocatedQty;
                    $orderRequestDetail->UpdatedDate = $now;
                    $orderRequestDetail->save();

                    $remainingQty -= $allocatedQty;

                    // Kiểm tra xem OrderRequest này đã nhận đủ tất cả sản phẩm chưa
                    $allDetailsOfOrder = OrderRequestDetail::where('OrderRequestId', $orderRequest->OrderRequestId)->get();
                    $allCompleted = true;
                    foreach ($allDetailsOfOrder as $d) {
                        if ($d->ReceivedQuantity < $d->RequestQuantity) {
                            $allCompleted = false;
                            break;
                        }
                    }

                    // Cập nhật Status của OrderRequest nếu đã nhận đủ
                    if ($allCompleted) {
                        $orderRequest->Status = 5; // Hoàn thành
                        $orderRequest->UpdatedDate = $now;
                        $orderRequest->save();
                    } else {
                        // Nếu chưa đủ nhưng đã có hàng về thì chuyển sang Đang vận chuyển
                        if ($orderRequest->Status == 10 || $orderRequest->Status == 2) {
                            $orderRequest->Status = 4; // Đang vận chuyển
                            $orderRequest->UpdatedDate = $now;
                            $orderRequest->save();
                        }
                    }
                }
            }

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('OutboutRequestRepository@confirmDelivery: ' . $e->getMessage());
            throw $e;
        }
    }
}
