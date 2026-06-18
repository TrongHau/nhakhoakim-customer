<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repositories\ProductRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    protected $productRepo;

    public function __construct(ProductRepository $productRepo)
    {
        parent::__construct();
        $this->productRepo = $productRepo;
    }

    /**
     * POST /product/list — Danh sách sản phẩm.
     */
    public function index(Request $request)
    {
        $products = $this->productRepo->searchByProductBase($request->all());
        $response[] = $this->formatPagination('ProductList', $products, 'Grid');

        return $this->json($response);
    }

    /**
     * POST /product/detail — Chi tiết sản phẩm.
     */
    public function show(Request $request)
    {
        $id      = (int) $request->input('ProductId');
        $product = $this->productRepo->getDetail($id);

        if (!$product) {
            $this->addMessage('Không tìm thấy sản phẩm', 'ERR003', self::$ERROR);
            return $this->json(null, 'views', 404);
        }

        $response[] = $this->formatData('ProductDetail', $product);

        return $this->json($response);
    }

    /**
     * POST /product/create — Tạo sản phẩm mới.
     */
    public function store(Request $request)
    {
        $data = $request->all();

        if (empty($data['SKU'])) {
            $this->addMessage('Mã SKU không được để trống', 'ERR001', self::$ERROR);
            return $this->json(null, 'views', 422);
        }

        if ($this->productRepo->isSkuExists($data['SKU'])) {
            $this->addMessage('Mã SKU đã tồn tại', 'ERR002', self::$ERROR);
            return $this->json(null, 'views', 422);
        }

        $data['CreatedBy']   = Auth::user()['StaffId'] ?? 0;
        $data['CreatedDate'] = Carbon::now();

        $productId = $this->productRepo->store($data);
        $product   = $this->productRepo->getDetail($productId);

        $response[] = $this->formatData('ProductDetail', $product);
        $this->addMessage('Tạo sản phẩm thành công', 'SUC001', self::$SUCCESS);

        return $this->json($response, 'views', 201);
    }

    /**
     * POST /product/update — Cập nhật sản phẩm.
     */
    public function update(Request $request)
    {
        $id      = (int) $request->input('ProductId');
        $product = $this->productRepo->getDetail($id);

        if (!$product) {
            $this->addMessage('Không tìm thấy sản phẩm', 'ERR003', self::$ERROR);
            return $this->json(null, 'views', 404);
        }

        $data = $request->all();
        unset($data['ProductId']);

        // Kiểm tra trùng SKU nếu có thay đổi
        if (!empty($data['SKU']) && $this->productRepo->isSkuExists($data['SKU'], $id)) {
            $this->addMessage('Mã SKU đã tồn tại', 'ERR002', self::$ERROR);
            return $this->json(null, 'views', 422);
        }

        $data['UpdatedBy']   = Auth::user()['StaffId'] ?? 0;
        $data['UpdatedDate'] = Carbon::now();

        $suppliers = $data['SupplierIds'] ?? null;
        unset($data['SupplierIds']);
        unset($data['Suppliers']);

        $this->productRepo->updateById($id, $data);

        // Cập nhật supplier mapping nếu có truyền
        if ($suppliers !== null) {
            $this->productRepo->updateSupplierMapping($id, $suppliers);
        }

        $product    = $this->productRepo->getDetail($id);
        $response[] = $this->formatData('ProductDetail', $product);
        $this->addMessage('Cập nhật sản phẩm thành công', 'SUC002', self::$SUCCESS);

        return $this->json($response);
    }

    /**
     * POST /product/toggle-state — Ngưng / Kích hoạt sản phẩm.
     */
    public function toggleState(Request $request)
    {
        $result = $this->productRepo->toggleState((int) $request->input('ProductId'));

        if (!$result) {
            $this->addMessage('Không tìm thấy sản phẩm', 'ERR003', self::$ERROR);
            return $this->json(null, 'views', 404);
        }

        $this->addMessage('Cập nhật trạng thái sản phẩm thành công', 'SUC003', self::$SUCCESS);

        return $this->json(null);
    }

    public function listProductBySupplier(Request $request)
    {
        $data = $request->all();
        $product    = $this->productRepo->listProductBySupplier($data);
        $response[] = $this->formatPagination('ProductBySupplier', $product);

        return $this->json($response);
    }
}
