<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\TreatmentRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TreatmentController extends Controller
{
    /**
     * @var TreatmentRepository
     */
    protected $treatmentRepo;

    public function __construct(TreatmentRepository $treatmentRepo)
    {
        parent::__construct();
        $this->treatmentRepo = $treatmentRepo;
    }

    public function getTreatmentActive(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CustomerId'    => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $customerId = $request->input('CustomerId');

        try {

            $result = $this->treatmentRepo->getTreatmentActive($customerId);

            return $this->json([$this->formatData('TreatmentActive', $result)]);
        } catch (\Exception $e) {
            Log::error("getTreatmentActive errors", [$e->getMessage()]);
        }
        return $this->json([$this->formatData('TreatmentActive', [])]);
    }

    public function getPrescriptionMedicines(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CustomerId'    => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $customerId = $request->input('CustomerId');

        try {

            $result = $this->treatmentRepo->getPrescriptionMedicines($customerId);

            return $this->json([$this->formatData('PrescriptionMedicines', $result)]);
        } catch (\Exception $e) {
            Log::error("getPrescriptionMedicines errors", [$e->getMessage()]);
        }
        return $this->json([$this->formatData('PrescriptionMedicines', [])]);
    }

    public function addPromotionTreatmentOffer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'TreatmentId'    => 'required|numeric',
            'TreatmentOffer' => 'required|array',
            'TreatmentOffer.*.TreatmentMedicalProcedureOfferId'  => 'required|numeric',
            'TreatmentOffer.*.PromotionId'      => 'required|numeric',
            'TreatmentOffer.*.VoucherCode'      => 'nullable',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $result = $this->treatmentRepo->addPromotionTreatmentOffer($request->input('TreatmentId'), $request->input('TreatmentOffer'));
        if (isset($result['code']) && $result['code'] === true) {
            if (isset($result['message']) && !empty($result['message'])) {
                $this->addMessage($result['message'], 'SUCC002', self::$SUCCESS);
            }
            return $this->json(true, 'bool');
        }
        if (isset($result['message']) && !empty($result['message'])) {
            $this->addMessage($result['message'], 'ERR002', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $this->addMessage('Thêm khuyến mãi không thành công', 'ERR003', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function removePromotionTreatmentOffer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'TreatmentOffer' => 'required|array',
            'TreatmentOffer.*.TreatmentMedicalProcedureOfferId'  => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $result = $this->treatmentRepo->removePromotionTreatmentOffer($request->input('TreatmentOffer'));
        if ($result) {
            $this->addMessage("Xóa khuyến mãi thành công", "SUCC004", self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage('Xóa khuyến mãi thất bại', 'ERR003', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function listTreatment(Request $request){
        $validator = Validator::make($request->all(), [
            'CustomerId'    => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $customerId = $request->input('CustomerId');

        try {

            $result = $this->treatmentRepo->getListTreatment($customerId);

            return $this->json([$this->formatData('ListTreatmentByCustomer', $result)]);
        } catch (\Exception $e) {
            Log::error("getListTreatment errors", [$e->getMessage()]);
        }
        return $this->json([$this->formatData('ListTreatmentByCustomer', [])]);
    }
}
