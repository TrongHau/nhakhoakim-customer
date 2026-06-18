<?php

namespace App\Repositories;

use App\InsuranceHub\EnumStatus;
use App\InsuranceRequest;
use App\InsuranceRequestHistory;
use App\Repositories\Abstracts\EloquentRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InsuranceRequestRepository extends EloquentRepository
{
    protected function getModel()
    {
        return InsuranceRequest::class;
    }

    public function list(array $filters, int $page, int $pageSize)
    {
        $paginator = $this->buildBaseQuery($filters)
            ->when($filters['status'] ?? null, function ($q, $v) {
                return $q->whereIn('UnifiedStatus', self::statusGroup($v));
            })
            ->orderByDesc('InsuranceRequestsId')
            ->paginate($pageSize, ['*'], 'page', $page);

        $paginator->getCollection()->transform(function ($item) {
            $item->ProviderStatus = EnumStatus::label($item->UnifiedStatus ?? '');
            return $item->makeHidden(['Payload', 'Treatments']);
        });

        return $paginator;
    }

    public function summary(array $filters): array
    {
        $rawCounts = $this->applyFilters(InsuranceRequest::query(), $filters)
            ->selectRaw('UnifiedStatus, COUNT(*) as cnt')
            ->groupBy('UnifiedStatus')
            ->pluck('cnt', 'UnifiedStatus')
            ->toArray();

        $sum = function (array $statuses) use ($rawCounts): int {
            return (int) array_sum(array_intersect_key($rawCounts, array_flip($statuses)));
        };

        return [
            'total'       => array_sum($rawCounts),
            'inReview'    => $sum([EnumStatus::SUBMITTED, EnumStatus::IN_REVIEW,
                                   EnumStatus::SUPPLEMENTED,
                                   EnumStatus::CLAIM_WAITING, EnumStatus::CLAIM_SENT,
                                   EnumStatus::CLAIM_RECEIVED, EnumStatus::CLAIM_SUPPLEMENTED,
                                   EnumStatus::PENDING_PAYMENT]),
            'pendingInfo' => $sum([EnumStatus::PENDING_INFO, EnumStatus::CLAIM_PENDING_INFO]),
            'approved'    => $sum([EnumStatus::APPROVED, EnumStatus::PAID]),
            'rejected'    => $sum([EnumStatus::REJECTED, EnumStatus::CLAIM_REJECTED,
                                   EnumStatus::CANCELLED, EnumStatus::CLAIM_CANCELLED]),
        ];
    }

    public function findWithRelations(int $id)
    {
        return InsuranceRequest::with(['histories', 'insuranceCompany', 'partnerCompany'])->find($id);
    }

    public function store(array $data): InsuranceRequest
    {
        $serviceIds = $data['ServiceIds'] ?? [];
        unset($data['ServiceIds']);

        return DB::transaction(function () use ($data, $serviceIds) {
            $insuranceRequest = InsuranceRequest::create($data);

            if (!empty($serviceIds)) {
                $rows = array_map(function ($serviceId) use ($insuranceRequest) {
                    return [
                        'InsuranceRequestId' => $insuranceRequest->InsuranceRequestsId,
                        'ServiceId'          => (int) $serviceId,
                    ];
                }, $serviceIds);

                DB::table('InsuranceRequestServices')->insert($rows);
            }

            return $insuranceRequest;
        });
    }

    public function updateStatus(InsuranceRequest $insuranceRequest, string $unifiedStatus, array $extra = []): void
    {
        $insuranceRequest->UnifiedStatus = $unifiedStatus;

        foreach ($extra as $col => $val) {
            $insuranceRequest->{$col} = $val;
        }

        $insuranceRequest->save();
    }

    public function addHistory(int $requestId, string $unifiedStatus, int $staffId, string $note): void
    {
        InsuranceRequestHistory::create([
            'InsuranceRequestId' => $requestId,
            'UnifiedStatus'      => $unifiedStatus,
            'ChangedBy'          => $staffId,
            'Note'               => $note,
            'ChangedAt'          => Carbon::now()->toDateTimeString(),
        ]);
    }

    public function historyOf(int $requestId)
    {
        return InsuranceRequestHistory::where('InsuranceRequestId', $requestId)
            ->orderByDesc('ChangedAt')
            ->get();
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function buildBaseQuery(array $filters)
    {
        return $this->applyFilters(
            InsuranceRequest::select('InsuranceRequests.*', 'pc.Name as CompanyName', 'pc.ShortName as CompanyShortName', 'b.BranchCode')
                ->leftJoin('PartnerCompany as pc', 'pc.PartnerCompanyId', '=', 'InsuranceRequests.CompanyId')
                ->leftJoin('in.Branch as b', 'b.BranchId', '=', 'InsuranceRequests.BranchId'),
            $filters
        );
    }

    private function applyFilters($query, array $filters)
    {
        return $query
            ->when($filters['memberCode'] ?? null, function ($q, $v) {
                return $q->where('InsuranceRequests.MemberCode', $v);
            })
            ->when($filters['companyId'] ?? null, function ($q, $v) { return $q->where('InsuranceRequests.CompanyId', (int) $v); })
            ->when($filters['BranchId'] ?? null,   function ($q, $v) { return $q->where('InsuranceRequests.BranchId', (int) $v); })
            ->when($filters['fromDate'] ?? null,  function ($q, $v) { return $q->whereDate('InsuranceRequests.CreatedDate', '>=', $v); })
            ->when($filters['toDate'] ?? null,    function ($q, $v) { return $q->whereDate('InsuranceRequests.CreatedDate', '<=', $v); });
    }

    private static function statusGroup(string $group): array
    {
        $map = [
            'inReview'    => [EnumStatus::SUBMITTED, EnumStatus::IN_REVIEW,
                              EnumStatus::SUPPLEMENTED,  EnumStatus::ADJUSTING,
                              EnumStatus::CLAIM_WAITING, EnumStatus::CLAIM_SENT,
                              EnumStatus::CLAIM_RECEIVED, EnumStatus::CLAIM_SUPPLEMENTED,
                              EnumStatus::PENDING_PAYMENT],
            'pendingInfo' => [EnumStatus::PENDING_INFO, EnumStatus::CLAIM_PENDING_INFO],
            'approved'    => [EnumStatus::APPROVED, EnumStatus::PAID],
            'rejected'    => [EnumStatus::REJECTED, EnumStatus::CLAIM_REJECTED,
                              EnumStatus::CANCELLED, EnumStatus::CLAIM_CANCELLED],
        ];

        return $map[$group] ?? [];
    }
}
