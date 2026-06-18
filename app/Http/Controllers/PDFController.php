<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Repositories\PDFRepository;

class PDFController extends Controller
{
    public function addTreatmentPlaning(Request $request) {
        $validator = Validator::make($request->all(), [
            'CustomerId' => 'required',
            'TreatmentId' => 'required',
            'Content' => 'required',
            'BranchId' => 'required'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $customerId = $request->input('CustomerId');
        $treatmentId = $request->input('TreatmentId');
        $content = $request->input('Content');
        $branchId = $request->input('BranchId');
        $PDFRepository = new PDFRepository;
        $data = $PDFRepository->add($customerId, $treatmentId, $content, $branchId);
        $result = [
            'code' => $data
        ];
        $this->addMessage("Tạo kế hoạch điều trị thành công.", 'ERR001', self::$SUCCESS);
        $results[] = $this->formatData('Add Treatment Planing', $result);
        return $this->json($results);
    }

    public function listTreatmentPlan(Request $request) {
        $validator = Validator::make($request->all(), [
            'CustomerId' => 'required'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $customerId = $request->input('CustomerId');
        $PDFRepository = new PDFRepository;
        $list = $PDFRepository->list($customerId);
        $result[] = $this->formatData('List Treatment Plan', $list);
        return $this->json($result);
    }

    public function detail(Request $request) {
        $validator = Validator::make($request->all(), [
            'CustomerId' => 'required'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $customerId = $request->input('CustomerId');
        $PDFRepository = new PDFRepository;
        $infoCustomer = $PDFRepository->detail($customerId);
        $result[] = $this->formatData('Detail Customer', $infoCustomer);
        return $this->json($result);
    }
    public function convertHtmlToPdf(Request $request) {
        
        $validator = Validator::make($request->all(), [
            'Content' => 'required'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $content = $request->input('Content');
        $PDFRepository = new PDFRepository;
        $pathFile = $PDFRepository->convertHtmlToPdf($content);
        $result[] = $this->formatData('convertHtmlToPdf', $pathFile);
        return $this->json($result);
    }
}
