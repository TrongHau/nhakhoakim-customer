<?php

namespace App\Repositories;

use App\Supplier;
use App\ProductSupplierMapping;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Repositories\Abstracts\EloquentRepository;

class SupplierRepository extends EloquentRepository
{
    protected function getModel()
    {
        return Supplier::class;
    }

    public function search(array $conditions = [])
    {
        $keyword = (string) ($conditions['Keyword'] ?? '');
        $status  = isset($conditions['Status']) ? (int) $conditions['Status'] : -1;
        $limit   = min((int) ($conditions['limit'] ?? 20), 100);
        $lmstart = max((int) ($conditions['lmstart'] ?? 0), 0);

        $query = Supplier::query()->where('Status', '!=', -1);

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('Name', 'like', "%{$keyword}%")
                  ->orWhere('SupplierCode', 'like', "%{$keyword}%")
                  ->orWhere('Phone', 'like', "%{$keyword}%")
                  ->orWhere('TaxCode', 'like', "%{$keyword}%");
            });
        }

        if ($status !== -1) {
            $query->where('Status', $status);
        }
        $query->orderByDesc('Status');
        $query->orderByDesc('SupplierId');
        
        $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);

        return $result;
    }

    public function isSupplierCodeExists($code, $excludeId = null)
    {
        $query = Supplier::where('SupplierCode', $code)->where('Status', '!=', -1);
        if ($excludeId) {
            $query->where('SupplierId', '!=', $excludeId);
        }
        return $query->exists();
    }

    public function isSupplierTaxCodeExists($code, $excludeId = null)
    {
        $query = Supplier::where('TaxCode', $code)->where('Status', '!=', -1);
        if ($excludeId) {
            $query->where('SupplierId', '!=', $excludeId);
        }
        return $query->exists();
    }

    public function generateSupplierCode()
    {
        $last = Supplier::query()
            ->where('SupplierCode', 'like', 'NCC%')
            ->orderByDesc('SupplierCode')
            ->value('SupplierCode');

        $next = $last ? ((int) substr($last, 3)) + 1 : 1;

        return 'NCC' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    public function toggleState($id)
    {
        $supplier = Supplier::query()->where('SupplierId', $id)->first();

        if (!$supplier) {
            return false;
        }

        return (bool) Supplier::query()
            ->where('SupplierId', $id)
            ->update([
                'Status'      => $supplier->Status === 1 ? 0 : 1,
                'UpdatedBy'   => Auth::user()['StaffId'] ?? 0,
                'UpdatedDate' => Carbon::now(),
            ]);
    }

    protected $allowedColumns = [
        'SupplierCode', 'Name', 'TaxCode', 'Phone', 'Email',
        'Address', 'Note', 'Status', 'CreatedBy', 'CreatedDate',
        'UpdatedBy', 'UpdatedDate',
    ];

    public function insertGetId(array $data)
    {
        $filtered = array_intersect_key($data, array_flip($this->allowedColumns));
        return (int) Supplier::query()->insertGetId($filtered);
    }

    public function updateById($id, array $data)
    {
        $filtered = array_intersect_key($data, array_flip($this->allowedColumns));
        return Supplier::query()
            ->where('SupplierId', $id)
            ->update($filtered);
    }

    public function listSupplierByProduct($productId)
    {
        $data = ProductSupplierMapping::where('ProductId', $productId)->where('Status', 1)->orderBy('Priority')->get()->toArray();

        return $data ?? [];
    }
}
