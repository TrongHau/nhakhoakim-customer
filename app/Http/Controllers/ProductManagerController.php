<?php

namespace App\Http\Controllers;

use App\Exceptions\ImportExcelException;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Imports\ProductManagerImport;
use App\Libs\Helper;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Repositories\ProductManagerRepository;
use Illuminate\Support\Facades\Log;
use LDAP\Result;

class ProductManagerController extends Controller
{
    protected $productManagerRepo;

    public function __construct(ProductManagerRepository $productManagerRepo)
    {
        Parent::__construct();
        $this->productManagerRepo = $productManagerRepo;
    }

    public function checkSku(Request $request)
    {
        $sku = $request->input('SKU');

        $product = $this->productManagerRepo->checkProductBySku($sku);

        if ($product) {
            $results[] = $this->formatData("Resulrt", false, 'Grid');
            return $this->json($results, 'views');
        }

        $results[] = $this->formatData("Resulrt", true, 'Grid');
        return $this->json($results, 'views');
    }

    public function checkProductName(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ProductName' => 'required|max:250',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $product = $this->productManagerRepo->checkProductByName($request->get('ProductName'));

        if ($product) {
            $results[] = $this->formatData("Result", false, 'Grid');
            return $this->json($results, 'views');
        }

        $results[] = $this->formatData("Result", true, 'Grid');
        return $this->json($results, 'views');
    }

    public function create(Request $request)
    {
        $message = [
            'required' => ':attribute không được bỏ trống.',
            'array' => ':attribute phải là dạng mảng.',
            'ProductName.max' => ':attribute không được lớn hơn 250 ký tự.',
            'Description.max' => ':attribute không được lớn hơn 500 ký tự.',
            'Barcode.max' => ':attribute không được lớn hơn 50 ký tự.',
        ];
        $attribute = [
            'ProductName'    => 'Tên sản phẩm',
            'Price' => 'Giá gốc',
            'SalePrice' => 'Giá bán',
            'BrandId' => 'Nhãn hàng',
            'Unit' => 'Đơn vị',
            'Description' => 'Mô tả',
            'Barcode' => 'Mã vạch',
            'Category' => 'Danh mục',
        ];
        $validator = Validator::make($request->all(), [
            'ProductName' => 'required|max:250',
            'Unit' => 'required|array',
            'Category' => 'required|array',
            'Description' => 'nullable|max:500',
            'Barcode' => 'nullable|max:50',
        ], $message, $attribute);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        // $sku = $request->input('SKU');

        $product = $this->productManagerRepo->checkProductByName($request->get('ProductName'));
        if ($product) {
            $this->addMessage("Tên sản phẩm bị trùng!", 'CP0001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $result = $this->productManagerRepo->save($request->all());

        if ($result) {
            $this->addMessage("Tạo sản phẩm thành công!", 'CP001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }

        $this->addMessage("Tạo sản phẩm không thành công!", 'CP002', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function getListProduct(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Keyword' => 'nullable|string|max:255',
            'SearchType' => 'nullable|numeric',
            'Type' => 'nullable|numeric',
            'lmstart' => 'nullable|numeric',
            'limit' => 'nullable|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $result = $this->productManagerRepo->getListProduct($request->all());

        $results[] = $this->formatPagination("ListProduct", $result, 'Grid');
        return $this->json($results, 'views');
    }

    public function getDetailProduct(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ProductId' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $result = $this->productManagerRepo->getDetailProduct($request->get('ProductId'));

        $results[] = $this->formatData("ProductDetail", $result, 'Grid');
        return $this->json($results, 'views');
    }

    public function getProductLog(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ProductId' => 'required|numeric',
            'lmstart' => 'numeric',
            'limit' => 'numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $prodLog = $this->productManagerRepo->getProductLog($request->all());

        $results[] = $this->formatPagination("ProductHistory", $prodLog, 'Grid');
        return $this->json($results, 'views');
    }

    public function import(Request $request)
    {
        $message = [
            'File' => 'File import'
        ];
        $attribute = [
            'mimes' => 'Định dạng file không đúng. File đính kèm phải là file Excel.'
        ];
        $validator = Validator::make($request->all(), [
            // comment de test
            'File' => 'nullable|file',
            'Products' => 'nullable',
        ], $attribute, $message);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        if ($request->file('File')) {
            $allowFileTypes = ['xls', 'xlsx'];
            $file = $request->file('File');
            if ($file && !in_array(strtolower($file->getClientOriginalExtension()), $allowFileTypes)) {
                $this->addMessage("File import không đúng định dạng. Định dạng đúng .xlsx, .xls", 'ERR001', self::$ERROR);
                return $this->json(false, 'bool');
            }
            // handle import first time
            return $this->processImportFile($request->file('File'));
        } else if ($request->get('Products') && is_array($request->get('Products'))) {
            //handle import after review
            return $this->processReviewImport($request->get('Products'));
        }
        $this->addMessage("Vui lòng chọn file để tải lên", "IPM001", self::$ERROR);
        return $this->json(false, 'bool');
    }

    private function processImportFile($file)
    {
        try {
            $result = $this->productManagerRepo->importFile($file);
            if (isset($result) && is_array($result) && !empty($result)) {
                if ($result["code"]) {
                    $this->addMessage("Import sản phẩm thành công", 'IPM002', self::$SUCCESS);
                    return $this->json(true, 'bool');
                }

                if (isset($result["msg"]) && !empty($result["msg"])) {
                    $this->addMessage($result["msg"], "IPM003", self::$ERROR);
                    return $this->json(false, 'bool');
                }

                if (isset($result["data"]) && !empty($result["data"]) && is_array($result["data"]) && count($result["data"]) > 0) {
                    $rs[] = $this->formatData("ImportData", $result["data"], 'Grid');
                    return $this->json($rs, 'views');
                }
            }
        } catch (\Exception $e) {
            Log::error('Error at controller when handle data: ', [$e->getMessage()]);
        }

        $this->addMessage("File import sai nội dung. Vui lòng kiểm tra lại", 'ERR002', self::$ERROR);
        return $this->json(false, 'bool');
    }

    private function processReviewImport($products)
    {
        $result = $this->productManagerRepo->saveImport($products);
        if ($result) {
            $this->addMessage("Import sản phẩm thành công", 'IPM001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }

        $this->addMessage("Import sản phẩm không thành công. Vui lòng kiểm tra lại", 'IPM004', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function remove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ProductId' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $isEditBlocked = $this->productManagerRepo->isEditBlocked($request->get('ProductId'));
        if ($isEditBlocked) {
            $this->addMessage('Sản phẩm đã tồn tại trong phiếu nhập!', 'RP001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $res = $this->productManagerRepo->removeProduct($request->get('ProductId'), $request->get('UpdatedDate'));
        if ($res) {
            $this->addMessage('Xóa sản phẩm thành công!', 'RP001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage('Xoá sản phẩm thất bại. Vui lòng F5 và thử lại!', 'RP001', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function getListImport(Request $request)
    {
        $rs = $this->productManagerRepo->getListImport($request->all());

        if ($rs) {
            foreach ($rs as $value) {
                $fullName = $value['createdBy']['FullName'];
                unset($value['createdBy']);
                $value['createdBy'] = $fullName;
            }
        }

        return $this->json([$this->formatData("ListImport", $rs)], 'views');
    }

    public function updateProduct(Request $request)
    {
        $message = [
            'required' => ':attribute không được bỏ trống.',
            'array' => ':attribute phải là dạng mảng.',
            'ProductName.max' => ':attribute không được lớn hơn 250 ký tự.',
            'Description.max' => ':attribute không được lớn hơn 500 ký tự.',
            'Barcode.max' => ':attribute không được lớn hơn 50 ký tự.',
        ];
        $attribute = [
            'ProductName' => 'Tên sản phẩm',
            'Price' => 'Giá gốc',
            'SalePrice' => 'Giá bán',
            'BrandId' => 'Nhãn hàng',
            'Unit' => 'Đơn vị',
            'Description' => 'Mô tả',
            'Barcode' => 'Mã vạch',
            'Category' => 'Danh mục',
        ];
        $validator = Validator::make($request->all(), [
            'ProductId' => 'required|numeric',
            'ProductName' => 'max:250',
            'BrandId' => 'numeric',
            'Unit' => 'array',
            'Category' => 'array',
            'Description' => 'max:500',
            'Barcode' => 'max:50',
        ], $message, $attribute);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        // Kiểm tra 2 màn hình
        $product = $this->productManagerRepo->checkProductUpdateById($request->get('ProductId'), $request->get('UpdatedDate'));
        if (!isset($product) || empty($product)) {
            $this->addMessage("Sản phẩm không tồn tại hoặc có sự thay đổi. Vui lòng F5 lại màn hình!", 'UPA001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        // Kiểm tra trùng tên sản phẩm
        $product = $this->productManagerRepo->checkProductByName($request->get('ProductName'), $request->get('ProductId'));
        if ($product) {
            $this->addMessage("Tên sản phẩm bị trùng lặp!", 'CP0001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $avatar = $request->file('Avatar');
        if (isset($avatar) && !empty($avatar) && is_file($avatar)) {
            $urlProduct = Helper::uploadFileToServer($avatar, 'Product');

            if (!$urlProduct || empty($urlProduct)) {
                $this->addMessage("Lưu ảnh sản phẩm vào hệ thống CDN thất bại", 'ERR001', self::$ERROR);
                return $this->json(false, 'bool');
            }
            $avatarURL = API_MEDIA . '/' . $urlProduct;
            $request->merge(['AvatarURL' => $avatarURL]);
        } else {
            $request->merge(['AvatarURL' => $request->get('Avatar')]);
        }

        $result = $this->productManagerRepo->updateProduct($request->all(), $request->get('AvatarURL'));

        if ($result) {
            $this->addMessage("Cập nhật sản phẩm thành công!", 'CP001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }

        $this->addMessage("Cập nhật sản phẩm không thành công!", 'CP002', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function getOptionLists()
    {
        $rs = $this->productManagerRepo->getOptionLists();

        return $this->json([$this->formatData("ProductDetailOpts", $rs, 'Grid')], 'views');
    }

    public function getListProductTreatment(Request $request)
    {
        $result = $this->productManagerRepo->getListProductTreatment($request->all());

        return $this->json([$this->formatPagination('ListProductTreatment', $result)]);
    }

    public function getListImportExportHistories(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ProductId' => 'required|numeric',
            'FromDate' => 'nullable|date',
            'ToDate' => 'nullable|date',
            'BranchIds' => 'nullable|array',
            'BranchIds.*' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $data = $this->productManagerRepo->getListImportExportHistoriesV2($request->all());
        $results[] = $this->formatPagination("ListImportExportHistories", $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function getImportHistoryDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ProductId' => 'required|numeric',
            'IRId' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $data = $this->productManagerRepo->getImportHistoryDetail($request->get('ProductId'), $request->get('IRId'));
        $results[] = $this->formatData("ImportHistoryDetail", $data, 'Grid');
        return $this->json($results, 'views');
    }

    public function getExportHistoryDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ProductId' => 'required|numeric',
            'ORId' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $data = $this->productManagerRepo->getExportHistoryDetail($request->get('ProductId'), $request->get('ORId'));
        $results[] = $this->formatData("ExportHistoryDetail", $data, 'Grid');
        return $this->json($results, 'views');
    }
}
