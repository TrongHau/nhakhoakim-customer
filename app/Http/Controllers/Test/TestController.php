<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Libs\RedisLib;
use App\Repositories\GatewayRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TestController extends Controller
{
    private $model;
    private $user;

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $user = Auth::user();
        return $this->json([$this->formatData('Authorized User',$user)]);
    }



}
