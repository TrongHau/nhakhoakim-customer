<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\ReportBranchRepository;
use Illuminate\Support\Facades\Validator;

class ReportBranchController extends Controller
{
    protected $reportBranchRepo;

    public function __construct(ReportBranchRepository $reportBranchRepo)
    {
        parent::__construct();
        $this->reportBranchRepo = $reportBranchRepo;
    }

    public function getDailyActive(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'nullable|integer',
            'lmstart' => 'nullable',
            'limit' => 'nullable',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $data = $this->reportBranchRepo->getDailyActive($request->all());
        foreach ($data as &$item) {
            $item = $this->convertReportNl2br($item);
        }
        $results[] = $this->formatPagination('ReportBranchDailyActive', $data);
        return $this->json($results);
    }

    public function getDailyHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'nullable|integer',
            'lmstart' => 'nullable',
            'limit' => 'nullable',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $data = $this->reportBranchRepo->getDailyHistory($request->all());
        foreach ($data as $item) {
            $item = $this->convertReportNl2br($item);
        }
        $results[] = $this->formatPagination('ReportBranchDailyHistory', $data);
        return $this->json($results);
    }

    public function getDailyDetail(Request $request)
    {
        $this->validate($request, [
            'ReportBranchDailyId' => 'required|integer',
        ]);
        $data = $this->reportBranchRepo->getDailyDetail($request->get('ReportBranchDailyId', 0));
        $data = $this->convertReportNl2br($data);
        $results[] = $this->formatData('ReportBranchDailyDetail', $data);
        return $this->json($results);
    }

    public function createContentReport(Request $request)
    {
        $this->validate($request, [
            'ReportBranchDailyId' => 'required|integer',
            'Content' => 'required',
        ]);
        $data = $this->reportBranchRepo->createContentReport($request->get('ReportBranchDailyId'), $request->get('Content'));
        if ($data) {
            $this->addMessage("Thêm báo cáo thành công", 'SUCC001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage("Thêm báo cáo thất bại", 'ERR001', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function createCommentReport(Request $request)
    {
        $this->validate($request, [
            'ReportBranchDailyId' => 'required|integer',
            'Comment' => 'required',
        ]);
        if (!$this->reportBranchRepo->checkAllowCommentReport($request->get('ReportBranchDailyId'))) {
            $this->addMessage("Báo cáo này đã quá hạn cho phép ý kiến.", 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $data = $this->reportBranchRepo->createCommentReport($request->get('ReportBranchDailyId'), $request->get('Comment'));
        if ($data) {
            $this->addMessage("Thêm ý kiến thành công", 'SUCC001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage("Thêm ý kiến thất bại", 'ERR001', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function createDoctorAssistantContentReport(Request $request)
    {
        $this->validate($request, [
            'ReportBranchDailyId' => 'required|integer',
            'DoctorAssistantContent' => 'required',
        ]);
        $data = $this->reportBranchRepo->createDoctorAssistantContentReport($request->get('ReportBranchDailyId'), $request->get('DoctorAssistantContent'));
        if ($data) {
            $this->addMessage("Thêm báo cáo của phụ tá thành công", 'SUCC001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage("Thêm báo cáo của phụ tá thất bại", 'ERR001', self::$ERROR);
        return $this->json(false, 'bool');
    }

    private function convertReportNl2br($report)
    {
        if (!$report || empty($report)) {
            return $report;
        }
        if (isset($report->Content)) {
            $report->Content = nl2br($report->Content);
        }
        if (isset($report->DoctorAssistantContent)) {
            $report->DoctorAssistantContent = nl2br($report->DoctorAssistantContent);
        }
        if (isset($report->comments) && !empty($report->comments)) {
            foreach ($report->comments as $comment) {
                $comment->CommentDetail = nl2br($comment->CommentDetail);
            }
        }
        return $report;
    }
}
