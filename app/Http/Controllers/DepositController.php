<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\DepositRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DepositController extends Controller
{
    /**
     * CustomerRepository
     * @var DepositRepository
     */
    protected $depositRepo;

    public function __construct(DepositRepository $depositRepo) {
        parent::__construct();
        $this->depositRepo = $depositRepo;
    }
}
