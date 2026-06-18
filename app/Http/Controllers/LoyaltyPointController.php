<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\LoyaltyPointRepository;
use Illuminate\Support\Facades\Validator;

class LoyaltyPointController extends Controller
{
    /**
     * LoyaltyPointRepository
     * @var LoyaltyPointRepository
     */
    protected $loyaltyPointRepo;

    public function __construct(LoyaltyPointRepository $loyaltyPointRepo)
    {
        parent::__construct();
        $this->loyaltyPointRepo = $loyaltyPointRepo;
    }

    public function getPointByCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CustomerId'    => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->loyaltyPointRepo->getPointByCustomer($request->all());
        $result[] = $this->formatData("LoyaltyPoint", $data);
        return $this->json($result, 'views');
    }

    public function getPointDetailByCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CustomerId'    => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->loyaltyPointRepo->getPointDetailByCustomer($request->all());
        $result[] = $this->formatData("LoyaltyPointDetail", $data);
        return $this->json($result, 'views');
    }
}
