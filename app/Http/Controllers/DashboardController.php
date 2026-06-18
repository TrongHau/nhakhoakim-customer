<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\DashboardRepository;
use Illuminate\Support\Facades\Validator;

class DashboardController extends Controller
{
    protected $dashboardRepo;

    public function __construct(DashboardRepository $dashboardRepo) {
        parent::__construct();
        $this->dashboardRepo = $dashboardRepo;
    }

    public function totalReceipt(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'BranchId' => 'required',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->dashboardRepo->totalReceipt($request->all());

        $results[] = $this->formatData('DashboardTotalReceipt', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function totalReceiptV2(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'BranchId' => 'array',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->dashboardRepo->totalReceiptV2($request->all());

        $results[] = $this->formatData('DashboardTotalReceiptV2', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function totalAppointment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->dashboardRepo->totalAppointment($request->all());
        $results[] = $this->formatData('DashboardTotalAppointment', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function totalAppointmentV2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->dashboardRepo->totalAppointmentV2($request->all());
        $results[] = $this->formatData('DashboardTotalAppointmentV2', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function listRating(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->dashboardRepo->listRating($request->all());
        $results[] = $this->formatData('DashboardListRating', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function totalRating(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->dashboardRepo->totalRating($request->all());
        $results[] = $this->formatData('DashboardTotalRating', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function totalRatingV2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'array',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->dashboardRepo->totalRatingV2($request->all());
        $results[] = $this->formatData('DashboardTotalRatingV2', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function listConsultationService(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->dashboardRepo->listConsultationService($request->all());
        if (!empty($data)) {

            $totalConsultationImplant = 0;
            $totalConsultationOrthodontic = 0;
            $totalConsultationProsthetic = 0;
            $totalConsultationGenerality = 0;

            foreach ($data['ConsultationService'] ?? [] as $item) {
                switch ($item['WarrantyType']) {
                    case 'I':
                        $totalConsultationImplant++;
                        break;
                    case 'P':
                        $totalConsultationProsthetic++;
                        break;
                    case 'O':
                        $totalConsultationOrthodontic++;
                        break;
                    case NULL:
                        $totalConsultationGenerality += 1;
                        break;
                }
            }

            $totalAgreementImplant = 0;
            $totalAgreementOrthodontic = 0;
            $totalAgreementProsthetic = 0;
            $totalAgreementGenerality = 0;

            foreach ($data['AgreementService'] ?? [] as $item) {
                switch ($item['WarrantyType']) {
                    case 'I':
                        $totalAgreementImplant++;
                        break;
                    case 'P':
                        $totalAgreementProsthetic++;
                        break;
                    case 'O':
                        $totalAgreementOrthodontic++;
                        break;
                    case NULL:
                        $totalAgreementGenerality += 1;
                        break;
                }
            }

            $response = [
                'TotalConsultationImplant' => $totalConsultationImplant,
                'TotalConsultationOrthodontic' => $totalConsultationOrthodontic,
                'TotalConsultationProsthetic' => $totalConsultationProsthetic,
                'TotalConsultationGenerality' => $totalConsultationGenerality,

                'TotalAgreementImplant' => $totalAgreementImplant,
                'TotalAgreementOrthodontic' => $totalAgreementOrthodontic,
                'TotalAgreementProsthetic' => $totalAgreementProsthetic,
                'TotalAgreementGenerality' => $totalAgreementGenerality
            ];

            $results = [];
            $results[] = $this->formatData('DashboardListConsultationService', $response, 'Grid');
            return $this->json($results, 'views');
        }

        $empty = [
            'TotalConsultationImplant' => 0,
            'TotalConsultationOrthodontic' => 0,
            'TotalConsultationProsthetic' => 0,
            'TotalConsultationGenerality' => 0,

            'TotalAgreementImplant' => 0,
            'TotalAgreementOrthodontic' => 0,
            'TotalAgreementProsthetic' => 0,
            'TotalAgreementGenerality' => 0
        ];

        $results = [];
        $results[] = $this->formatData('DashboardListConsultationService', $empty, 'Grid');
        return $this->json($results, 'views');
    }


    public function listConsultationServiceV2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->dashboardRepo->listConsultationServiceV2($request->all());
        if (!empty($data)) {

            $totalConsultationImplant = 0;
            $totalConsultationOrthodontic = 0;
            $totalConsultationProsthetic = 0;
            $totalConsultationGenerality = 0;
            $arrServiceIdConsultation = [];
            foreach ($data['ConsultationService'] ?? [] as $item) {
                switch ($item['WarrantyType']) {
                    case 'I':
                        $totalConsultationImplant++;
                        break;
                    case 'P':
                        $totalConsultationProsthetic++;
                        break;
                    case 'O':
                        $totalConsultationOrthodontic++;
                        break;
                    case NULL:
                        if(!in_array($item['ServiceId'].'_'.$item['OrderChangingId'], $arrServiceIdConsultation)){
                            $arrServiceIdConsultation[] = $item['ServiceId'].'_'.$item['OrderChangingId'];
                            $totalConsultationGenerality += 1;
                        }
                        break;
                }
            }

            $totalAgreementImplant = 0;
            $totalAgreementOrthodontic = 0;
            $totalAgreementProsthetic = 0;
            $totalAgreementGenerality = 0;
            $arrServiceIdAgreement = [];
            foreach ($data['AgreementService'] ?? [] as $item) {
                switch ($item['WarrantyType']) {
                    case 'I':
                        $totalAgreementImplant++;
                        break;
                    case 'P':
                        $totalAgreementProsthetic++;
                        break;
                    case 'O':
                        $totalAgreementOrthodontic++;
                        break;
                    case NULL:
                        if(!in_array($item['ServiceId'].'_'.$item['OrderChangingId'], $arrServiceIdAgreement)){
                            $arrServiceIdAgreement[] = $item['ServiceId'].'_'.$item['OrderChangingId'];
                            $totalAgreementGenerality += 1;
                        }
                        break;
                }
            }

            $KPIImplant = 0;
            $KPIOrthodontic = 0;
            $KPIProsthetic = 0;
            $KPIGenerality = 0;

            foreach ($data['CRMTargetKPI'] ?? [] as $item) {
                $KPIImplant += $item['ImplantAmount'] ?? 0;
                $KPIOrthodontic += $item['OrthodonticAmount'] ?? 0;
                $KPIProsthetic += $item['ProstheticAmount'] ?? 0;
                $KPIGenerality += $item['GeneralityAmount'] ?? 0;
            }

            $response = [
                'TotalConsultationImplant' => $totalConsultationImplant,
                'TotalConsultationOrthodontic' => $totalConsultationOrthodontic,
                'TotalConsultationProsthetic' => $totalConsultationProsthetic,
                'TotalConsultationGenerality' => $totalConsultationGenerality,

                'TotalAgreementImplant' => $totalAgreementImplant,
                'TotalAgreementOrthodontic' => $totalAgreementOrthodontic,
                'TotalAgreementProsthetic' => $totalAgreementProsthetic,
                'TotalAgreementGenerality' => $totalAgreementGenerality,
                
                'KPIImplant' => $KPIImplant,
                'KPIOrthodontic' => $KPIOrthodontic,
                'KPIProsthetic' => $KPIProsthetic,
                'KPIGenerality' => $KPIGenerality
            ];

            $results = [];
            $results[] = $this->formatData('DashboardListConsultationServiceV2', $response, 'Grid');
            return $this->json($results, 'views');
        }

        $empty = [
            'TotalConsultationImplant' => 0,
            'TotalConsultationOrthodontic' => 0,
            'TotalConsultationProsthetic' => 0,
            'TotalConsultationGenerality' => 0,

            'TotalAgreementImplant' => 0,
            'TotalAgreementOrthodontic' => 0,
            'TotalAgreementProsthetic' => 0,
            'TotalAgreementGenerality' => 0,

            'KPIImplant' => 0,
            'KPIOrthodontic' => 0,
            'KPIProsthetic' => 0,
            'KPIGenerality' => 0
        ];

        $results = [];
        $results[] = $this->formatData('DashboardListConsultationServiceV2', $empty, 'Grid');
        return $this->json($results, 'views');
    }

    public function listBranch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $listReceiptV2 = $this->dashboardRepo->listReceiptByBranch($request->all());
        $data = $this->dashboardRepo->listConsultationServiceV2($request->all());
        
        $branchMap = [];
        $branchCodeMap = [];

        if (!empty($data)) {
            $arrServiceIdConsultation = [];
            $arrServiceIdAgreement = [];
            foreach ($data['ConsultationService'] ?? [] as $item) {

                $branchId = $item['BranchId'];
                $branchCode = $item['BranchCode'];
                $type     = $item['WarrantyType'];
                if ($type == null || $type == '') {
                    $type = 'G';
                }
                $qty      = 1;

                if (!isset($branchMap[$branchId][$type])) {
                    $branchMap[$branchId][$type] = [
                        'consult' => 0,
                        'success' => 0
                    ];
                }
                if($type == 'G' || $type == ''){
                    if(!in_array($item['ServiceId'].'_'.$item['OrderChangingId'], $arrServiceIdConsultation)){
                        $arrServiceIdConsultation[] = $item['ServiceId'].'_'.$item['OrderChangingId'];
                        $branchMap[$branchId][$type]['consult'] += $qty;
                    }
                } else {
                    $branchMap[$branchId][$type]['consult'] += $qty;
                }
                $branchCodeMap[$branchId] = $branchCode;
            }

            foreach ($data['AgreementService'] ?? [] as $item) {

                $branchId = $item['BranchId'];
                $branchCode = $item['BranchCode'];
                $type     = $item['WarrantyType'];
                if ($type == null || $type == '') {
                    $type = 'G';
                }
                $qty      = 1;

                if (!isset($branchMap[$branchId][$type])) {
                    $branchMap[$branchId][$type] = [
                        'consult' => 0,
                        'success' => 0
                    ];
                }

                if($type == 'G' || $type == ''){
                    if(!in_array($item['ServiceId'].'_'.$item['OrderChangingId'], $arrServiceIdAgreement)){
                        $arrServiceIdAgreement[] = $item['ServiceId'].'_'.$item['OrderChangingId'];
                        $branchMap[$branchId][$type]['success'] += $qty;
                    }
                } else {
                    $branchMap[$branchId][$type]['success'] += $qty;
                }

                $branchCodeMap[$branchId] = $branchCode;
            }
        }

        $receiptMap = [];
        $visitorMap = [];
        $priorityMap = [];
        $branchCodeMap = [];
        foreach ($listReceiptV2['ListReceipt'] as $item) {
            $receiptMap[$item['BranchId']] = $item['TotalAmount'];
            $visitorMap[$item['BranchId']] = $item['TotalVisitor'] ?? 0;
            $priorityMap[$item['BranchId']] = $item['Priority'] ?? 0;
            $branchCodeMap[$item['BranchId']] = $item['BranchCode'] ?? null;
        }

        $targetValueMap = [];
        foreach ($listReceiptV2['TargetValue'] as $item) {
            $targetValueMap[$item['BranchId']] = $item['TotalTargetRevenue'];
        }

        $result = [];

        $branchIds = array_unique(array_merge(
            array_keys($receiptMap),
            array_keys($targetValueMap)
        ));

        foreach ($branchIds as $branchId) {
            $receipt     = $receiptMap[$branchId] ?? 0;
            $targetValue = $targetValueMap[$branchId] ?? 0;
            $result[] = [
                'BranchId'    => $branchId,
                'TotalAmount'=> $receipt,
                'TargetValue'=> $targetValue
            ];
        }

        $final = [];

        foreach ($result as $row) {
            $branchId = $row['BranchId'];
            $totalVisitor = $visitorMap[$branchId] ?? 0;
            $infoBranch = $this->dashboardRepo->mapBranchCodeName($branchId);
            $branchCode = $infoBranch->BranchCode ?? NULL;
            $priority = $infoBranch->Priority ?? 0;

            $finalRow = [
                'BranchId'     => $branchId,
                'BranchCode'   => $branchCode,
                'TotalVisitor' => $totalVisitor,
                'TotalAmount' => $row['TotalAmount'],
                'TargetValue' => $row['TargetValue'],
                'Priority' => $priority,
                // default
                'G' => '0 | 0',
                'P' => '0 | 0',
                'I' => '0 | 0',
                'O' => '0 | 0',
            ];

            if (isset($branchMap[$branchId])) {
                foreach (['G', 'P', 'I', 'O'] as $type) {
                    if (isset($branchMap[$branchId][$type])) {
                        $consult = $branchMap[$branchId][$type]['consult'] ?? 0;
                        $success = $branchMap[$branchId][$type]['success'] ?? 0;

                        $finalRow[$type] =  $success. ' | ' . $consult;
                    }
                }
            }

            $final[] = $finalRow;
        }
        usort($final, function ($a, $b) {
            return $a['Priority'] <=> $b['Priority'];
        });

        $results[] = $this->formatData('DashboardListBranch', $final, 'Grid');
        return $this->json($results, 'views');
    }

    public function listTreatmentByDoctor(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->dashboardRepo->listTreatmentByDoctor($request->all());
        $results[] = $this->formatData('DashboardListTreatmentByDoctor', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function totalCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->dashboardRepo->totalCustomer($request->all());
        $results[] = $this->formatData('DashboardTotalCustomer', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function listAppointmentSource(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->dashboardRepo->listAppointmentSource($request->all());
        $results[] = $this->formatData('ListAppointmentSource', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function totalReceiptByStaff(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->dashboardRepo->totalReceiptByStaff($request->all());

        $results[] = $this->formatData('TotalReceiptByStaff', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function totalReceiptByStaffV2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->dashboardRepo->totalReceiptByStaffV2($request->all());

        $results[] = $this->formatData('TotalReceiptByStaffV2', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function totalReceiptByDoctor(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->dashboardRepo->totalReceiptByDoctor($request->all());

        $results[] = $this->formatData('TotalReceiptByDoctor', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function totalReceiptByDoctorV2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->dashboardRepo->totalReceiptByDoctorV2($request->all());

        $results[] = $this->formatData('TotalReceiptByDoctorV2', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function insertBranchDaily(Request $request)
    {
        $data = $this->dashboardRepo->insertBranchDaily($request->all());
        $results[] = $this->formatData('InsertBranchDaily', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function insertBranchServiceDaily(Request $request)
    {
        $data = $this->dashboardRepo->insertBranchServiceDaily($request->all());
        $results[] = $this->formatData('InsertBranchServiceDaily', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function insertCustomerRatingDaily(Request $request)
    {
        $data = $this->dashboardRepo->insertCustomerRatingDaily($request->all());
        $results[] = $this->formatData('InsertCustomerRatingDaily', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function insertCustomerSourceSummary(Request $request)
    {
        $data = $this->dashboardRepo->insertCustomerSourceSummary($request->all());
        $results[] = $this->formatData('InsertCustomerSourceSummary', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function insertStaffEffectiveDaily(Request $request)
    {
        $data = $this->dashboardRepo->insertStaffEffectiveDaily($request->all());
        $results[] = $this->formatData('InsertStaffEffectiveDaily', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function insertDoctorEffectiveDaily(Request $request)
    {
        $data = $this->dashboardRepo->insertDoctorEffectiveDaily($request->all());
        $results[] = $this->formatData('InsertDoctorEffectiveDaily', $data, 'Grid');
        return $this->json($results, 'views');
    }

    /**
     * Refresh tất cả dữ liệu dashboard
     * Gọi tất cả các insert functions theo thứ tự
     */
    public function refreshDashboardData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate' => 'nullable|date',
            'ToDate' => 'nullable|date',
            'BranchId' => 'array'
        ]);
        
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $data = $this->dashboardRepo->refreshDashboardData($request->all());
        $results[] = $this->formatData('RefreshDashboardData', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function getPromotionByService(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate' => 'required|date',
            'ToDate' => 'required|date',
            'BranchId' => 'array'
        ]);
        
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $result = $this->dashboardRepo->getPromotionByService($request->all());
        $response[] = $this->formatData('PromotionByService', $result);

        return $this->json($response);
    }

    public function getPromotionByType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate' => 'required|date',
            'ToDate' => 'required|date',
            'BranchId' => 'array'
        ]);
        
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $result = $this->dashboardRepo->getPromotionByType($request->all());
        $response[] = $this->formatData('PromotionByType', $result);

        return $this->json($response);
    }

    public function getPromotionByBranch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate' => 'required|date',
            'ToDate' => 'required|date',
            'BranchId' => 'array'
        ]);
        
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $result = $this->dashboardRepo->getPromotionByBranch($request->all());
        $response[] = $this->formatData('PromotionByBranch', $result);

        return $this->json($response);
    }

    public function getTopCustomersByPromotion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate' => 'required|date',
            'ToDate' => 'required|date',
            'BranchId' => 'array'
        ]);
        
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $result = $this->dashboardRepo->getTopCustomersByPromotion($request->all());
        $response[] = $this->formatData('TopCustomersByPromotion', $result);

        return $this->json($response);
    }

    public function getPromotionSummary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate' => 'required|date',
            'ToDate' => 'required|date',
            'BranchId' => 'array'
        ]);
        
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $result = $this->dashboardRepo->getPromotionSummary($request->all());
        $response[] = $this->formatData('PromotionSummary', $result);

        return $this->json($response);
    }

    public function insertPromotionDashboard(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate' => 'required|date',
            'ToDate' => 'required|date',
            'BranchId' => 'array'
        ]);
        
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $data = $this->dashboardRepo->insertPromotionDashboard($request->all());
        $results[] = $this->formatData('InsertPromotionDashboard', $data, 'Grid');
        return $this->json($results, 'views');
    }

    /**
     * API: Lượt khách checkin bình quân theo thứ
     * Trả về data giả cho UI
     */
    public function getCheckinByWeekday(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required|integer',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->dashboardRepo->getCheckinByWeekday($request->all());
        $results[] = $this->formatData('CheckinByWeekday', $data, 'Grid');
        return $this->json($results, 'views');
    }

    /**
     * API: Ghế điều trị sử dụng bình quân theo thứ
     * Trả về data giả cho UI
     */
    public function getChairUsageByWeekday(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required|integer',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->dashboardRepo->getChairUsageByWeekday($request->all());
        $results[] = $this->formatData('ChairUsageByWeekday', $data, 'Grid');
        return $this->json($results, 'views');
    }

    /**
     * API: Lượt khách checkin theo giờ trong ngày
     * Trả về data giả cho UI
     */
    public function getCheckinByHour(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required|integer',
            'Weekday' => 'required|string',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->dashboardRepo->getCheckinByHour($request->all());
        $results[] = $this->formatData('CheckinByHour', $data, 'Grid');
        return $this->json($results, 'views');
    }

    /**
     * API: Ghế sử dụng theo giờ trong ngày
     * Trả về data giả cho biểu đồ "Ghế sử dụng / giờ"
     */
    public function getHourlyUsage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required|integer',
            'Weekday' => 'required|string',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $data = $this->dashboardRepo->getHourlyUsage($request->all());
        $results[] = $this->formatData('HourlyUsage', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function getCheckinWeekdayByHour(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required|integer',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->dashboardRepo->getCheckinWeekdayByHour($request->all());
        $results[] = $this->formatData('CheckinWeekdayByHour', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function getAverageStayTime(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required|integer',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date'
        ]);
        
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->dashboardRepo->getAverageStayTime($request->all());
        $results[] = $this->formatData('AverageStayTime', $data, 'Grid');
        return $this->json($results, 'views');
    }

    

    public function countAppointment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required|integer',
            'FromDate' => 'required|date',
            'ToDate' => 'required|date',
            'DoctorId' => 'int'
        ]);
        
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->dashboardRepo->countAppointment($request->all());
        $results[] = $this->formatData('CountAppointment', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function totalCustomerOrthodontic(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'StaffId'           => 'required|int'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->dashboardRepo->totalCustomerOrthodontic($request->all());
        $results[] = $this->formatData('TotalCustomerOrthodontic', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function totalCustomerOrthodonticByStep(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'StaffId'           => 'required|int'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->dashboardRepo->totalCustomerOrthodonticByStep($request->all());

        $results[] = $this->formatData('TotalCustomerOrthodonticByStep', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function totalCustomerOrthodonticByStepDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Keyword'           => 'nullable|string',
            'Stage'             => 'nullable|integer|between:0,5',
            'StaffId'           => 'required|integer',
            'CurrentWorkProfilePositionId' => 'required|integer'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        // Truyền toàn bộ input sang hàm xử lý logic dưới DB
        $data = $this->dashboardRepo->getTotalCustomerOrthodonticByStepDetailData($request->all());

        $results[] = $this->formatData('TotalCustomerOrthodonticByStepDetail', $data, 'Grid');
        return $this->json($results, 'views');
    }

}