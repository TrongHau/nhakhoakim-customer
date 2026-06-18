<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Imports\RatingSummaryImport;
use App\Repositories\RatingRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class RatingController extends Controller
{
    /**
     * @var RatingRepository
     */
    protected $ratingRepo;


    public function __construct(RatingRepository $ratingRepo) {
        parent::__construct();
        $this->ratingRepo = $ratingRepo;
    }

    public function detail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'RatingId' => 'required|integer'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $detail = $this->ratingRepo->getRatingDetail($request->get('RatingId'));
        if ($detail && isset($detail->Note) && !empty($detail->Note)) {
            $detail->Note = nl2br($detail->Note);
        }
        $results[] = $this->formatData("RatingDetail", $detail);
        return $this->json($results);
    }

    public function updateCustomerRatingWeb(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'RatingId' => 'required|integer'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $result = $this->ratingRepo->updateCustomerRatingWeb($request->get('RatingId'),$request->get('CustomerId'));

        if ($result) {
            $this->addMessage("Chỉnh thông tin đánh giá thành công", 'SUCC001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage("Chỉnh thông tin đánh giá thất bại", 'ERR001', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function importSummaryByMonth(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Month' => 'required|date', //2026-02-01
            'File' => 'required|file'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $result = true;
        try {
            $file = $request->file('File');
            $ratingImport = new RatingSummaryImport($request->get('Month', date('Y-m-01')));
            Excel::import($ratingImport, $file);
        } catch (\Exception $ex) {
            Log::error("Import summary by month fail", [$ex->getMessage()]);
            $result = false;
        }
        if ($result && $ratingImport->getTotalRows() < 1) {
            $this->addMessage("Import điểm đánh giá thất bại. Không có dữ liệu để import.", 'ERR003', self::$ERROR);
            return $this->json(false, 'bool');
        }
        if ($result && $ratingImport->getInvalidCount() < 1) {
            $this->addMessage("Import điểm đánh giá thành công", 'SUCC002', self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        if ($result && $ratingImport->getInvalidCount() > 0) {
            $this->addMessage("Import thành công. Có ".$ratingImport->getInvalidCount()." dòng dữ liệu không đúng");
            return $this->json(true, 'bool');
        }
        $this->addMessage("Import điểm đánh giá thất bại", 'ERR002', self::$ERROR);
        return $this->json(false, 'bool');
    }
}
