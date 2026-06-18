<?php

namespace App\Http\Controllers;

use App\Jobs\InsertDepositSendZNSJob;
use App\Jobs\UpdateCRMDepositJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DebugController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testDispatchJobs(Request $request)
    {
        dispatch(new InsertDepositSendZNSJob(0, 0, 0, 0, 'Add'));
        return response()->json([
            'success' => true,
            'message' => 'Jobs dispatched, check queue worker log',
        ]);
    }
}
