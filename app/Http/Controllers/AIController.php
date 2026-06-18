<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\ConsultationTrainingRepository;
use Illuminate\Support\Facades\Validator;

class AIController extends Controller
{
    protected $consultationTrainingRepo;

    public function __construct(ConsultationTrainingRepository $consultationTrainingRepo) {
        parent::__construct();
        $this->consultationTrainingRepo = $consultationTrainingRepo;
    }

    public function detailConsultationTraining(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Id'    => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $detail = $this->consultationTrainingRepo->find($request->get('Id', 0));
        $results[] = $this->formatData('ConsultationTraining', $detail);
        return $this->json($results, 'views');
    }
}
