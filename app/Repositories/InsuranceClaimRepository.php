<?php

namespace App\Repositories;

use App\InsuranceClaim;
use App\InsuranceClaimHistory;
use App\Repositories\Abstracts\EloquentRepository;
use Carbon\Carbon;

class InsuranceClaimRepository extends EloquentRepository
{
    protected function getModel()
    {
        return InsuranceClaim::class;
    }

    public function list(array $filters, int $page, int $pageSize)
    {
        return InsuranceClaim::with('request')
            ->when($filters['status'] ?? null,   function ($q, $v) { return $q->where('UnifiedStatus', $v); })
            ->when($filters['fromDate'] ?? null,  function ($q, $v) { return $q->whereDate('CreatedAt', '>=', $v); })
            ->when($filters['toDate'] ?? null,    function ($q, $v) { return $q->whereDate('CreatedAt', '<=', $v); })
            ->orderByDesc('Id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    public function findWithRelations(int $id)
    {
        return InsuranceClaim::with(['request', 'histories'])->find($id);
    }

    public function store(array $data): InsuranceClaim
    {
        return InsuranceClaim::create($data);
    }

    public function updateStatus(InsuranceClaim $claim, string $unifiedStatus, array $extra = []): void
    {
        $claim->UnifiedStatus = $unifiedStatus;

        foreach ($extra as $col => $val) {
            $claim->{$col} = $val;
        }

        $claim->save();
    }

    public function addHistory(int $claimId, string $unifiedStatus, int $staffId, string $note): void
    {
        InsuranceClaimHistory::create([
            'InsuranceClaimId' => $claimId,
            'UnifiedStatus'    => $unifiedStatus,
            'ChangedBy'        => $staffId,
            'Note'             => $note,
            'ChangedAt'        => Carbon::now()->toDateTimeString(),
        ]);
    }
}
