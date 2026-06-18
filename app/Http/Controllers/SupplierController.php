<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repositories\SupplierRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierController extends Controller
{
    protected $supplierRepo;

    public function __construct(SupplierRepository $supplierRepo)
    {
        parent::__construct();
        $this->supplierRepo = $supplierRepo;
    }

    /**
     * POST /supplier/list — Danh sách NCC.
     */
    public function index(Request $request)
    {
        $suppliers = $this->supplierRepo->search($request->all());
        $response[] = $this->formatPagination('SupplierList', $suppliers, 'Grid');

        return $this->json($response);
    }

    /**
     * POST /supplier/detail — Chi tiết NCC.
     */
    public function show(Request $request)
    {
        $id       = (int) $request->input('SupplierId');
        $supplier = $this->supplierRepo->find($id);

        if (!$supplier) {
            $this->addMessage('Không tìm thấy nhà cung cấp', 'ERR003', self::$ERROR);
            return $this->json(null, 'views', 404);
        }

        $response[] = $this->formatData('SupplierDetail', $supplier);

        return $this->json($response);
    }

    /**
     * POST /supplier/create — Tạo NCC mới.
     */
    public function store(Request $request)
    {
        $data = $request->all();

        // Nếu UI truyền SupplierCode thì check trùng, còn null thì bỏ qua
        if (!empty($data['SupplierCode'])) {
            if ($this->supplierRepo->isSupplierCodeExists($data['SupplierCode'])) {
                $this->addMessage('Mã nhà cung cấp đã tồn tại', 'ERR002', self::$ERROR);
                return $this->json(null, 'views', 422);
            }
        }

        if (!empty($data['SupplieTaxCoderCode'])) {
            if ($this->supplierRepo->isSupplierTaxCodeExists($data['TaxCode'])) {
                $this->addMessage('Mã số thuế đã tồn tại', 'ERR002', self::$ERROR);
                return $this->json(null, 'views', 422);
            }
        }

        $data['CreatedBy']   = Auth::user()['StaffId'] ?? 0;
        $data['CreatedDate'] = Carbon::now();

        $supplierId = $this->supplierRepo->insertGetId($data);
        $supplier   = $this->supplierRepo->find($supplierId);

        $response[] = $this->formatData('SupplierDetail', $supplier);
        $this->addMessage('Tạo nhà cung cấp thành công', 'SUC001', self::$SUCCESS);

        return $this->json($response, 'views', 201);
    }

    /**
     * POST /supplier/update — Cập nhật NCC.
     */
    public function update(Request $request)
    {
        $id       = (int) $request->input('SupplierId');
        $supplier = $this->supplierRepo->find($id);

        if (!$supplier) {
            $this->addMessage('Không tìm thấy nhà cung cấp', 'ERR003', self::$ERROR);
            return $this->json(false, 'views', 404);
        }

        $data = $request->all();
        unset($data['SupplierId']);

        // Kiểm tra trùng SupplierCode nếu có thay đổi
        if (!empty($data['SupplierCode']) && $this->supplierRepo->isSupplierCodeExists($data['SupplierCode'], $id)) {
            $this->addMessage('Mã nhà cung cấp đã tồn tại', 'ERR002', self::$ERROR);
            return $this->json(false, 'views', 422);
        }

        // Kiểm tra trùng SupplierCode nếu có thay đổi
        if (!empty($data['TaxCode']) && $this->supplierRepo->isSupplierTaxCodeExists($data['TaxCode'], $id)) {
            $this->addMessage('Mã số thuế đã tồn tại', 'ERR002', self::$ERROR);
            return $this->json(false, 'views', 422);
        }

        $data['UpdatedBy']   = Auth::user()['StaffId'] ?? 0;
        $data['UpdatedDate'] = Carbon::now();

        $this->supplierRepo->updateById($id, $data);

        $supplier   = $this->supplierRepo->find($id);
        $response[] = $this->formatData('SupplierDetail', $supplier);
        $this->addMessage('Cập nhật nhà cung cấp thành công', 'SUC002', self::$SUCCESS);

        return $this->json($response);
    }

    /**
     * POST /supplier/toggle-state — Ngưng / Kích hoạt NCC.
     */
    public function toggleState(Request $request)
    {
        $result = $this->supplierRepo->toggleState((int) $request->input('SupplierId'));

        if (!$result) {
            $this->addMessage('Không tìm thấy nhà cung cấp', 'ERR003', self::$ERROR);
            return $this->json(null, 'views', 404);
        }

        $this->addMessage('Cập nhật trạng thái nhà cung cấp thành công', 'SUC003', self::$SUCCESS);

        return $this->json(null);
    }

    /**
     * POST /supplier/list-supplier-by-product — Danh sách NCC ưu tiên.
     */
    public function listSupplierByProduct(Request $request)
    {
        $productId = (int) $request->input('ProductId');

        $result = $this->supplierRepo->listSupplierByProduct($productId);

        $response[] = $this->formatData('SupplierByProduct', $result, 'Grid');

        return $this->json($response);
    }
}
