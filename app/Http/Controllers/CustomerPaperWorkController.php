<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libs\Helper;
use App\Repositories\CustomerPaperWorkRepository;
use Illuminate\Support\Facades\Validator;

class CustomerPaperWorkController extends Controller
{
    protected $customerPaperWorkRepo;

    public function __construct(CustomerPaperWorkRepository $customerPaperWorkRepo) {
        parent::__construct();
        $this->customerPaperWorkRepo = $customerPaperWorkRepo;
    }

    public function findByCustomer(Request $request) {
        //TODO: add validator for customer Id
		$validator = Validator::make($request->all(),[
			'CustomerId'=>'required',
		]);
		if($validator->errors()->count()>0){
			$this->formatValidationMessages($validator->errors()->getMessages());
			return $this->json(false,'bool');
		}
        $data = $this->customerPaperWorkRepo->getPaperWorkByCustomer($request->get('CustomerId'));
        $result[] = $this->formatData('ListPaperWork', $data);
        return $this->json($result);
    }

    public function saveCustomerPaperWork(Request $request) 
    {
        //TODO: add validator for customer Id
		$validator = Validator::make($request->all(),[
			'CustomerId'=> 'required|numeric',
            'PaperName'=> 'required',
            'CustomerPaperTypeId' => 'required|numeric',
            'PaperContent' => 'required',
            'Provider' => 'nullable'
		]);
		if($validator->errors()->count()>0){
			$this->formatValidationMessages($validator->errors()->getMessages());
			return $this->json(false,'bool');
		}
        $result = $this->customerPaperWorkRepo->saveCustomerPaperWork($request->all());
        if ($result) {
            $this->addMessage("Lưu thông tin giấy tờ thành công", 'MSG001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage("Lưu thông tin giấy tờ thất bại", 'MSG002', self::$ERROR);
        return $this->json(false, 'bool');
    }
}
