<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\CategoryRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    protected $categoryRepo;

    public function __construct(CategoryRepository $categoryRepo) {
        parent::__construct();
        $this->categoryRepo = $categoryRepo;
    }

    public function getCategoriesParentChild(Request $request) {
        $data = $this->categoryRepo->getCategoryParentChild();
        $results[] = $this->formatData("CategoriesParentChild", $data);
        return $this->json($results, 'views');
    }

    public function checkCategory(Request $request) {
        $validator = Validator::make($request->all(), [
            'CategoryName' => 'required',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $data = $this->categoryRepo->checkCategory($request->get('CategoryName'));
        $results[] = $this->formatData("Result", $data, 'Grid');
        return $this->json($results, 'views');
    }
}
