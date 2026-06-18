<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Repositories\OrthodonticRepository;

class OrthodonticController extends Controller
{
    protected $orthodonticRepo;

    public function __construct(OrthodonticRepository $orthodonticRepo)
    {
        parent::__construct();
        $this->orthodonticRepo = $orthodonticRepo;
    }

    // ── Summary ───────────────────────────────────────────────

    public function clinicManagerSummary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId'    => 'nullable|array',
            'ServiceType' => 'nullable|int',
        ]);

        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->orthodonticRepo->getClinicManagerSummary($request->all());
        $results[] = $this->formatData('ClinicManagerSummary', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function executiveSummary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId'    => 'nullable|array',
            'ServiceType' => 'nullable|int',
        ]);

        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->orthodonticRepo->getExecutiveSummary($request->all());
        $results[] = $this->formatData('ExecutiveSummary', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function doctorSummary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ServiceType' => 'nullable|int',
        ]);

        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->orthodonticRepo->getDoctorSummary($request->all());
        $results[] = $this->formatData('DoctorSummary', $data, 'Grid');
        return $this->json($results, 'views');
    }

    // ── Paginated ─────────────────────────────────────────────

    public function priorityList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId'    => 'nullable|array',
            'ShowDoctor'  => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->orthodonticRepo->getPriorityList($request->all());
        $results[] = $this->formatPagination('PriorityList', $data);
        return $this->json($results, 'views');
    }

    public function nearCompletion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId'    => 'nullable|array',
        ]);

        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->orthodonticRepo->getNearCompletion($request->all());
        $results[] = $this->formatPagination('NearCompletion', $data);
        return $this->json($results, 'views');
    }

    public function doctorList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId'    => 'nullable|array',
        ]);

        if ($validator->fails()) {
            $this->addMessage($validator->errors()->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->orthodonticRepo->getDoctorList($request->all());
        $results[] = $this->formatPagination('DoctorList', $data);
        return $this->json($results, 'views');
    }

    public function buildSnapshot()
    {
        $data = $this->orthodonticRepo->buildKpiSnapshot();
        $data = $this->orthodonticRepo->buildStageSnapshot();
        $data = $this->orthodonticRepo->buildPrioritySnapshot();
        $data = $this->orthodonticRepo->buildDoctorSnapshot();
        $data = $this->orthodonticRepo->buildNearCompletionSnapshot();
        $results[] = $this->formatData('BuildSnapshot', $data);
        return $this->json($results, 'views');
    }
}