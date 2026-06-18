<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\CustomerGatewayRepository;
use Illuminate\Support\Facades\Log;

class CustomerGatewayController extends Controller
{
    /**
     * @var CustomerGateway
     */
    protected $customerGatewayRepo;

    public function __construct(CustomerGatewayRepository $customerGatewayRepo) {
        parent::__construct();
        $this->customerGatewayRepo = $customerGatewayRepo;
    }

    public function checkCustomerHIS(Request $request)
    {
        $key = $request->input('Key');
        Log::info('CustomerGatewayController@checkCustomerHIS - Key: ' . $key);
        $decoded = base64_decode($key);
        if (strpos($decoded, 'C') === 0) {
            $customerCode = str_replace('C', '', $decoded);
            $checkCustomer = $this->customerGatewayRepo->checkCustomerHIS($customerCode);
            return $this->json($checkCustomer, 'bool');
        } else {
            return $this->json(false, 'bool');
        }
        return $this->json(false, 'bool');
    }

    public function checkUrlHIS(Request $request)
    {
        $key = $request->input('Key');
        Log::info('CustomerGatewayController@checkUrlHIS - Key: ' . $key);
        $decoded = base64_decode($key);
        if (strpos($decoded, 'C') === 0) {
            $customerCode = str_replace('C', '', $decoded);
            $checkCustomer = $this->customerGatewayRepo->checkUrlHIS($customerCode);
            return $this->json($checkCustomer, 'bool');
        } else {
            return $this->json(false, 'bool');
        }
        return $this->json(false, 'bool');
    }

    public function saveCustomerHIS(Request $request)
    {
        $customerInfo = $request->input('CustomerInfo');
        $result = $this->customerGatewayRepo->saveCustomerHIS($customerInfo);
        if ($result) {
            return $this->json(true, 'bool');
        } else {
            return $this->json(false, 'bool');
        }
    }
}