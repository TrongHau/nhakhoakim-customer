<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repositories\ProductCategoryRepository;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    protected $productCategoryRepo;

    public function __construct(ProductCategoryRepository $productCategoryRepo)
    {
        parent::__construct();
        $this->productCategoryRepo = $productCategoryRepo;
    }

    /**
     * POST /product-category/list — Danh sách danh mục sản phẩm.
     */
    public function index(Request $request)
    {
        $categories = $this->productCategoryRepo->getActiveList();
        $response[] = $this->formatData('ProductCategoryList', $categories);

        return $this->json($response);
    }
}
