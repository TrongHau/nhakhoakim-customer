<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\AllocatedRevenueTrackingRepository;
use App\Repositories\OrderDetailRepository;
use App\Repositories\RatingRepository;
use App\Repositories\ReportLuckyDrawSpinsRepository;
use App\Repositories\ReportRepository;
use App\Repositories\SystemAccessTrackingRepository;
use App\Repositories\TicketSupportRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    /**
     *  
     * @var ReportRepository
     */
    protected $reportRepo;

    /**
     * @var SystemAccessTrackingRepository
     */
    protected $systemAccessTrackingRepo;
    protected $reportLuckyDrawSpins;
    protected $ticketSupportRepo;
    protected $orderDetailRepository;
    protected $ratingRepo;
    protected $allocatedRevenueTrackingRepo;

    public function __construct(
        ReportRepository $reportRepo,
        SystemAccessTrackingRepository $systemAccessTrackingRepo,
        ReportLuckyDrawSpinsRepository $reportLuckyDrawSpins,
        TicketSupportRepository $ticketSupportRepo,
        OrderDetailRepository $orderDetailRepository,
        RatingRepository $ratingRepo,
        AllocatedRevenueTrackingRepository $allocatedRevenueTrackingRepo
    )
    {
        parent::__construct();
        $this->reportRepo = $reportRepo;
        $this->systemAccessTrackingRepo = $systemAccessTrackingRepo;
        $this->reportLuckyDrawSpins = $reportLuckyDrawSpins;
        $this->ticketSupportRepo = $ticketSupportRepo;
        $this->orderDetailRepository = $orderDetailRepository;
        $this->ratingRepo = $ratingRepo;
        $this->allocatedRevenueTrackingRepo = $allocatedRevenueTrackingRepo;
    }

    public function reportMKTCashCheckin(Request $request)
    {
        $data = $this->reportRepo->reportMKTCashCheckin();
        $results[] = $this->formatData('reportMKTCashCheckin', $data);
        return $this->json($results);
    }

    public function doctorTreatmentReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate'      => 'required',
            'ToDate'        => 'required'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->reportRepo->doctorTreatmentReport($request->all());
        $results[] = $this->formatDataPaginationByStore('doctorTreatmentReport', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function getDoctorLevelCriteria()
    {

        $data = $this->reportRepo->getDoctorLevelCriteria();
        $results[] = $this->formatData('getDoctorLevelCriteria', $data, 'Grid');

        return $this->json($results, 'views');
    }

    public function getTrackingCheckIP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lmstart'      => 'nullable',
            'limit'        => 'nullable'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $data = $this->systemAccessTrackingRepo->getTrackingCheckIP($request->all());
        $results[] = $this->formatPagination('TrackingCheckIPList', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function getTrackingAccessCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lmstart'      => 'nullable',
            'limit'        => 'nullable'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $data = $this->systemAccessTrackingRepo->getTrackingAccessCustomer($request->all());
        $results[] = $this->formatPagination('TrackingAccessCustomerList', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function getAdvisoryReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate'      => 'required',
            'ToDate'        => 'required',
            'ServiceId'     => 'nullable',
            'BranchId'      => 'nullable',
            'Keyword'       => 'nullable',
            'lmstart'       => 'nullable',
            'limit'         => 'nullable',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->reportRepo->getAdvisoryReport($request->all());
        $results[] = $this->formatPagination('AdvisoryReport', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function getAdvisoryReportCount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate'      => 'required',
            'ToDate'        => 'required',
            'ServiceId'     => 'nullable',
            'BranchId'      => 'nullable',
            'Keyword'       => 'nullable',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->reportRepo->getAdvisoryReportCount($request->all());
        $results[] = $this->formatData('AdvisoryReportCount', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function getNetCashCollection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate'      => 'required',
            'ToDate'        => 'required',
            'BranchId'        => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->reportRepo->getNetCashCollection($request->all());
        $results[] = $this->formatPagination('NetCashCollection', $data, 'Grid');
        return $this->json($results, 'views');
    }
    
    public function getOrderMeasuringConsulting(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate'      => 'required',
            'ToDate'        => 'required',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $data = $this->reportRepo->getOrderMeasuringConsulting($request->all());
        if ($data) {
            foreach ($data as $v) {
                $arr = [];
                if ($v->GeneralityLevel) {
                    $arr[] = 'Tổng quát';
                }
                if ($v->ProstheticLevel) {
                    $arr[] = 'Phục hình';
                }
                if ($v->ImplantLevel) {
                    $arr[] = 'Implant';
                }
                if ($v->OrthodonticLevel) {
                    $arr[] = 'Chỉnh nha';
                }
                $v->SpecificationText = implode(', ', $arr);
            }
        }
        $results[] = $this->formatPagination('OrderMeasuringConsulting', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function countOrderMeasuringConsulting(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate'      => 'required',
            'ToDate'        => 'required',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $advise = $this->reportRepo->countOrderMeasuringConsulting($request->all(), 1);
        $success = $this->reportRepo->countOrderMeasuringConsulting($request->all(), 10);
        $successRate = (int)$success > 0 ? ((int)$advise / (int)$success) * 100 : 0;

        $countOIP = $this->reportRepo->countOIP($request->all());

        $data = [
            'Advise' => $advise,
            'Success' => $success,
            'Remaining' => (int)$advise - (int)$success,
            'SuccessRate' => (int)$successRate,
        ];

        $data = array_merge($data, $countOIP);

        $results[] = $this->formatData('CountOrderMeasuringConsulting', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function updateOrderMeasuringConsulting(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Id'      => 'required|numeric',
            'TreatmentMedicalProcedureOfferIds'      => 'nullable|array',
            'TreatmentMedicalProcedureOfferIds.*'      => 'required|numeric',
            'Note' => 'nullable'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $result = $this->reportRepo->updateOrderMeasuringConsulting($request->all());

        if ($result) {
            $this->addMessage("Chốt tư vấn thành công.", 'UOMC0001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage("Chốt tư vấn không thành công.", 'UOMC0002', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function getRankCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate'      => 'required',
            'ToDate'        => 'required',
            ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $data = $this->reportRepo->getRankCustomer($request->all());
        $results[] = $this->formatDataPaginationByStore('RankCustomer', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function getOrderMeasuringConsultingServiceDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Id'      => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
    
        $data = $this->reportRepo->getOrderMeasuringConsultingServiceDetail($request->get('Id'));
        $result[] = $this->formatData('OrderMeasuringConsultingServiceDetail', $data, 'Grid');
        return $this->json($result, 'views');
    }

    public function countConsultingPerformance()
    {
        $data = $this->reportRepo->countConsultingPerformance();
        $results[] = $this->formatData('CountConsultingPerformance', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function getConsultingPerformance(Request $request)
    {
        $data = $this->reportRepo->getConsultingPerformance($request->all());
        $results[] = $this->formatData('ConsultingPerformance', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function getConsultingPerformanceByBranch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId'        => 'required',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $data = $this->reportRepo->getConsultingPerformanceByBranch($request->all());
        $results[] = $this->formatData('ConsultingPerformanceByBranch', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function getLuckyDrawSpinsReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'LuckyDrawCampaignId'      => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $data = $this->reportLuckyDrawSpins->getLuckyDrawSpinsReport($request->all());
        $result[] = $this->formatData('LuckyDrawSpinsReport', $data, 'Grid');
        return $this->json($result, 'views');
    }

    public function countCustomerCare(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Day'        => 'required|date',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $data = $this->reportRepo->countCustomerCare($request->all());
        $results[] = $this->formatData('CountCustomerCare', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function getTrackingAccessLoginOutside(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Keyword'      => 'nullable',
            'FromDate'     => 'nullable|date',
            'ToDate'       => 'nullable|date',
            'lmstart'      => 'nullable',
            'limit'        => 'nullable'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $data = $this->systemAccessTrackingRepo->getTrackingAccessLoginOutside($request->all());
        $results[] = $this->formatPagination('TrackingAccessLoginOutside', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function getTrackingVerifyAccessCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Keyword'      => 'nullable',
            'FromDate'     => 'nullable|date',
            'ToDate'       => 'nullable|date',
            'lmstart'      => 'nullable',
            'limit'        => 'nullable'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $data = $this->systemAccessTrackingRepo->getTrackingVerifyAccessCustomer($request->all());
        $results[] = $this->formatPagination('TrackingVerifyAccessCustomer', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function exportTicketSupport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate'     => 'nullable|date',
            'ToDate'       => 'nullable|date',
            'ReceivingOrg' => 'array',
            'ReceivingOrg.*' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $data = $this->ticketSupportRepo->exportTicketSupport($request->all());
        if (!$data) {
            $this->addMessage("Không có dữ liệu trong khoảng thời gian đã chọn.", 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $results[] = $this->formatData('ExportTicketSupport', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function exportConsultedServicesReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate'     => 'nullable|date',
            'ToDate'       => 'nullable|date',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        if (!$this->validateDateRange($request->get('FromDate'), $request->get('ToDate'))) {
            $this->addMessage("Khoảng thời gian không được vượt quá 31 ngày", 'OORERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->orderDetailRepository->exportConsultedServicesReport($request->all());
        if (!$data) {
            $this->addMessage("Không có dữ liệu trong khoảng thời gian đã chọn.", 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $results[] = $this->formatData('ExportConsultedServicesReport', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function exportConsultedSuccessServicesReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate'     => 'nullable|date',
            'ToDate'       => 'nullable|date',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        if (!$this->validateDateRange($request->get('FromDate'), $request->get('ToDate'))) {
            $this->addMessage("Khoảng thời gian không được vượt quá 31 ngày", 'OORERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->orderDetailRepository->exportConsultedServicesByConsultedDateReport($request->all());
        if (!$data) {
            $this->addMessage("Không có dữ liệu trong khoảng thời gian đã chọn.", 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $results[] = $this->formatData('ExportConsultedSuccessServicesReport', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function exportCustomerReviewsReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate'     => 'nullable|date',
            'ToDate'       => 'nullable|date',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        if (!$this->validateDateRange($request->get('FromDate'), $request->get('ToDate'))) {
            $this->addMessage("Khoảng thời gian không được vượt quá 31 ngày", 'OORERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->ratingRepo->exportRatingReport($request->all());
        if (!$data) {
            $this->addMessage("Không có dữ liệu trong khoảng thời gian đã chọn.", 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $results[] = $this->formatData('ExportCustomerReviewsReport', $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function exportProfessionalSupportTreatmentReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate'     => 'nullable|date',
            'ToDate'       => 'nullable|date',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        if (!$this->validateDateRange($request->get('FromDate'), $request->get('ToDate'))) {
            $this->addMessage("Khoảng thời gian không được vượt quá 31 ngày", 'OORERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->allocatedRevenueTrackingRepo->exportTreatmentRevenueReport($request->all());
        if (!$data) {
            $this->addMessage("Không có dữ liệu trong khoảng thời gian đã chọn.", 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $results[] = $this->formatData('ExportProfessionalSupportTreatmentReport', $data, 'Grid');
        return $this->json($results, 'views');
    }

    private function validateDateRange($fromDate, $toDate)
    {
        $from = Carbon::parse($fromDate);
        $to   = Carbon::parse($toDate);

        if ($from->diffInDays($to) > 31) {
            return false;
        }

        return true;
    }

    public function getReceiptsByStaffInMonth(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate' => 'required|date',
            'ToDate' => 'required|date',
            'StaffId' => 'required|numeric',
            'Keyword' => 'nullable',
            'lmstart' => 'nullable',
            'limit' => 'nullable',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->reportRepo->getReceiptsByStaffInMonth($request->all());

        $results[] = $this->formatPagination('ReceiptsByStaffInMonth', $data, 'Grid');
        return $this->json($results, 'views');
    }
}
