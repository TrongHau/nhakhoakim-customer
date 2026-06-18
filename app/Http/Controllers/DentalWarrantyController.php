<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\DentalWarrantyRepository;
use Illuminate\Support\Facades\Validator;

class DentalWarrantyController extends Controller
{
    protected $dentalWarrantyRepo;

    public function __construct(DentalWarrantyRepository $dentalWarrantyRepo) {
        parent::__construct();
        $this->dentalWarrantyRepo = $dentalWarrantyRepo;
    }

    public function findByCustomerId(Request $request) {

        //Validation
        $validator = Validator::make($request->all(), [
            'CustomerId' => 'required|numeric',
            'SpecializationCode' => 'nullable',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->formatValidationMessages($errors->all());
            return $this->json(false, 'bool');
        }

        $warraties = $this->dentalWarrantyRepo->getWarrantyRecords($request->get('CustomerId'), $request->all());

        $result[] = $this->formatData('DentalWarrantyByCustomer',$warraties);
        return $this->json($result, 'views');
    }
}
