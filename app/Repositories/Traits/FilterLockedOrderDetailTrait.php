<?php

namespace App\Repositories\Traits;

use App\OrderDetail;

trait FilterLockedOrderDetailTrait
{
    /**
     * Trả về danh sách OrderDetailId không bị khóa (TreatmentMedicalProcedureStatusId <= 3).
     * Dùng để ngăn đổ tiền hoặc đổi trạng thái vào dịch vụ đang bị khóa.
     */
    private function filterLockedOrderDetailIds(array $orderDetailIds): array
    {
        if (empty($orderDetailIds)) {
            return [];
        }
        return OrderDetail::leftJoin('TreatmentMedicalProcedure as tmp', function ($join) {
            $join->on('tmp.TreatmentMedicalProcedureId', '=', 'OrderDetail.TreatmentMedicalProcedureId')
                ->where('tmp.TreatmentMedicalProcedureStatusId', '<=', 3);
        })
            ->whereIn('OrderDetail.OrderDetailId', $orderDetailIds)
            ->pluck('OrderDetail.OrderDetailId')
            ->toArray();
    }

    /**
     * Lọc danh sách $orderDetails (array of arrays), loại bỏ các dịch vụ bị khóa.
     */
    private function filterLockedOrderDetails(array $orderDetails): array
    {
        if (empty($orderDetails)) {
            return $orderDetails;
        }

        $orderDetailIds = array_values(array_filter(array_column($orderDetails, 'OrderDetailId')));
        if (empty($orderDetailIds)) {
            return $orderDetails;
        }

        $validOrderDetailIds = OrderDetail::leftJoin('TreatmentMedicalProcedure as tmp', function ($join) {
            $join->on('tmp.TreatmentMedicalProcedureId', '=', 'OrderDetail.TreatmentMedicalProcedureId')
                ->where('tmp.TreatmentMedicalProcedureStatusId', '<=', 3);
        })
            ->whereIn('OrderDetail.OrderDetailId', $orderDetailIds)
            ->pluck('OrderDetail.OrderDetailId')
            ->toArray();

        $lockedOrderDetailIds = array_diff($orderDetailIds, $validOrderDetailIds);
        if ($lockedOrderDetailIds) {
            $lockedItems = array_filter($orderDetails, function ($item) use ($lockedOrderDetailIds) {
                return in_array($item['OrderDetailId'] ?? null, $lockedOrderDetailIds);
            });
            \Log::warning('Loại dịch vụ tạm khóa', [
                'locked_ids'   => array_values($lockedOrderDetailIds),
                'locked_items' => array_values($lockedItems),
            ]);
        }

        return array_values(
            array_filter($orderDetails, function ($item) use ($validOrderDetailIds) {
                return in_array($item['OrderDetailId'] ?? null, $validOrderDetailIds);
            })
        );
    }
}
