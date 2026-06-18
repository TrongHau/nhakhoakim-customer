<?php

namespace App\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Abstracts\EloquentRepository;
use Illuminate\Support\Facades\DB;
use App\OrderDetail;
use Carbon\Carbon;

class OrthodonticRepository extends EloquentRepository
{
    /**
    * @return string
    */
    protected function getModel(): string
    {
        //Required model
        return OrderDetail::class;
    }

    public function getClinicManagerSummary(array $params)
    {
        return [
            'KpiSummary'    => $this->queryKpiFromSnapshot($params, 'clinic_manager'),
            'StageProgress' => $this->queryStageFromSnapshot($params, 'clinic_manager')
        ];
    }

    public function getExecutiveSummary(array $params)
    {
        return [
            'KpiSummary'    => $this->queryKpiFromSnapshot($params, 'executive'),
            'StageProgress' => $this->queryStageFromSnapshot($params, 'executive'),
        ];
    }

    public function getDoctorSummary(array $params)
    {
        return [
            'KpiSummary'    => $this->queryKpiFromSnapshot($params, 'doctor'),
            'StageProgress' => $this->queryStageFromSnapshot($params, 'doctor'),
        ];
    }

    public function getPriorityList(array $params)
    {
        $branchId     = $this->parseBranchIds($params['BranchId'] ?? null);
        $staffId         = (int) ($params['StaffId'] ?? 0);
        $lmstart         = (int) ($params['lmstart'] ?? 1);
        $limit        = (int) ($params['limit'] ?? ($params['limit'] ?? 20));
        $snapshotDate = date('Y-m-d');

        // 1. Chạy Query Gọi Data Thật
        $query = DB::table('pos.OrthoPrioritySnapshot')
            ->where('SnapshotDate', '=', $snapshotDate)
            ->when(!empty($branchId), function ($q) use ($branchId) {
                return $q->whereIn('BranchId', $branchId);
            })
            ->when($staffId > 0, function ($a) use ($staffId) {
                return $a->where('DoctorStaffId', $staffId);
            })
            ->orderBy('SortOrder', 'asc')
            ->orderBy('OverdueDays', 'desc');

        $results = $query->paginate($limit,['*'],'page', round((int) $lmstart/ (int) $limit) + 1);

        return $results;
    }

    public function getNearCompletion(array $params)
    {
        $branchId     = $this->parseBranchIds($params['BranchId'] ?? null);
        $staffId         = (int) ($params['StaffId'] ?? 0);
        $lmstart         = (int) ($params['lmstart'] ?? 1);
        $limit         = (int) ($params['limit'] ?? ($params['limit'] ?? 20));
        $showDoctor   = (bool) ($params['ShowDoctor'] ?? true);
        $snapshotDate = date('Y-m-d');

        // 1. Chạy Query Gọi Data Thật
        $query = DB::table('pos.OrthoNearCompletionSnapshot')
            ->where('SnapshotDate', '=', $snapshotDate)
            ->when(!empty($branchId), function ($q) use ($branchId) {
                return $q->whereIn('BranchId', $branchId);
            })
            ->when($staffId > 0, function ($a) use ($staffId) {
                return $a->where('DoctorStaffId', $staffId);
            })
            ->orderBy('ExpectedCompletionDate', 'asc');

        $results = $query->paginate($limit,['*'],'page', round((int) $lmstart/ (int) $limit) + 1);

        return $results;
    }

    public function getDoctorList(array $params)
    {
        $branchId       = $this->parseBranchIds($params['BranchId'] ?? null);
        $lmstart        = (int) ($params['lmstart'] ?? 1);
        $limit          = (int) ($params['limit'] ?? ($params['limit'] ?? 20));
        $snapshotDate   = date('Y-m-d');

        // 1. Chạy Query Gọi Data Thật
        $query = DB::table('pos.OrthoDoctorSnapshot')
            ->where('SnapshotDate', '=', $snapshotDate)
            ->when(!empty($branchId), function ($q) use ($branchId) {
                return $q->whereIn('BranchId', $branchId);
            })
            ->groupBy('DoctorStaffId','DoctorCode', 'DoctorName', 'SpecializationCode')
            ->selectRaw("
                DoctorStaffId as StaffId,
                DoctorCode,
                DoctorName,
                SpecializationCode,
                MAX(BranchCode) AS BranchCode,
                SUM(DirectCases)  AS DirectCases,
                SUM(GuidedCases)  AS GuidedCases
            ")
            ->orderBy('DirectCases', 'desc');

        $results = $query->paginate($limit,['*'],'page', round((int) $lmstart/ (int) $limit) + 1);

        return $results;
    }

    // ════════════════════════════════════════════════════════
    // PRIVATE — Snapshot Helpers
    // ════════════════════════════════════════════════════════

    private function queryKpiFromSnapshot(array $params, string $role = 'clinic_manager')
    {
        $branchId     = $this->parseBranchIds($params['BranchId'] ?? null);
        $doctorStaffId = $params['StaffId'] ?? 0;

        $query = DB::table('pos.OrthoKpiSnapshot')
            ->when(!empty($branchId), function ($q) use ($branchId) {
                return $q->whereIn('BranchId', $branchId);
            })
            ->when((int) $doctorStaffId > 0, function ($q) use ($doctorStaffId) {
                // BS: chỉ xem data của mình
                $q->where('DoctorStaffId', $doctorStaffId);
            });

        $result = $query->selectRaw("
                SUM(TotalCases)             AS TotalCases,
                SUM(NewCasesThisMonth)      AS NewCasesThisMonth,
                SUM(BracketCases)           AS BracketCases,
                SUM(InvisalignCases)        AS InvisalignCases,
                SUM(TotalDoctors)           AS TotalDoctors,
                SUM(OverdueFollowUp)        AS OverdueFollowUp,
                SUM(OverdueTreatment)       AS OverdueTreatment,
                SUM(NearCompletion)         AS NearCompletion,
                SUM(NewThisWeek)            AS NewThisWeek,
                SUM(DirectCases)            AS DirectCases,
                SUM(DirectBracketCases)     AS DirectBracketCases,
                SUM(DirectInvisalignCases)  AS DirectInvisalignCases,
                SUM(GuidedCases)            AS GuidedCases,
                SUM(GuidedBracketCases)     AS GuidedBracketCases,
                SUM(GuidedInvisalignCases)  AS GuidedInvisalignCases,
                SUM(CompletedThisMonth)     AS CompletedThisMonth
            ")
            ->first();
        
        if (!$result || (int) $result->TotalCases <= 0) {
            return $this->defaultKpi($role);
        }

        $total   = (int) $result->TotalCases;
        $bracket = (int) $result->BracketCases;
        $inv     = (int) $result->InvisalignCases;

        $data = [
            'TotalCases'        => $total,
            'NewCasesThisMonth' => (int) $result->NewCasesThisMonth,
            'BracketCases'      => $bracket,
            'BracketPercent'    => $total > 0 ? (int) round($bracket / $total * 100) : 0,
            'InvisalignCases'   => $inv,
            'InvisalignPercent' => $total > 0 ? (int) round($inv / $total * 100) : 0,
            'TotalDoctors'      => (int) $result->TotalDoctors,
            'OverdueFollowUp'   => (int) $result->OverdueFollowUp,
            'OverdueTreatment'  => (int) $result->OverdueTreatment,
            'NearCompletion'    => (int) $result->NearCompletion,
            'NewThisWeek'       => (int) $result->NewThisWeek,
        ];

        if ($role === 'executive') {
            $data = array_merge($data, [
                'DirectCases'           => (int) $result->DirectCases,
                'DirectBracketCases'    => (int) $result->DirectBracketCases,
                'DirectInvisalignCases' => (int) $result->DirectInvisalignCases,
                'GuidedCases'           => (int) $result->GuidedCases,
                'GuidedBracketCases'    => (int) $result->GuidedBracketCases,
                'GuidedInvisalignCases' => (int) $result->GuidedInvisalignCases,
                'CompletedThisMonth'    => (int) $result->CompletedThisMonth,
            ]);
        }

        return $data;
    }

    private function queryStageFromSnapshot(array $params, string $role = 'clinic_manager')
    {
        $branchId     = $this->parseBranchIds($params['BranchId'] ?? null);
        $serviceType     = $params['ServiceType'] ?? 1;
        $doctorStaffId = $params['StaffId'] ?? 0;

        return DB::table('pos.OrthoStageSnapshot')
            ->when(!empty($branchId), function ($q) use ($branchId) {
                return $q->whereIn('BranchId', $branchId);
            })
            ->when($doctorStaffId > 0, function ($q) use ($doctorStaffId) {
                $q->where('DoctorStaffId', $doctorStaffId);
            })
            ->where('Type', $serviceType)
            ->selectRaw('Stage, SUM(Total) AS Total')
            ->groupBy('Stage')
            ->orderBy('Stage', 'asc')
            ->get()
            ->map(function ($row) {
                return [
                    'Stage'     => (int) $row->Stage ?? 0,
                    'Total'     => (int) $row->Total ?? 0,
                ];
            })
            ->toArray();
    }

    private function parseBranchIds($branchId)
    {
        if (is_array($branchId)) {
            return array_filter(array_map('intval', $branchId));
        }
        $intVal = (int) $branchId;
        return $intVal > 0 ? [$intVal] : [];
    }

    private function defaultKpi(string $role)
    {
        $base = [
            'TotalCases'        => 0,
            'NewCasesThisMonth' => 0,
            'BracketCases'      => 0,
            'BracketPercent'    => 0,
            'InvisalignCases'   => 0,
            'InvisalignPercent' => 0,
            'TotalDoctors'      => 0,
            'OverdueFollowUp'   => 0,
            'OverdueTreatment'  => 0,
            'NearCompletion'    => 0,
            'NewThisWeek'       => 0,
        ];

        if ($role === 'executive') {
            return array_merge($base, [
                'DirectCases'           => 0,
                'DirectBracketCases'    => 0,
                'DirectInvisalignCases' => 0,
                'GuidedCases'           => 0,
                'GuidedBracketCases'    => 0,
                'GuidedInvisalignCases' => 0,
                'CompletedThisMonth'    => 0,
            ]);
        }

        return $base;
    }

    public function buildKpiSnapshot($snapshotDate = null)
    {
        $snapshotDate = $snapshotDate ?? date('Y-m-d');
        $startOfWeek  = Carbon::parse($snapshotDate)->startOfWeek()->toDateString();
        $startOfMonth = Carbon::parse($snapshotDate)->startOfMonth()->toDateString();
        $endOfMonth   = Carbon::parse($snapshotDate)->endOfMonth()->toDateString() . ' 23:59:59';
        $oneMonthAgo  = Carbon::parse($snapshotDate)->subMonth()->toDateString();
        $now          = date('Y-m-d H:i:s');

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

        // ── 1. Main KPI ───────────────────────────────────────────────────────
        $mainRows = DB::table('pos.OrderDetail as od')
            ->join('pos.Service as s', 's.ServiceId', '=', 'od.ServiceId')
            ->join('pos.TreatmentMedicalProcedure as tmp', 'tmp.TreatmentMedicalProcedureId', '=', 'od.TreatmentMedicalProcedureId')
            ->join('pos.OrderChanging as oc', 'oc.OrderChangingId', '=', 'od.OrderChangingId')
            ->join('pos.Doctor as d', 'd.StaffId', '=', 'oc.ChangedBy')
            ->where('s.WarrantyType', 'O')
            ->where('tmp.TreatmentMedicalProcedureStatusId', 2)
            ->whereNotNull('od.ConsultedBranchId')
            ->groupBy('od.ConsultedBranchId', 'oc.ChangedBy')
            ->selectRaw("
                od.ConsultedBranchId AS BranchId,
                oc.ChangedBy AS DoctorStaffId,
                COUNT(DISTINCT od.TreatmentId) AS TotalCases,
                COUNT(DISTINCT CASE WHEN s.OrthodonticType = 1 THEN od.TreatmentId END) AS BracketCases,
                COUNT(DISTINCT CASE WHEN s.OrthodonticType = 2 THEN od.TreatmentId END) AS InvisalignCases,
                COUNT(DISTINCT CASE WHEN od.FirstTreatmentTime >= ? THEN od.TreatmentId END) AS NewThisWeek,
                COUNT(DISTINCT CASE WHEN od.FirstTreatmentTime >= ? THEN od.TreatmentId END) AS NewCasesThisMonth,
                COUNT(DISTINCT CASE WHEN d.OrthodonticAdvisorStaffId IS NULL     THEN od.TreatmentId END) AS DirectCases,
                COUNT(DISTINCT CASE WHEN d.OrthodonticAdvisorStaffId IS NOT NULL THEN od.TreatmentId END) AS GuidedCases,
                COUNT(DISTINCT CASE WHEN d.OrthodonticAdvisorStaffId IS NULL     AND s.OrthodonticType = 1 THEN od.TreatmentId END) AS DirectBracketCases,
                COUNT(DISTINCT CASE WHEN d.OrthodonticAdvisorStaffId IS NULL     AND s.OrthodonticType = 2 THEN od.TreatmentId END) AS DirectInvisalignCases,
                COUNT(DISTINCT CASE WHEN d.OrthodonticAdvisorStaffId IS NOT NULL AND s.OrthodonticType = 1 THEN od.TreatmentId END) AS GuidedBracketCases,
                COUNT(DISTINCT CASE WHEN d.OrthodonticAdvisorStaffId IS NOT NULL AND s.OrthodonticType = 2 THEN od.TreatmentId END) AS GuidedInvisalignCases
            ", [$startOfWeek, $startOfMonth])
            ->get();

        if ($mainRows->isEmpty()) {
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            return ['inserted' => 0, 'snapshotDate' => $snapshotDate];
        }

        // ── 2. CompletedThisMonth theo (BranchId, DoctorStaffId) ─────────────
        $completedRows = DB::table('pos.OrderDetail as od')
            ->join('pos.Service as s', 's.ServiceId', '=', 'od.ServiceId')
            ->join('pos.TreatmentMedicalProcedure as tmp', 'tmp.TreatmentMedicalProcedureId', '=', 'od.TreatmentMedicalProcedureId')
            ->join('pos.OrderChanging as oc', 'oc.OrderChangingId', '=', 'od.OrderChangingId')
            ->where('s.WarrantyType', 'O')
            ->where('tmp.TreatmentMedicalProcedureStatusId', 3)
            ->whereNotNull('od.ConsultedBranchId')
            ->whereBetween('tmp.LatestUpdated', [$startOfMonth, $endOfMonth])
            ->groupBy('od.ConsultedBranchId', 'oc.ChangedBy')
            ->selectRaw('od.ConsultedBranchId AS BranchId, oc.ChangedBy AS DoctorStaffId, COUNT(DISTINCT od.TreatmentId) AS CompletedThisMonth')
            ->get();

        // ── 3. OverdueFollowUp theo (BranchId, DoctorStaffId) ────────────────
        $overdueFollowUpRows = DB::table('pos.OrderDetail as od')
            ->join('pos.Service as s', 's.ServiceId', '=', 'od.ServiceId')
            ->join('pos.TreatmentMedicalProcedure as tmp', 'tmp.TreatmentMedicalProcedureId', '=', 'od.TreatmentMedicalProcedureId')
            ->join('pos.OrderChanging as oc', 'oc.OrderChangingId', '=', 'od.OrderChangingId')
            ->join(DB::raw('(
                SELECT TreatmentMedicalProcedureId, MAX(TreatmentCompletedDate) AS LastStepDate
                FROM pos.AllocatedRevenueTracking
                WHERE TrackingType = 1 AND TreatmentCompletedDate IS NOT NULL
                GROUP BY TreatmentMedicalProcedureId
            ) AS art'), 'art.TreatmentMedicalProcedureId', '=', 'tmp.TreatmentMedicalProcedureId')
            ->where('s.WarrantyType', 'O')
            ->where('tmp.TreatmentMedicalProcedureStatusId', 2)
            ->whereNotNull('od.ConsultedBranchId')
            ->whereRaw('art.LastStepDate < ?', [$oneMonthAgo])
            ->groupBy('od.ConsultedBranchId', 'oc.ChangedBy')
            ->selectRaw('od.ConsultedBranchId AS BranchId, oc.ChangedBy AS DoctorStaffId, COUNT(DISTINCT od.TreatmentId) AS OverdueFollowUp')
            ->get();

        // ── 4. OverdueTreatment theo (BranchId, DoctorStaffId) ───────────────
        $overdueTreatmentRows = DB::table('pos.OrderDetail as od')
            ->join('pos.Service as s', 's.ServiceId', '=', 'od.ServiceId')
            ->join('pos.TreatmentMedicalProcedure as tmp', 'tmp.TreatmentMedicalProcedureId', '=', 'od.TreatmentMedicalProcedureId')
            ->join('pos.OrderChanging as oc', 'oc.OrderChangingId', '=', 'od.OrderChangingId')
            ->join(DB::raw('(
                SELECT ServiceId, COUNT(*) AS TotalSteps
                FROM pos.TreatmentProcedureProgressByStep
                WHERE IsActive = 1
                GROUP BY ServiceId
            ) AS steps'), 'steps.ServiceId', '=', 'od.ServiceId')
            ->where('s.WarrantyType', 'O')
            ->where('tmp.TreatmentMedicalProcedureStatusId', 2)
            ->whereNotNull('od.ConsultedBranchId')
            ->whereNotNull('od.FirstTreatmentTime')
            ->whereRaw('DATE_ADD(od.FirstTreatmentTime, INTERVAL steps.TotalSteps MONTH) < ?', [$snapshotDate])
            ->groupBy('od.ConsultedBranchId', 'oc.ChangedBy')
            ->selectRaw('od.ConsultedBranchId AS BranchId, oc.ChangedBy AS DoctorStaffId, COUNT(DISTINCT od.TreatmentId) AS OverdueTreatment')
            ->get();

        // ── 5. NearCompletion theo (BranchId, DoctorStaffId) ─────────────────
        $nearCompletionRows = DB::table('pos.OrderDetail as od')
            ->join('pos.Service as s', 's.ServiceId', '=', 'od.ServiceId')
            ->join('pos.TreatmentMedicalProcedure as tmp', 'tmp.TreatmentMedicalProcedureId', '=', 'od.TreatmentMedicalProcedureId')
            ->join('pos.OrderChanging as oc', 'oc.OrderChangingId', '=', 'od.OrderChangingId')
            ->join(DB::raw('(
                SELECT TreatmentMedicalProcedureId, MAX(ProcedureProgressId) AS CurrentProgressId
                FROM pos.TreatmentProcedureProgress
                GROUP BY TreatmentMedicalProcedureId
            ) AS curStep'), 'curStep.TreatmentMedicalProcedureId', '=', 'tmp.TreatmentMedicalProcedureId')
            ->join('pos.TreatmentProcedureProgressByStep as tpps', function ($join) {
                $join->on('tpps.ServiceId', '=', 'od.ServiceId')
                    ->on('tpps.ProcedureProgressId', '=', 'curStep.CurrentProgressId');
            })
            ->join(DB::raw('(
                SELECT ServiceId, MAX(Stage) AS MaxStage
                FROM pos.TreatmentProcedureProgressByStep
                WHERE IsActive = 1
                GROUP BY ServiceId
            ) AS maxStage'), 'maxStage.ServiceId', '=', 'od.ServiceId')
            ->where('s.WarrantyType', 'O')
            ->where('tmp.TreatmentMedicalProcedureStatusId', 2)
            ->whereNotNull('od.ConsultedBranchId')
            ->whereColumn('tpps.Stage', 'maxStage.MaxStage')
            ->groupBy('od.ConsultedBranchId', 'oc.ChangedBy')
            ->selectRaw('od.ConsultedBranchId AS BranchId, oc.ChangedBy AS DoctorStaffId, COUNT(DISTINCT od.TreatmentId) AS NearCompletion')
            ->get();

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

        // ── Lookup maps: composite key BranchId_DoctorStaffId ────────────────
        $completedMap       = [];
        $overdueFollowUpMap = [];
        $overdueTreatMap    = [];
        $nearCompletionMap  = [];

        foreach ($completedRows as $r) {
            $completedMap[$r->BranchId . '_' . $r->DoctorStaffId] = (int) $r->CompletedThisMonth;
        }
        foreach ($overdueFollowUpRows as $r) {
            $overdueFollowUpMap[$r->BranchId . '_' . $r->DoctorStaffId] = (int) $r->OverdueFollowUp;
        }
        foreach ($overdueTreatmentRows as $r) {
            $overdueTreatMap[$r->BranchId . '_' . $r->DoctorStaffId] = (int) $r->OverdueTreatment;
        }
        foreach ($nearCompletionRows as $r) {
            $nearCompletionMap[$r->BranchId . '_' . $r->DoctorStaffId] = (int) $r->NearCompletion;
        }

        // ── Batch INSERT ──────────────────────────────────────────────────────
        $values   = [];
        $bindings = [];

        foreach ($mainRows as $row) {
            $total    = (int) $row->TotalCases;
            $bracket  = (int) $row->BracketCases;
            $inv      = (int) $row->InvisalignCases;
            $key      = $row->BranchId . '_' . $row->DoctorStaffId;

            $values[]   = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $bindings[] = $snapshotDate;
            $bindings[] = (int) $row->BranchId;
            $bindings[] = (int) $row->DoctorStaffId;
            $bindings[] = $total;
            $bindings[] = (int) $row->NewCasesThisMonth;
            $bindings[] = $bracket;
            $bindings[] = $total > 0 ? round($bracket / $total * 100, 2) : 0.00;
            $bindings[] = $inv;
            $bindings[] = $total > 0 ? round($inv     / $total * 100, 2) : 0.00;
            $bindings[] = 1; // TotalDoctors = 1 per row, SUM khi aggregate
            $bindings[] = $overdueFollowUpMap[$key] ?? 0;
            $bindings[] = $overdueTreatMap[$key]    ?? 0;
            $bindings[] = $nearCompletionMap[$key]  ?? 0;
            $bindings[] = (int) $row->NewThisWeek;
            $bindings[] = (int) $row->DirectCases;
            $bindings[] = (int) $row->DirectBracketCases;
            $bindings[] = (int) $row->DirectInvisalignCases;
            $bindings[] = (int) $row->GuidedCases;
            $bindings[] = (int) $row->GuidedBracketCases;
            $bindings[] = (int) $row->GuidedInvisalignCases;
            $bindings[] = $completedMap[$key] ?? 0;
            $bindings[] = $now;
            $bindings[] = $now;
        }

        DB::statement("
            INSERT INTO pos.OrthoKpiSnapshot
                (SnapshotDate, BranchId, DoctorStaffId, TotalCases, NewCasesThisMonth,
                BracketCases, BracketPercent, InvisalignCases, InvisalignPercent,
                TotalDoctors, OverdueFollowUp, OverdueTreatment, NearCompletion,
                NewThisWeek, DirectCases, DirectBracketCases, DirectInvisalignCases,
                GuidedCases, GuidedBracketCases, GuidedInvisalignCases,
                CompletedThisMonth, CreatedAt, UpdatedAt)
            VALUES " . implode(', ', $values) . "
            ON DUPLICATE KEY UPDATE
                TotalCases            = VALUES(TotalCases),
                NewCasesThisMonth     = VALUES(NewCasesThisMonth),
                BracketCases          = VALUES(BracketCases),
                BracketPercent        = VALUES(BracketPercent),
                InvisalignCases       = VALUES(InvisalignCases),
                InvisalignPercent     = VALUES(InvisalignPercent),
                TotalDoctors          = VALUES(TotalDoctors),
                OverdueFollowUp       = VALUES(OverdueFollowUp),
                OverdueTreatment      = VALUES(OverdueTreatment),
                NearCompletion        = VALUES(NearCompletion),
                NewThisWeek           = VALUES(NewThisWeek),
                DirectCases           = VALUES(DirectCases),
                DirectBracketCases    = VALUES(DirectBracketCases),
                DirectInvisalignCases = VALUES(DirectInvisalignCases),
                GuidedCases           = VALUES(GuidedCases),
                GuidedBracketCases    = VALUES(GuidedBracketCases),
                GuidedInvisalignCases = VALUES(GuidedInvisalignCases),
                CompletedThisMonth    = VALUES(CompletedThisMonth),
                UpdatedAt             = VALUES(UpdatedAt)
        ", $bindings);

        return [
            'inserted'     => $mainRows->count(),
            'snapshotDate' => $snapshotDate,
        ];
    }

    public function buildStageSnapshot($snapshotDate = null)
    {
        $snapshotDate = $snapshotDate ?? date('Y-m-d');
        $now          = date('Y-m-d H:i:s');

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

        // Stage hiện tại = ProcedureProgressId lớn nhất ghi nhận trong AllocatedRevenueTracking
        $rows = DB::table('pos.OrderDetail as od')
            ->join('pos.OrderChanging as oc', 'oc.OrderChangingId', '=', 'od.OrderChangingId')
            ->join('pos.Service as s', 's.ServiceId', '=', 'od.ServiceId')
            ->join('pos.TreatmentMedicalProcedure as tmp', 'tmp.TreatmentMedicalProcedureId', '=', 'od.TreatmentMedicalProcedureId')
            ->join(DB::raw('(
                SELECT TreatmentMedicalProcedureId, MAX(ProcedureProgressId) AS CurrentProgressId
                FROM pos.AllocatedRevenueTracking
                WHERE TrackingType = 1
                AND TreatmentCompletedDate IS NOT NULL
                GROUP BY TreatmentMedicalProcedureId
            ) AS latestStep'), 'latestStep.TreatmentMedicalProcedureId', '=', 'tmp.TreatmentMedicalProcedureId')
            ->join('pos.TreatmentProcedureProgressByStep as tpps', function ($join) {
                $join->on('tpps.ServiceId', '=', 'od.ServiceId')
                    ->on('tpps.ProcedureProgressId', '=', 'latestStep.CurrentProgressId');
            })
            ->where('s.WarrantyType', 'O')
            ->where('tmp.TreatmentMedicalProcedureStatusId', 2)
            ->whereNotNull('od.ConsultedBranchId')
            ->groupBy('od.ConsultedBranchId', 'oc.ChangedBy', 's.OrthodonticType', 'tpps.Stage')
            ->selectRaw('
                oc.ChangedBy            AS DoctorStaffId,
                od.ConsultedBranchId    AS BranchId,
                s.OrthodonticType       AS Type,
                tpps.Stage              AS Stage,
                MIN(tpps.ProgressName)  AS StageName,
                COUNT(DISTINCT od.TreatmentId) AS Total
            ')
            ->orderBy('od.ConsultedBranchId')
            ->orderBy('tpps.Stage')
            ->get();

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

        if ($rows->isEmpty()) {
            return ['inserted' => 0, 'snapshotDate' => $snapshotDate];
        }

        $values   = [];
        $bindings = [];

        foreach ($rows as $row) {
            $values[]   = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $bindings[] = $snapshotDate;
            $bindings[] = (int) $row->BranchId;
            $bindings[] = (int) $row->DoctorStaffId;
            $bindings[] = (int) $row->Stage;
            $bindings[] = (int) $row->Type;
            $bindings[] = $row->StageName;
            $bindings[] = (int) $row->Total;
            $bindings[] = $now;
            $bindings[] = $now;
        }

        DB::statement("
            INSERT INTO pos.OrthoStageSnapshot
                (SnapshotDate, BranchId, DoctorStaffId, Stage, Type, StageName, Total, CreatedAt, UpdatedAt)
            VALUES " . implode(', ', $values) . "
            ON DUPLICATE KEY UPDATE
                StageName = VALUES(StageName),
                Total     = VALUES(Total),
                UpdatedAt = VALUES(UpdatedAt)
        ", $bindings);

        return [
            'inserted'     => $rows->count(),
            'snapshotDate' => $snapshotDate,
        ];
    }

    public function buildPrioritySnapshot($snapshotDate = null)
    {
        $snapshotDate = $snapshotDate ?? date('Y-m-d');
        $oneMonthAgo  = Carbon::parse($snapshotDate)->subMonth()->toDateString();
        $now          = date('Y-m-d H:i:s');

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

        // ── AlertType 1: Trễ tái khám (bước cuối > 1 tháng) ─────────────────
        $type1Rows = DB::table('pos.OrderDetail as od')
            ->join('pos.Service as s', 's.ServiceId', '=', 'od.ServiceId')
            ->join('pos.TreatmentMedicalProcedure as tmp', 'tmp.TreatmentMedicalProcedureId', '=', 'od.TreatmentMedicalProcedureId')
            ->join('pos.OrderChanging as oc', 'oc.OrderChangingId', '=', 'od.OrderChangingId')
            ->join(DB::raw('(
                SELECT TreatmentMedicalProcedureId,
                    MAX(TreatmentCompletedDate) AS LastStepDate,
                    MAX(ProcedureProgressId)    AS CurrentProgressId
                FROM pos.AllocatedRevenueTracking
                WHERE TrackingType = 1 AND TreatmentCompletedDate IS NOT NULL
                GROUP BY TreatmentMedicalProcedureId
            ) AS art'), 'art.TreatmentMedicalProcedureId', '=', 'tmp.TreatmentMedicalProcedureId')
            ->join('pos.TreatmentProcedureProgressByStep as tpps', function ($join) {
                $join->on('tpps.ServiceId', '=', 'od.ServiceId')
                    ->on('tpps.ProcedureProgressId', '=', 'art.CurrentProgressId');
            })
            ->join('pos.Treatment as t', 't.TreatmentId', '=', 'od.TreatmentId')
            ->join('pos.Customer as c', 'c.CustomerId', '=', 't.PersonId')
            ->join('in.Branch as b', 'b.BranchId', '=', 'od.ConsultedBranchId')
            ->join('in.Staff as st', 'st.StaffId', '=', 'oc.ChangedBy')
            ->leftJoin(DB::raw('(
                SELECT CustomerId, MIN(StartAt) AS NextStart
                FROM pos.Appointment
                WHERE StartAt > UNIX_TIMESTAMP(NOW())
                GROUP BY CustomerId
            ) AS appt'), 'appt.CustomerId', '=', 'c.CustomerId')
            ->where('s.WarrantyType', 'O')
            ->where('tmp.TreatmentMedicalProcedureStatusId', 2)
            ->whereNotNull('od.ConsultedBranchId')
            ->whereRaw('art.LastStepDate < ?', [$oneMonthAgo])
            ->selectRaw("
                od.ConsultedBranchId                        AS BranchId,
                od.TreatmentId,
                c.CustomerCode,
                c.FullName                                  AS CustomerName,
                oc.ChangedBy                                AS DoctorStaffId,
                od.ConsultedBranchId                        AS ConsultedBranchId,
                b.BranchCode,
                st.FullName                                 AS DoctorName,
                tpps.Stage,
                tpps.ProgressName                           AS StageName,
                DATE(FROM_UNIXTIME(appt.NextStart))         AS NextAppointmentDate,
                od.FirstTreatmentTime,
                1                                           AS AlertType,
                DATEDIFF(NOW(), art.LastStepDate)           AS OverdueDays
            ")
            ->get();

        // ── AlertType 2: Trễ quá hạn điều trị ───────────────────────────────
        $type2Rows = DB::table('pos.OrderDetail as od')
            ->join('pos.Service as s', 's.ServiceId', '=', 'od.ServiceId')
            ->join('pos.TreatmentMedicalProcedure as tmp', 'tmp.TreatmentMedicalProcedureId', '=', 'od.TreatmentMedicalProcedureId')
            ->join('pos.OrderChanging as oc', 'oc.OrderChangingId', '=', 'od.OrderChangingId')
            ->join(DB::raw('(
                SELECT ServiceId, COUNT(*) AS TotalSteps
                FROM pos.TreatmentProcedureProgressByStep
                WHERE IsActive = 1
                GROUP BY ServiceId
            ) AS steps'), 'steps.ServiceId', '=', 'od.ServiceId')
            ->join(DB::raw('(
                SELECT TreatmentMedicalProcedureId, MAX(ProcedureProgressId) AS CurrentProgressId
                FROM pos.AllocatedRevenueTracking
                WHERE TrackingType = 1 AND TreatmentCompletedDate IS NOT NULL
                GROUP BY TreatmentMedicalProcedureId
            ) AS latestStep'), 'latestStep.TreatmentMedicalProcedureId', '=', 'tmp.TreatmentMedicalProcedureId')
            ->join('pos.TreatmentProcedureProgressByStep as tpps', function ($join) {
                $join->on('tpps.ServiceId', '=', 'od.ServiceId')
                    ->on('tpps.ProcedureProgressId', '=', 'latestStep.CurrentProgressId');
            })
            ->join('pos.Treatment as t', 't.TreatmentId', '=', 'od.TreatmentId')
            ->join('pos.Customer as c', 'c.CustomerId', '=', 't.PersonId')
            ->join('in.Branch as b', 'b.BranchId', '=', 'od.ConsultedBranchId')
            ->join('in.Staff as st', 'st.StaffId', '=', 'oc.ChangedBy')
            ->leftJoin(DB::raw('(
                SELECT CustomerId, MIN(StartAt) AS NextStart
                FROM pos.Appointment
                WHERE StartAt > UNIX_TIMESTAMP(NOW())
                GROUP BY CustomerId
            ) AS appt'), 'appt.CustomerId', '=', 'c.CustomerId')
            ->where('s.WarrantyType', 'O')
            ->where('tmp.TreatmentMedicalProcedureStatusId', 2)
            ->whereNotNull('od.ConsultedBranchId')
            ->whereNotNull('od.FirstTreatmentTime')
            ->whereRaw('DATE_ADD(od.FirstTreatmentTime, INTERVAL steps.TotalSteps MONTH) < ?', [$snapshotDate])
            ->selectRaw("
                od.ConsultedBranchId                                                          AS BranchId,
                od.TreatmentId,
                c.CustomerCode,
                c.FullName                                                                    AS CustomerName,
                oc.ChangedBy                                                                  AS DoctorStaffId,
                od.ConsultedBranchId                                                          AS ConsultedBranchId,
                b.BranchCode,
                st.FullName                                                                   AS DoctorName,
                tpps.Stage,
                tpps.ProgressName                                                             AS StageName,
                DATE(FROM_UNIXTIME(appt.NextStart))                                           AS NextAppointmentDate,
                od.FirstTreatmentTime,
                2                                                                             AS AlertType,
                DATEDIFF(NOW(), DATE_ADD(od.FirstTreatmentTime, INTERVAL steps.TotalSteps MONTH)) AS OverdueDays
            ")
            ->get();

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

        // ── Merge: AlertType 2 override AlertType 1 cùng TreatmentId ─────────
        $merged = [];
        foreach ($type1Rows as $row) {
            $merged[(int) $row->TreatmentId] = $row;
        }
        foreach ($type2Rows as $row) {
            $merged[(int) $row->TreatmentId] = $row;
        }

        if (empty($merged)) {
            return ['inserted' => 0, 'snapshotDate' => $snapshotDate];
        }

        // SortOrder: OverdueDays nhiều nhất lên đầu
        usort($merged, function ($a, $b) {
            return (int) $b->OverdueDays - (int) $a->OverdueDays;
        });

        $values    = [];
        $bindings  = [];
        $sortOrder = 1;

        foreach ($merged as $row) {
            $overdueDays = max(0, (int) $row->OverdueDays);
            $alertType   = (int) $row->AlertType;
            $alertLabel  = $alertType === 1
                ? "Trễ tái khám {$overdueDays} ngày"
                : "Trễ quá hạn {$overdueDays} ngày";

            $values[]   = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $bindings[] = (int) $row->BranchId;
            $bindings[] = (int) $row->TreatmentId;
            $bindings[] = $row->CustomerCode;
            $bindings[] = $row->CustomerName;
            $bindings[] = (int) $row->ConsultedBranchId;
            $bindings[] = $row->BranchCode;
            $bindings[] = $row->DoctorName;
            $bindings[] = $row->DoctorStaffId;
            $bindings[] = $row->Stage !== null ? (int) $row->Stage : null;
            $bindings[] = $row->StageName;
            $bindings[] = $row->NextAppointmentDate;
            $bindings[] = $row->FirstTreatmentTime;
            $bindings[] = $alertType;
            $bindings[] = $alertLabel;
            $bindings[] = $overdueDays;
            $bindings[] = $sortOrder++;
            $bindings[] = $snapshotDate;
            $bindings[] = $now;
            $bindings[] = $now;
        }

        DB::statement("
            INSERT INTO pos.OrthoPrioritySnapshot
                (BranchId, TreatmentId, CustomerCode, CustomerName,
                ConsultedBranchId, BranchCode, DoctorName, DoctorStaffId,
                Stage, StageName, NextAppointmentDate, FirstTreatmentTime,
                AlertType, AlertLabel, OverdueDays, SortOrder,
                SnapshotDate, CreatedAt, UpdatedAt)
            VALUES " . implode(', ', $values) . "
            ON DUPLICATE KEY UPDATE
                CustomerCode        = VALUES(CustomerCode),
                CustomerName        = VALUES(CustomerName),
                ConsultedBranchId   = VALUES(ConsultedBranchId),
                BranchCode          = VALUES(BranchCode),
                DoctorName          = VALUES(DoctorName),
                DoctorStaffId       = VALUES(DoctorStaffId),
                Stage               = VALUES(Stage),
                StageName           = VALUES(StageName),
                NextAppointmentDate = VALUES(NextAppointmentDate),
                FirstTreatmentTime  = VALUES(FirstTreatmentTime),
                AlertType           = VALUES(AlertType),
                AlertLabel          = VALUES(AlertLabel),
                OverdueDays         = VALUES(OverdueDays),
                SortOrder           = VALUES(SortOrder),
                SnapshotDate        = VALUES(SnapshotDate),
                UpdatedAt           = VALUES(UpdatedAt)
        ", $bindings);

        return [
            'inserted'     => count($merged),
            'snapshotDate' => $snapshotDate,
        ];
    }

    public function buildDoctorSnapshot($snapshotDate = null)
    {
        $snapshotDate = $snapshotDate ?? date('Y-m-d');
        $now          = date('Y-m-d H:i:s');

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

        $rows = DB::table('pos.OrderDetail as od')
            ->join('pos.Service as s', 's.ServiceId', '=', 'od.ServiceId')
            ->join('pos.TreatmentMedicalProcedure as tmp', 'tmp.TreatmentMedicalProcedureId', '=', 'od.TreatmentMedicalProcedureId')
            ->join('pos.OrderChanging as oc', 'oc.OrderChangingId', '=', 'od.OrderChangingId')
            ->join('in.Staff as st', 'st.StaffId', '=', 'oc.ChangedBy')
            ->join('pos.Doctor as d', 'd.StaffId', '=', 'oc.ChangedBy')
            ->leftJoin('in.Branch as b', 'b.BranchId', '=', 'od.ConsultedBranchId')
            ->where('s.WarrantyType', 'O')
            ->where('tmp.TreatmentMedicalProcedureStatusId', 2)
            ->whereNotNull('od.ConsultedBranchId')
            ->whereNotNull('st.StaffCode')
            ->groupBy('od.ConsultedBranchId', 'oc.ChangedBy')
            ->selectRaw("
                od.ConsultedBranchId AS BranchId,
                oc.ChangedBy                  AS DoctorStaffId,
                MIN(st.StaffCode)             AS DoctorCode,
                MIN(st.FullName)              AS DoctorName,
                od.ConsultedBranchId          AS ConsultedBranchId,
                MIN(b.BranchCode)             AS BranchCode,
                MIN(d.SpecializationCode)     AS SpecializationCode,
                COUNT(DISTINCT CASE WHEN d.OrthodonticAdvisorStaffId IS NULL     THEN od.TreatmentId END) AS DirectCases,
                COUNT(DISTINCT CASE WHEN d.OrthodonticAdvisorStaffId IS NOT NULL THEN od.TreatmentId END) AS GuidedCases,
                COUNT(DISTINCT od.TreatmentId) AS TotalCases
            ")
            ->get();

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

        if ($rows->isEmpty()) {
            return ['inserted' => 0, 'snapshotDate' => $snapshotDate];
        }

        $sorted    = $rows->sortByDesc(function ($row) { return (int) $row->TotalCases; })->values();
        $values    = [];
        $bindings  = [];
        $sortOrder = 1;

        foreach ($sorted as $row) {
            $values[]   = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $bindings[] = (int) $row->BranchId;
            $bindings[] = (int) $row->DoctorStaffId;
            $bindings[] = $row->DoctorCode;
            $bindings[] = $row->DoctorName;
            $bindings[] = (int) $row->ConsultedBranchId;
            $bindings[] = $row->BranchCode;
            $bindings[] = $row->SpecializationCode;
            $bindings[] = (int) $row->DirectCases;
            $bindings[] = (int) $row->GuidedCases;
            $bindings[] = 1;
            $bindings[] = $sortOrder++;
            $bindings[] = $snapshotDate;
            $bindings[] = $now;
            $bindings[] = $now;
        }

        DB::statement("
            INSERT INTO pos.OrthoDoctorSnapshot
                (BranchId, DoctorStaffId, DoctorCode, DoctorName, ConsultedBranchId, BranchCode,
                SpecializationCode, DirectCases, GuidedCases, WorkloadStatus, SortOrder,
                SnapshotDate, CreatedAt, UpdatedAt)
            VALUES " . implode(', ', $values) . "
            ON DUPLICATE KEY UPDATE
                DoctorStaffId      = VALUES(DoctorStaffId),
                DoctorName         = VALUES(DoctorName),
                ConsultedBranchId  = VALUES(ConsultedBranchId),
                BranchCode         = VALUES(BranchCode),
                SpecializationCode = VALUES(SpecializationCode),
                DirectCases        = VALUES(DirectCases),
                GuidedCases        = VALUES(GuidedCases),
                WorkloadStatus     = VALUES(WorkloadStatus),
                SortOrder          = VALUES(SortOrder),
                SnapshotDate       = VALUES(SnapshotDate),
                UpdatedAt          = VALUES(UpdatedAt)
        ", $bindings);

        return [
            'inserted'     => $sorted->count(),
            'snapshotDate' => $snapshotDate,
        ];
    }

    public function buildNearCompletionSnapshot($snapshotDate = null)
    {
        $snapshotDate = $snapshotDate ?? date('Y-m-d');
        $now          = date('Y-m-d H:i:s');

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

        $rows = DB::table('pos.OrderDetail as od')
            ->join('pos.Service as s', 's.ServiceId', '=', 'od.ServiceId')
            ->join('pos.TreatmentMedicalProcedure as tmp', 'tmp.TreatmentMedicalProcedureId', '=', 'od.TreatmentMedicalProcedureId')
            ->join('pos.OrderChanging as oc', 'oc.OrderChangingId', '=', 'od.OrderChangingId')
            ->join(DB::raw('(
                SELECT TreatmentMedicalProcedureId, MAX(ProcedureProgressId) AS CurrentProgressId
                FROM pos.AllocatedRevenueTracking
                WHERE TrackingType = 1 AND TreatmentCompletedDate IS NOT NULL
                GROUP BY TreatmentMedicalProcedureId
            ) AS latestStep'), 'latestStep.TreatmentMedicalProcedureId', '=', 'tmp.TreatmentMedicalProcedureId')
            ->join('pos.TreatmentProcedureProgressByStep as tpps', function ($join) {
                $join->on('tpps.ServiceId', '=', 'od.ServiceId')
                    ->on('tpps.ProcedureProgressId', '=', 'latestStep.CurrentProgressId');
            })
            ->join(DB::raw('(
                SELECT ServiceId, MAX(Stage) AS MaxStage
                FROM pos.TreatmentProcedureProgressByStep
                WHERE IsActive = 1
                GROUP BY ServiceId
            ) AS maxStage'), 'maxStage.ServiceId', '=', 'od.ServiceId')
            ->join(DB::raw('(
                SELECT ServiceId, COUNT(*) AS TotalSteps
                FROM pos.TreatmentProcedureProgressByStep
                WHERE IsActive = 1
                GROUP BY ServiceId
            ) AS steps'), 'steps.ServiceId', '=', 'od.ServiceId')
            ->join('pos.Treatment as t', 't.TreatmentId', '=', 'od.TreatmentId')
            ->join('pos.Customer as c', 'c.CustomerId', '=', 't.PersonId')
            ->join('in.Branch as b', 'b.BranchId', '=', 'od.ConsultedBranchId')
            ->join('in.Staff as st', 'st.StaffId', '=', 'oc.ChangedBy')
            ->where('s.WarrantyType', 'O')
            ->where('tmp.TreatmentMedicalProcedureStatusId', 2)
            ->whereNotNull('od.ConsultedBranchId')
            ->whereNotNull('od.FirstTreatmentTime')
            ->whereColumn('tpps.Stage', 'maxStage.MaxStage')
            ->selectRaw("
                od.ConsultedBranchId AS BranchId,
                oc.ChangedBy as DoctorStaffId,
                od.TreatmentId,
                c.CustomerCode,
                c.FullName AS CustomerName,
                od.ConsultedBranchId AS ConsultedBranchId,
                b.BranchCode,
                st.FullName AS DoctorName,
                od.FirstTreatmentTime,
                DATE(DATE_ADD(od.FirstTreatmentTime, INTERVAL steps.TotalSteps MONTH)) AS ExpectedCompletionDate
            ")
            ->orderByRaw('DATE_ADD(od.FirstTreatmentTime, INTERVAL steps.TotalSteps MONTH) ASC')
            ->get();

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

        if ($rows->isEmpty()) {
            return ['inserted' => 0, 'snapshotDate' => $snapshotDate];
        }

        $values    = [];
        $bindings  = [];
        $sortOrder = 1;

        foreach ($rows as $row) {
            $values[]   = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $bindings[] = (int) $row->BranchId;
            $bindings[] = (int) $row->TreatmentId;
            $bindings[] = $row->CustomerCode;
            $bindings[] = $row->CustomerName;
            $bindings[] = (int) $row->ConsultedBranchId;
            $bindings[] = $row->BranchCode;
            $bindings[] = $row->DoctorName;
            $bindings[] = $row->DoctorStaffId;
            $bindings[] = $row->FirstTreatmentTime;
            $bindings[] = $row->ExpectedCompletionDate;
            $bindings[] = $sortOrder++;
            $bindings[] = $snapshotDate;
            $bindings[] = $now;
            $bindings[] = $now;
        }

        DB::statement("
            INSERT INTO pos.OrthoNearCompletionSnapshot
                (BranchId, TreatmentId, CustomerCode, CustomerName,
                ConsultedBranchId, BranchCode, DoctorName, DoctorStaffId,
                FirstTreatmentTime, ExpectedCompletionDate,
                SortOrder, SnapshotDate, CreatedAt, UpdatedAt)
            VALUES " . implode(', ', $values) . "
            ON DUPLICATE KEY UPDATE
                CustomerCode           = VALUES(CustomerCode),
                CustomerName           = VALUES(CustomerName),
                ConsultedBranchId      = VALUES(ConsultedBranchId),
                BranchCode             = VALUES(BranchCode),
                DoctorName             = VALUES(DoctorName),
                DoctorStaffId          = VALUES(DoctorStaffId),
                FirstTreatmentTime     = VALUES(FirstTreatmentTime),
                ExpectedCompletionDate = VALUES(ExpectedCompletionDate),
                SortOrder              = VALUES(SortOrder),
                SnapshotDate           = VALUES(SnapshotDate),
                UpdatedAt              = VALUES(UpdatedAt)
        ", $bindings);

        return [
            'inserted'     => $rows->count(),
            'snapshotDate' => $snapshotDate,
        ];
    }
}