<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\CountryDialInfoRepository;
use Illuminate\Support\Facades\Validator;

class CountryDialInfoController extends Controller
{
    protected $countryDialInfoRepo;

    public function __construct()
    {
        parent::__construct();
        $this->countryDialInfoRepo = new CountryDialInfoRepository();
    }

    public function getAll()
    {
        $data = $this->countryDialInfoRepo->getAll();

        $results[] = $this->formatData('CountryDialInfo', $data, 'Grid');
        return $this->json($results, 'views');
    }
}
