<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\MaterialTreatmentRepository;
use App\Repositories\TreatmentRepository;
use Illuminate\Support\Facades\Validator;

class MaterialTreatmentController extends Controller
{
    /**
     * @var MaterialTreatmentRepository
     */
    protected $materialTreatmentRepo;

    /**
     * @var TreatmentRepository
     */
    protected $treatmentRepo;

    public function __construct(MaterialTreatmentRepository $materialTreatmentRepo, TreatmentRepository $treatmentRepo)
    {
        parent::__construct();
        $this->materialTreatmentRepo = $materialTreatmentRepo;
        $this->treatmentRepo = $treatmentRepo;
    }

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'State' => 'nullable|numeric',
            'Keyword' => 'nullable',
            'BranchId' => 'nullable|numeric',
            'FromDate' => 'nullable|date',
            'ToDate' => 'nullable|date',
            'limit' => 'nullable|numeric',
            'lmstart' => 'nullable|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $data = $this->materialTreatmentRepo->getMaterialTreatmentList($request->all());

        $results[] = $this->formatPagination('MaterialTreatmentList', $data);
        return $this->json($results, 'views');
    }

    public function getListByCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CustomerId' => 'required|numeric',
            'State' => 'nullable|numeric',
            'FromDate' => 'nullable|date',
            'ToDate' => 'nullable|date',
            'limit' => 'nullable|numeric',
            'lmstart' => 'nullable|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $treatmentActive = $this->treatmentRepo->getTreatmentActive($request->get('CustomerId', 0));

        if (!$treatmentActive || empty($treatmentActive)) {
            $results[] =  $this->formatPagination('MaterialTreatmentList', []);
            return $this->json($results, 'views');
        }

        $data = $this->materialTreatmentRepo->getListByTreatment($treatmentActive->TreatmentId, $request->all());

        $results[] = $this->formatPagination('MaterialTreatmentList', $data);
        return $this->json($results, 'views');
    }

    public function detail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ExportMaterialTreatmentId' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $data = $this->materialTreatmentRepo->findById($request->get('ExportMaterialTreatmentId', 0));

        $results[] = $this->formatData('MaterialTreatmentDetail', $data, 'Detail');
        return $this->json($results, 'views');
    }

    public function confirm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ExportMaterialTreatmentId' => 'required|numeric',
            'CurrentUpdatedDate' => 'required|date',
            'ExportMaterialTreatmentDetail' => 'required|array',
            'ExportMaterialTreatmentDetail.*.MaterialId' => 'required|numeric',
            'ExportMaterialTreatmentDetail.*.Quantity' => 'required|numeric',
            'ExportMaterialTreatmentDetail.*.RealQuantity' => 'required|numeric',
            'ExportMaterialTreatmentDetail.*.UnitId' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $exists = $this->materialTreatmentRepo->findByIdAndUpdatedDate($request->get('ExportMaterialTreatmentId', 0), $request->get('CurrentUpdatedDate', ''));
        if (!$exists || empty($exists)) {
            $this->addMessage('Dữ liệu đã được thay đổi trước đó, vui lòng F5 màn hình', 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $result = $this->materialTreatmentRepo->confirmMaterialTreatment($request->get('ExportMaterialTreatmentId', 0), $request->all());

        if ($result) {
            $this->addMessage('Xác nhận thành công', 'SUCC0001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }

        $this->addMessage('Xác nhận thất bại', 'ERR0001', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ExportMaterialTreatmentId' => 'required|numeric',
            'CurrentUpdatedDate' => 'required|date',
            'ExportMaterialTreatmentDetail' => 'required|array',
            'ExportMaterialTreatmentDetail.*.MaterialId' => 'required|numeric',
            'ExportMaterialTreatmentDetail.*.Quantity' => 'required|numeric',
            'ExportMaterialTreatmentDetail.*.RealQuantity' => 'required|numeric',
            'ExportMaterialTreatmentDetail.*.UnitId' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $exists = $this->materialTreatmentRepo->findByIdAndUpdatedDate($request->get('ExportMaterialTreatmentId', 0), $request->get('CurrentUpdatedDate', ''));
        if (!$exists || empty($exists)) {
            $this->addMessage('Dữ liệu đã được thay đổi trước đó, vui lòng F5 màn hình', 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $result = $this->materialTreatmentRepo->updateMaterialTreatment($request->get('ExportMaterialTreatmentId', 0), $request->all());

        if ($result) {
            $this->addMessage('Chỉnh sửa thành công', 'SUCC0001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }

        $this->addMessage('Chỉnh sửa thất bại', 'ERR0001', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function confirmNotUsingMaterial(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ExportMaterialTreatmentId' => 'required|numeric',
            'CurrentUpdatedDate' => 'required|date'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $exists = $this->materialTreatmentRepo->findByIdAndUpdatedDate($request->get('ExportMaterialTreatmentId', 0), $request->get('CurrentUpdatedDate', ''));
        if (!$exists || empty($exists)) {
            $this->addMessage('Dữ liệu đã được thay đổi trước đó, vui lòng F5 màn hình', 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $result = $this->materialTreatmentRepo->confirmNotUsingMaterialTreatment($request->get('ExportMaterialTreatmentId', 0), $request->all());

        if ($result) {
            $this->addMessage('Xác nhận không sử dụng vật tư thành công', 'SUCC0001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }

        $this->addMessage('Xác nhận không sử dụng vật tư thất bại', 'ERR0001', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function detailByTreatmentHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'TreatmentHistoryId' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        return $this->json([], 'views');
        $data = $this->materialTreatmentRepo->findByTreatmentHistory($request->get('TreatmentHistoryId', 0));

        $results[] = $this->formatData('MaterialTreatmentDetail', $data, 'Detail');
        return $this->json($results, 'views');
    }
}