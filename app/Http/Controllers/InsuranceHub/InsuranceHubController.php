<?php

namespace App\Http\Controllers\InsuranceHub;

use App\Http\Controllers\Controller;
use App\InsuranceHub\InsuranceDriver;
use Illuminate\Http\Request;

class InsuranceHubController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    // GET /customer/insurance-hub/providers/{code}/request-workflow
    public function getRequestWorkflow(Request $request, string $code)
    {
        try {
            $steps     = InsuranceDriver::for($code)->getRequestWorkflow();
            $results[] = $this->formatData('InsuranceRequestWorkflow', ['steps' => $steps], 'Grid');
            return $this->json($results, 'views');
        } catch (\Exception $e) {
            $this->addMessage($e->getMessage(), 'ERR500', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }

    // GET /customer/insurance-hub/providers/{code}/claim-workflow
    public function getClaimWorkflow(Request $request, string $code)
    {
        try {
            $steps     = InsuranceDriver::for($code)->getClaimWorkflow();
            $results[] = $this->formatData('InsuranceClaimWorkflow', ['steps' => $steps], 'Grid');
            return $this->json($results, 'views');
        } catch (\Exception $e) {
            $this->addMessage($e->getMessage(), 'ERR500', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }
}
