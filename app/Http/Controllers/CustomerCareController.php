<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\CustomerCareRepository;
use App\Repositories\TreatmentProgressEvaluationRepository;
use Illuminate\Support\Facades\Validator;

class CustomerCareController extends Controller
{
    /**
     * @var CustomerCareRepository
     */
    protected $customerCareRepo;

    /**
     * @var TreatmentProgressEvaluationRepository
     */
    protected $treatmentEvaluationRepo;


    public function __construct(CustomerCareRepository $customerCareRepo, TreatmentProgressEvaluationRepository $treatmentEvaluationRepo)
    {
        parent::__construct();
        $this->customerCareRepo = $customerCareRepo;
        $this->treatmentEvaluationRepo = $treatmentEvaluationRepo;
    }

    public function getCustomerCareConsulting(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CustomerId'    => 'nullable'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->customerCareRepo->getCustomerCareConsulting($request->all());
        $result[] = $this->formatData('CustomerCareConsulting', $data);
        return $this->json($result);
    }

    public function getCustomerCareConsultingAll(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId'    => 'nullable',
            'ServiceType'    => 'nullable',
            'Keyword'    => 'nullable',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->customerCareRepo->getCustomerCareConsultingAll($request->all());
        if ($data) {
            foreach ($data as $key => $value) {
                $value->Note =  nl2br(str_replace(["\\n", "\\t", "\\r"], ["\n", "\t", "\r"], $value->Note ?? ''));
            }
        }
        $result[] = $this->formatDataPaginationByStore('CustomerCareConsultingAll', $data);
        return $this->json($result);
    }

    public function getCustomerCareConsultingByCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CustomerId'    => 'required'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->customerCareRepo->getCustomerCareConsultingByCustomer($request->all());
        $result[] = $this->formatData('CustomerCareConsultingByCustomer', $data);
        return $this->json($result);
    }

    public function getEvaluationOrthodontic(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'StaffId' => 'nullable|numeric',
            'Keyword' => 'nullable',
            'ProcessState' => 'nullable|numeric',
            'BranchId' => 'nullable|numeric',
            'IsFollowing' => 'nullable|numeric',
            'limit' => 'nullable|numeric',
            'lmstart' => 'nullable|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->treatmentEvaluationRepo->getEvaluationOrthodontic($request->all());
        $result[] = $this->formatDataPaginationByStore('EvaluationOrthodonticList', $data);
        return $this->json($result);
    }

    public function getEvaluationOrthodonticForDoctor(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Keyword' => 'nullable',
            'ProcessState' => 'nullable|numeric',
            'BranchId' => 'nullable|numeric',
            'IsFollowing' => 'nullable|numeric',
            'limit' => 'nullable|numeric',
            'lmstart' => 'nullable|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->treatmentEvaluationRepo->getEvaluationOrthodonticForDoctor($request->all());
        $result[] = $this->formatDataPaginationByStore('EvaluationOrthodonticForDoctorList', $data);
        return $this->json($result);
    }

    public function processEvaluationOrthodontic(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'TreatmentProgressEvaluationId' => 'required|numeric',
            'SelfEvaluation' => 'required',
            'ProcessState' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        // Check if the evaluation exists and has not been updated before
        $existingEvaluation = $this->treatmentEvaluationRepo->find($request->input('TreatmentProgressEvaluationId'));
        if (!$existingEvaluation) {
            $this->addMessage('Đánh giá tiến độ niềng răng không tồn tại', 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        if (isset($existingEvaluation->SelfEvaluation) && !empty($existingEvaluation->SelfEvaluation)) {
            $this->addMessage('Đánh giá tiến độ niềng răng đã được cập nhật trước đó', 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        // Process the evaluation
        $result = $this->treatmentEvaluationRepo->processEvaluationOrthodontic(
            $request->input('TreatmentProgressEvaluationId'),
            $request->all()
        );
        if ($result) {
            $this->addMessage('Cập nhật đánh giá tiến độ niềng răng thành công', 'SUCC001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage('Cập nhật đánh giá tiến độ niềng răng thất bại', 'ERR001', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function detailEvaluationOrthodontic(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'TreatmentProgressEvaluationId' => 'required',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->treatmentEvaluationRepo->detailEvaluationOrthodontic($request->input('TreatmentProgressEvaluationId'));
        $result[] = $this->formatData('DetailEvaluationOrthodontic', $data);
        return $this->json($result);
    }

    public function historyEvaluationOrthodontic(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CustomerId' => 'required',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->treatmentEvaluationRepo->historyEvaluationOrthodontic($request->input('CustomerId'),$request->input('TreatmentMedicalProcedureId'));
        $result[] = $this->formatData('HistoryEvaluationOrthodontic', $data);
        return $this->json($result);
    }

    public function getTreatmentProgress(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'nullable|numeric',
            'ExcludeBranchIds' => 'nullable|array',
            'ExcludeBranchIds.*' => 'nullable|numeric',
            'Keyword' => 'nullable',
            'lmstart' => 'nullable|numeric',
            'limit' => 'nullable|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->customerCareRepo->getCustomerCareTreatmentProgress($request->all());
        $result[] = $this->formatDataPaginationByStore('CustomerCareTreatmentProgress', $data);
        return $this->json($result);
    }

    public function getTreatmentProgressForDoctor(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'nullable|numeric',
            'ExcludeBranchIds' => 'nullable|array',
            'ExcludeBranchIds.*' => 'nullable|numeric',
            'Keyword' => 'nullable',
            'lmstart' => 'nullable|numeric',
            'limit' => 'nullable|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->customerCareRepo->getCustomerCareTreatmentProgressForDoctor($request->all());
        $result[] = $this->formatDataPaginationByStore('CustomerCareTreatmentProgress', $data);
        return $this->json($result);
    }
}
