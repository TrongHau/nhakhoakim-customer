<?php

namespace App\Repositories;

use App\Branch;
use App\Exports\BaseReport;
use App\Exports\ReportExport;
use App\Exports\S3ExportStorage;
use App\Validations\InboundProductValidation;
use App\Imports\IRImport;
use App\IRDetail;
use App\IR;
use App\InvProduct;
use App\InvProductExpired;
use App\InvTransaction;
use App\Libs\Helper;
use App\Product;
use App\Repositories\Abstracts\EloquentRepository;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class InBoundOrderRepository extends EloquentRepository
{
    protected const TYPE_SEARCH = [
        'IRCode' => 1,
        'SKU' => 2,
        'RefCode' => 3
    ];
    protected function getModel()
    {
        return IR::class;
    }

    public function checkRefCode($refCode)
    {
        $query = $this->_model->newQuery();
        $query->where('RefCode', $refCode);

        $rs = $query->first();
        if (!$rs) {
            return true;
        }
        return false;
    }

    public function createInboundOrder($data)
    {
        $totalProducts = count($data['Products'] ?? 0);
        $staffId = Auth::user()['StaffId'] ?? 0;

        $branchId = $data['BranchId'] ?? 0;
        $currentBranchId = $data['CurrentBranchId'] ?? 0;
        $providerId = $data["ProviderId"] ?? 0;

        if (!isset($branchId) || empty($branchId) || !is_numeric($branchId)) {
            $branchId = $currentBranchId ?? 0;
        }
        if (!$providerId || empty($providerId)) {
            $providerId = 0;
        }

        try {
            $branch = DB::table('in.Branch')->select('BranchCode')->where('BranchId', $data['BranchId'])->first();
            $code = $branch->BranchCode . substr(date('Y'), -2) . date('m');
            DB::select("CALL pos.usp_AutoGenerate_Generate('InboundOrder', :Code, @pSeekNumber)", ['Code' => $code]);
            $seekNumber = DB::select("SELECT @pSeekNumber")[0]->{'@pSeekNumber'};
            $inboundCode = 'IR' . $code . str_pad($seekNumber, 4, '0', STR_PAD_LEFT);

            $totalExpectQty = 0;
            $flatDataProduct = [];
            foreach ($data['Products'] as &$product) {
                if (!isset($product['ExpectQuantity']) || !is_numeric($product['ExpectQuantity'])) {
                    return (object) [
                        "Result" => 0,
                        "ResultMessage" => "Số lượng dự kiến phải là số."
                    ];
                }
                $itemFlatData = [];
                if (isset($product['CostPrice'])) {
                    $costPrice = (int) $product['CostPrice'] ?? 0;
                    $itemFlatData['Price'] = "$costPrice";
                }

                if (isset($product['ExpectQuantity'])) {
                    $itemFlatData['ExpectedQty'] = $product['ExpectQuantity'] ?? 0;
                }
                $itemFlatData['ProductId'] = $product['ProductId'] ?? 0;
                $itemFlatData['UnitId'] = $product['UnitId'] ?? 0;
                $itemFlatData['PartnerSKU'] = $product['PartnerSKU'] ?? '';
                $itemFlatData['RefQty'] = $product['RefQty'] ?? 0;
                $itemFlatData['ActualQty'] = $product['ActualQty'] ?? 0;
                $itemFlatData['ExceptionQty'] = $product['ExceptionQty'] ?? 0;
                $itemFlatData['PutAwayQty'] = $product['PutAwayQty'] ?? 0;
                $itemFlatData['ExpirationDate'] = $product['ExpirationDate'] ?? '';
                $itemFlatData['ManufactureDate'] = $product['ManufactureDate'] ?? '';
                
                $expectQty = $product['ExpectQuantity'] ?? 0;
                $totalExpectQty += $expectQty;
                $flatDataProduct[] = $itemFlatData;
            }
            
            $productsJson = json_encode($flatDataProduct);
            $dateTimeString = $data["ExpectArrivalDate"];
            $expectArrivalDate = Carbon::parse($dateTimeString);
            $supplierName = $data["SupplierName"] ?? '';
            $note = '';
            if (isset($data["Note"]) && !empty($data["Note"])) {
                $note = base64_encode($data["Note"]);
            }
            $refCode = $data["RefCode"] ?? '';
            $lastCheckInDate = Carbon::now()->toDateTimeString();
            $queryStore =  "CALL sale.sp_ManageInboundReceipt('CREATE', NULL, '$branchId', '$providerId' , '$inboundCode', 1, '$expectArrivalDate', NULL, NULL, NULL, NULL, '$supplierName', '$note', '$lastCheckInDate', '$refCode',$totalExpectQty,$staffId, '$productsJson')";

            Log::info('store create IR: ' . $queryStore);

            $result = DB::select($queryStore);
        } catch (\Exception $e) {
            Log::error("Create IR Error:", [$e->getMessage()]);
            // xóa $queryStore khi lên production
            return (object) [
                "Result" => 0,
                "ResultMessage" => "Tạo phiếu nhập hàng thất bại. "
            ];
        }
        // Log::info('Store Log: ', [$result]);
        if (isset($result) && !empty($result) && is_array($result) && isset($result[0])) {
            return (object) [
                "Result" => $result[0]->Result,
                "ResultMessage" => $result[0]->ResultMessage
            ];
        }
        return (object) [
            "Result" => 0,
            "ResultMessage" => "Tạo phiếu nhập hàng thất bại."
        ];
    }

    public function getListIR($data)
    {
        $keyword = $data['Keyword'] ?? 0;
        $type = $data['Type'] ?? 0;
        $fromDate = $data['FromDate'] ?? '';
        $toDate = $data['ToDate'] ?? '';
        $lmstart = $data['lmstart'] ?? 0;
        $limit = $data['limit'] ?? 0;
        $branchId = $data['BranchId'] ?? 0;
        $staffId = Auth::user()['StaffId'] ?? 0;

        if (isset($lmstart) && empty($lmstart)) {
            $lmstart = 0;
        }
        if (isset($limit) && empty($limit)) {
            $limit = 20;
        }

        if (!isset($branchId) || empty($branchId)) {
            $branchId = 0;
        }

        $query = $this->_model->newQuery();
        $query->select(
            'pir.IRId',
            'pir.BranchId',
            'pir.PartnerId',
            'pir.IRCode',
            'pir.IRStatus',
            'pir.IRType',
            'pir.TotalValue',
            'pir.EstimateArrivalDate',
            'pir.ActualArrivalDate',
            'pir.SupplierName',
            'pir.TotalSKU',
            'pir.TotalActualQty',
            'pir.TotalExpectedQty as TotalExpectQty',
            'pir.CreatedBy',
            'pir.CreatedDate',
            'b.Name',
            'b.BranchCode',
            'cgp.PartnerName',
            'cgp.PartnerCode'
        )->from('sale.IR as pir')
            ->join('in.Branch as b', 'b.BranchId', 'pir.BranchId')
            ->leftJoin('sale.Partner as cgp', function ($join) {
                $join->on('cgp.PartnerId', '=', 'pir.PartnerId');
                $join->where('cgp.IsDeleted', 0);
            });

        if (is_numeric($branchId) && $branchId != 0) {
            $query->where('pir.BranchId', $branchId);
        }

        if (isset($keyword) && !empty($keyword)) {
            switch ($type) {
                case self::TYPE_SEARCH['IRCode']:
                    $query->where('pir.IRCode', 'LIKE', '%' . $keyword . '%');
                    break;
                case self::TYPE_SEARCH['SKU']:
                    $query->join('sale.IRDetail as pdir', 'pir.IRId', '=', 'pdir.IRId')
                        ->join('sale.Product as p', 'p.ProductId', '=', 'pdir.ProductId')
                        ->where('p.SKU', 'LIKE', '%' . $keyword . '%');
                    break;
                case self::TYPE_SEARCH['RefCode']:
                    $query->where('pir.RefCode', 'LIKE', '%' . $keyword . '%');
                default:
                    $query->join('sale.IRDetail as pdir', 'pir.IRId', '=', 'pdir.IRId')
                        ->join('sale.Product as p', 'p.ProductId', '=', 'pdir.ProductId')
                        ->where(function ($q) use ($keyword) {
                            $q->where('pir.IRCode', 'LIKE', '%' . $keyword . '%')
                                ->orWhere('pir.RefCode', 'LIKE', '%' . $keyword . '%')
                                ->orWhere('p.SKU', 'LIKE', '%' . $keyword . '%');
                        });
                    break;
            }
        }
        if (isset($fromDate) && !empty($fromDate)) {
            $fromDate = Carbon::parse($fromDate)->format('Y-m-d 0:00:00');
            $query->where('pir.CreatedDate', '>=', $fromDate);
        }
        if (isset($toDate) && !empty($toDate)) {
            $toDate = Carbon::parse($toDate)->format('Y-m-d 23:59:59');
            $query->where('pir.CreatedDate', '<=', $toDate);
        }

        $query->groupBy('pir.IRId')->orderByDESC('pir.IRId');

        $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);
        foreach ($result as $rs) {
            $rs->IsEdit = false;
            if ($rs->IRStatus == 1 && $rs->CreatedBy == $staffId) {
                $rs->IsEdit = true;
            }
        }

        return $result;
    }

    public function getDetailIR($id)
    {
        $results = [];
        $query = $this->_model->newQuery();
        $query->select(
            'pir.IRId',
            'pir.BranchId',
            'pir.PartnerId',
            'pir.IRCode',
            'pir.IRStatus',
            'pir.IRType',
            'pir.TotalValue',
            'pir.EstimateArrivalDate',
            'pir.ActualArrivalDate',
            'pir.SupplierName',
            'pir.Note',
            'pir.TotalSKU',
            'pir.TotalActualQty',
            'pir.RefCode',
            'pir.CreatedBy',
            'pir.CreatedDate',
            'b.BranchId',
            'b.Name',
            'b.BranchCode',
            'cgp.PartnerName',
            'cgp.PartnerCode'
        )->from('sale.IR as pir')
            ->join('in.Branch as b', 'b.BranchId', 'pir.BranchId')
            ->leftJoin('sale.Partner as cgp', function ($join) {
                $join->on('cgp.PartnerId', '=', 'pir.PartnerId');
                $join->where('cgp.IsDeleted', 0);
            })
            ->where('pir.IRId', $id);

        $query->with(['createdByStaff' => function ($query) {
            $query->select('StaffId', 'FullName', 'StaffCode');
        }]);

        $ir = $query->first();
        $results['IR'] = $ir;
        $query = $this->_model->newQuery();
        $query->select(
            'pdir.IRDetailId',
            'pdir.IRId',
            'pdir.ProductId',
            'pdir.UnitId',
            'pdir.LOT',
            'pdir.ManufactureDate',
            'pdir.ExpirationDate',
            'p.Barcode',
            'p.SKU',
            'p.ProductName',
            'p.IsTrackingSerial',
            'p.IsExpiryDate',
            'pdir.ExpectedQty as ExpectQty',
            'pdir.ActualQty',
            'um.Code',
            'um.Name',
            'pu.SalePrice',
            'pdir.Price as CostPrice',
            'pu.QtyPerCase'
        )->from('sale.IRDetail as pdir')
            ->leftJoin('sale.Product as p', 'p.ProductId', 'pdir.ProductId')
            ->leftJoin('sale.ProductUnit as pu',  function ($join) {
                $join->on('pu.ProductId', '=', 'pdir.ProductId')
                    ->on('pdir.UnitId', '=', 'pu.UnitId');
            })
            ->leftJoin('sale.Unit as um', 'um.UnitId', 'pdir.UnitId')
            ->where('pdir.IRId', $id);
        $detail = $query->get();
        $results['Details'] = $detail;

        return $results;
    }

    public function getWarehousedProductsList($data)
    {
        $keyword = $data['Keyword'] ?? '';
        $lmstart = $data['lmstart'] ?? 0;
        $limit = $data['limit'] ?? 0;

        if (isset($lmstart) && empty($lmstart)) {
            $lmstart = 0;
        }
        if (isset($limit) && empty($limit)) {
            $limit = 20;
        }
        $results = [];
        $query = $this->_model->newQuery();
        $query->select(
            'p.ProductId',
            'p.SKU',
            'p.Barcode',
            'p.ProductName',
            'um.UnitId',
            'um.Name',
            'um.Code'
        )
            ->from('sale.Product as p')
            ->join('sale.ProductUnit as pu', 'pu.ProductId', 'p.ProductId')
            ->join('sale.Unit as um', 'um.UnitId', 'pu.UnitId');
        if (isset($keyword) && !empty($keyword)) {
            $query->where('p.SKU', $keyword)
                ->orWhere('p.Barcode', $keyword)
                ->orWhere('p.ProductName', 'LIKE', '%' . $keyword . '%');
        }
        $query->where('IsActive', 1);
        $results = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);

        return $results;
    }

    public function updateInboundOrder($data)
    {
        $productIRId = $data['IRId'] ?? 0;
        $staffId = Auth::user()['StaffId'] ?? 0;
        $type = $data['Type'] ?? 0;
        $status = $data['CurrentStatus'] ?? 0;
        $products = $data['Products'] ?? [];
        $branchId = $data['BranchId'] ?? 0;
        $iRId = $data['IRId'] ?? 0;
        $currentBranchId = $data['CurrentBranchId'] ?? 0;
        $note = $data["Note"] ?? '';
        $providerId = $data["ProviderId"] ?? 0;
        
        if (!isset($productIRId) || empty($productIRId) && $productIRId) {
            return false;
        }
        
        if (!isset($status) || empty($status) || $status < 1) {
            $status = $data['Status'] ?? 0;
        
        }
        if (!isset($branchId) || empty($branchId) || !is_numeric($branchId)) {
            $branchId = $currentBranchId;
        }
        if (!$providerId || empty($providerId)) {
            $providerId = 0;
        }

        // handle note
        if ($note && !empty($note)) {
            $note = base64_encode($note);
        }

        $query = $this->_model->where('IR.IRId', $productIRId);
        $productIR = $query->first();
        if (!isset($productIR) || empty($productIR)) {
            return (object) [
                "Result" => 0,
                "ResultMessage" => "Phiếu nhập không tồn tại hoặc đã bị huỷ."
            ];
        }
        if ($productIR->IRStatus != $status || $status < 1) {
            return (object) [
                "Result" => 0,
                "ResultMessage" => "Phiếu nhập đã có sự thay đổi. Vui lòng F5 màn hình!"
            ];
        }
        $branchId = $productIR->BranchId;
        try {
            switch ($type) {
                case 1: // New -> Processing

                    $result = DB::select("CALL sale.sp_ManageInboundReceipt('CONFIRM', ".$productIRId.", ".$branchId.", NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$note', NULL, NULL, NULL, ".$staffId.", NULL)");
                    Log::info("CONFIRM Inbound: " . "CALL sale.sp_ManageInboundReceipt('CONFIRM', ".$productIRId.", ".$branchId.", NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$note', NULL, NULL, NULL, ".$staffId.", NULL)");

                    return $result;
                    break;
                case 2: // Processing -> Completed
                    if ($productIR->IRStatus != 2) return (object) [
                        "Result" => 0,
                        "ResultMessage" => "Phiếu nhập đã có sự thay đổi. Vui lòng F5 màn hình!"
                    ];
                    $actualReceived = 0;

                    foreach ($products as $item) {
                        $actualReceived += $item['ActualReceived'];
                    }

                    $flatData = [];
                    foreach ($products as $key => $product) {
                        $costPrice = (int)$product['CostPrice'];
                        if(isset($product['UseTime']) || !empty($product['UseTime'])){
                            foreach ($product['UseTime'] as $k => $useTime) {
                                $lot = '';
                                if(isset($useTime['LOT'])){
                                    $lot = $useTime['LOT'];
                                }
                                $flatData[] = [
                                    "ProductId" => $product['ProductId'],
                                    "UnitId" => $product['UnitId'],
                                    "ConditionTypeId" => 1,
                                    "PartnerSKU" => $product['SKU'],
                                    "ExpectedQty" => $product['ExpectQty'],
                                    "RefQty" => 0,
                                    "ActualQty" => $useTime['Quantity'],
                                    "ExceptionQty" => 0,
                                    "Price" => "$costPrice",
                                    "PutAwayQty" => 0,
                                    "ManufactureDate" => Carbon::parse($useTime['ManufactureDate'])->format('Y-m-d')." 00:00:00",
                                    "ExpirationDate" => Carbon::parse($useTime['ExpirationDate'])->format('Y-m-d')." 00:00:00",
                                    "LOT" => Helper::cleanString($lot),
                                ];
                            }
                        }else{
                            $flatData[] = [
                                "ProductId" => $product['ProductId'],
                                "UnitId" => $product['UnitId'],
                                "ConditionTypeId" => 1,
                                "PartnerSKU" => $product['SKU'],
                                "ExpectedQty" => $product['ExpectQty'],
                                "RefQty" => 0,
                                "ActualQty" => $product['ActualReceived'],
                                "ExceptionQty" => 0,
                                "Price" => "$costPrice",
                                "PutAwayQty" => 0,
                                "ManufactureDate" => "",
                                "ExpirationDate" => "",
                                "LOT" => '',
                            ];
                        }
                    }
                    
                    $productsJson = json_encode($flatData, JSON_UNESCAPED_UNICODE);
                    $result = DB::select("CALL sale.sp_ManageInboundReceipt('FINISH', ".$productIRId.", ".$branchId.", NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$note', NULL, NULL, ".$actualReceived.", ".$staffId.", '".$productsJson."')");
                    Log::info("FINISH Inbound: " . "CALL sale.sp_ManageInboundReceipt('FINISH', ".$productIRId.", ".$branchId.", NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$note', NULL, NULL, ".$actualReceived.", ".$staffId.", '".$productsJson."')");
                    return $result;

                    break;
                case 3: //New -> Cancel
                    if ($productIR->IRStatus != 1) return (object) [
                        "Result" => 0,
                        "ResultMessage" => "Phiếu nhập đã có sự thay đổi. Vui lòng F5 màn hình!"
                    ];

                    $result = DB::select("CALL sale.sp_ManageInboundReceipt('CANCEL', ".$productIRId.", ".$branchId.", NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$note', NULL, NULL, NULL, ".$staffId.", NULL)");
                    Log::info("CANCEL Inbound: " . "CALL sale.sp_ManageInboundReceipt('CANCEL', ".$productIRId.", ".$branchId.", NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$note', NULL, NULL, NULL, ".$staffId.", NULL)");
                    return $result;

                    break;
                case 4: // chỉnh sửa phiếu nhập
                    if ($productIR->IRStatus != 1) return (object) [
                        "Result" => 0,
                        "ResultMessage" => "Chỉ có thể chỉnh sửa phiếu nhập hàng ở trạng thái 'Mới'. Vui lòng tải lại trang"
                    ];

                    $totalExpectQty = 0;
                    $flatDataProduct = [];
                    foreach ($data['Products'] as &$product) {
                        if (!isset($product['ExpectQuantity']) || !is_numeric($product['ExpectQuantity'])) {
                            return (object) [
                                "Result" => 0,
                                "ResultMessage" => "Số lượng dự kiến phải là số."
                            ];
                        }
                        $itemFlatData = [];
                        if (isset($product['CostPrice'])) {
                            $costPrice = (int) $product['CostPrice'] ?? 0;
                            $itemFlatData['Price'] = "$costPrice";
                        }

                        if (isset($product['ExpectQuantity'])) {
                            $itemFlatData['ExpectedQty'] = $product['ExpectQuantity'] ?? 0;
                        }
                        $itemFlatData['RefQty'] = $product['RefQty'] ?? 0;
                        $itemFlatData['UnitId'] = $product['UnitId'] ?? 0;
                        $itemFlatData['ActualQty'] = $product['ActualQty'] ?? 0;
                        $itemFlatData['ProductId'] = $product['ProductId'] ?? 0;
                        $itemFlatData['PartnerSKU'] = $product['PartnerSKU'] ?? '';
                        $itemFlatData['PutAwayQty'] = $product['PutAwayQty'] ?? 0;
                        $itemFlatData['ExceptionQty'] = 0;
                        $itemFlatData['ExpirationDate'] = $product['ExpirationDate'] ?? '';
                        $itemFlatData['ManufactureDate'] = $product['ManufactureDate'] ?? '';
                        
                        $expectQty = $product['ExpectQuantity'] ?? 0;
                        $totalExpectQty += $expectQty;
                        $flatDataProduct[] = $itemFlatData;
                    }
                    
                    $productsJson = json_encode($flatDataProduct);
                    $dateTimeString = $data["ExpectArrivalDate"];
                    $expectArrivalDate = Carbon::parse($dateTimeString);
                    $supplierName = $data["SupplierName"] ?? '';
                    $refCode = $data["RefCode"] ?? '';
                    $lastCheckInDate = Carbon::now()->toDateTimeString();
                    $queryStore =  "CALL sale.sp_ManageInboundReceipt('UPDATE', $iRId, '$branchId', '$providerId', NULL, NULL, '$expectArrivalDate', NULL, NULL, NULL, NULL, '$supplierName', '$note', '$lastCheckInDate', '$refCode',$totalExpectQty,$staffId, '$productsJson')";
                    $result = DB::select(DB::raw($queryStore));
                    Log::info("UPDATE Inbound: $queryStore");
                    return $result;
                default:
                    break;
            }
        } catch (\Exception $e) {
            Log::error("Update Product IR Error: ", [$e->getMessage()]);
        }
    }

    public function getOptionLists()
    {
        $results = [];

        // get list warehouse
        $query = Branch::where('State', 1);
        $query->whereNotIn('BranchCode', ['BO', 'PK']);
        $query->whereNotNull('BranchCode');
        $query->where('BranchCode','!=', '');
        $query->whereIn('CompanyId', [1,2,3]);
        $query->select('BranchId', 'BranchCode', 'Name', 'Address');
        $query->orderBy('Priority');

        $results['Branch'] = $query->get();

        // get list Provider
        $query = $this->_model->newQuery();
        $query->select(
            'PartnerId',
            'PartnerCode',
            'PartnerName'
        )
            ->from('sale.Partner')
            ->where('Type', 1)
            ->where('IsDeleted', 0);
        $results['Provider'] = $query->get();
        $results['IRType'] = ['Nhập bán'];
        return $results;
    }

    public function getStockInOutReport($data)
    {
        $branchId = $data['BranchId'] ?? 0;
        $currentBranchId = $data['CurrentBranchId'] ?? 'NULL';
        $keyword = $data['Keyword'] ?? '';
        $fromDate = $data['FromDate'] ?? '';
        $toDate = $data['ToDate'] ?? '';
        $lmstart = $data['lmstart'] ?? 0;
        $limit = $data['limit'] ?? 20;

        if (!isset($lmstart) || empty($lmstart)) {
            $lmstart = 0;
        }

        if (!isset($limit) || empty($limit)) {
            $limit = 20;
        }

        if (!isset($fromDate) || empty($fromDate)) {
            $fromDate = Carbon::parse('00:00:00')->subMonths(1)->toDateTimeString();
        }

        if (!isset($toDate) || empty($toDate)) {
            $toDate = Carbon::parse('23:59:59')->subMonths(1)->toDateTimeString();
        }

        if (!isset($branchId) || empty($branchId) || !is_numeric($branchId) || $branchId < 1) {
            $branchId = $currentBranchId ?? 'NULL';
            if (!isset($branchId) || empty($branchId)) {
                $branchId = 'NULL';
            }
        }

        try {
            $paramStore = "$branchId, '$keyword', '$fromDate', '$toDate'";
            $storeReport = DB::select(DB::raw("CALL sale.usp_GetInventoryMovementReport($paramStore, $lmstart, $limit)"));
            $totalStoreReport = DB::select(DB::raw("CALL sale.usp_GetInventoryMovementReport($paramStore, 0, NULL)"));
            Log::info("CALL sale.usp_GetInventoryMovementReport($paramStore, $lmstart, $limit)");
            // [Issue 251] Tính thêm dòng tổng
            $totalRow = (object) [
                'StartingAvailableQty' => 0,
                'EndingAvailableQty' => 0,
                'QuantityIn' => 0,
                'QuantityOut' => 0,
                'StartingAmount' => 0,
                'EndingAmount' => 0,
                'TotalImportAmount' => 0,
                'TotalExportAmount' => 0,
            ];
            
            if (!empty($totalStoreReport)) {
                foreach ($totalStoreReport as $value) {
                    $totalRow->StartingAvailableQty += $value->StartingAvailableQty ?? 0;
                    $totalRow->EndingAvailableQty += $value->EndingAvailableQty ?? 0;
                    $totalRow->QuantityIn += $value->QuantityIn ?? 0;
                    $totalRow->QuantityOut += $value->QuantityOut ?? 0;
                    $totalRow->StartingAmount += $value->StartingAmount ?? 0;
                    $totalRow->EndingAmount += $value->EndingAmount ?? 0;
                    $totalRow->TotalImportAmount += $value->TotalImportAmount ?? 0;
                    $totalRow->TotalExportAmount += $value->TotalExportAmount ?? 0;
                }
            }
            return [
                'Data' => $storeReport, 
                'TotalRow' => $totalRow
            ];
        } catch (\Exception $e) {
            Log::error('Error ', [$e->getMessage()]);
            Log::info("CALL sale.usp_GetInventoryMovementReport($paramStore, $lmstart, $limit)");
        }
        return [];
    }

    public function getStockInOutReportsByPartner($data)
    {
        $partnerId = $data['PartnerId'] ?? 'NULL';
        $categoryId = $data['CategoryId'] ?? 'NULL';
        $keyword = $data['Keyword'] ?? '';
        $brandId = $data['BrandId'] ?? 'NULL';
        $fromDate = $data['FromDate'] ?? Carbon::parse('00:00:00')->subMonths(1)->startOfMonth()->toDateTimeString();
        $toDate = $data['ToDate'] ?? Carbon::parse('23:59:59')->subMonths(1)->endOfMonth()->toDateTimeString();
        $lmstart = $data['lmstart'] ?? 0;
        $limit = $data['limit'] ?? 20;

        if (!isset($lmstart) || empty($lmstart)) {
            $lmstart = 0;
        }

        if (!isset($limit) || empty($limit)) {
            $limit = 20;
        }

        if (!isset($categoryId) || empty($categoryId)) {
            $categoryId = 'NULL';
        }

        if (!isset($brandId) || empty($brandId)) {
            $brandId = 'NULL';
        }

        if (!isset($partnerId) || empty($partnerId)) {
            $partnerId = 'NULL';
        }
        
        try {
            $paramStore = "CALL sale.usp_GetPurchaseReportBySupplier($partnerId, $categoryId, '$keyword', $brandId, '$fromDate', '$toDate'";
            $storeReport = DB::select(DB::raw("$paramStore, $lmstart, $limit)"));
            $totalStoreReport = DB::select(DB::raw("$paramStore, 0, NULL)"));
            Log::info("$paramStore, $lmstart, $limit)");
            // [Issue 251] Tính thêm dòng tổng
            $totalRow = (object) [
                'TotalIR' => 0,
                'TotalValue' => 0,
            ];
            
            if (!empty($totalStoreReport)) {
                foreach ($totalStoreReport as $value) {
                    $totalRow->TotalIR += $value->TotalIR ?? 0;
                    $totalRow->TotalValue += $value->TotalValue ?? 0;
                }
            }
            return [
                'Data' => $storeReport, 
                'TotalRow' => $totalRow
            ];
        } catch (\Exception $e) {
            Log::error('Error ', [$e->getMessage()]);
            Log::info("$paramStore, $lmstart, $limit)");
        }

        return [];
    }

    public function exportStockInOutReports($data)
    {
        $dataStore = [
            "FromDate" => $data['FromDate'] ?? Carbon::parse('00:00:00')->subMonths(1)->startOfMonth()->toDateTimeString(),
            "ToDate" => $data['ToDate'] ?? Carbon::parse('23:59:59')->subMonths(1)->endOfMonth()->toDateTimeString(),
            "lmstart" => 0,
            "limit" => 'NULL',
            'BranchId' => $data['BranchId'] ?? 0,
            "CurrentBranchId" => $data['CurrentBranchId'] ?? 'NULL',
            "Keyword" => $data['Keyword'] ?? '',
        ];
        $staffId = Auth::user()['StaffId'];

        if (!isset($dataStore['lmstart']) || empty($dataStore['lmstart'])) {
            $dataStore['lmstart'] = 0;
        }

        if (!isset($dataStore['limit']) || empty($dataStore['limit'])) {
            $dataStore['limit'] = 20;
        }

        if (!isset($dataStore['CategoryId']) || empty($dataStore['CategoryId'])) {
            $dataStore['CategoryId'] = 'NULL';
        }

        if (!isset($dataStore['BrandId']) || empty($dataStore['BrandId'])) {
            $dataStore['BrandId'] = 'NULL';
        }

        if (!isset($dataStore['BranchId']) || empty($dataStore['BranchId']) || !is_numeric($dataStore['BranchId']) || $dataStore['BranchId'] < 1) {
            $dataStore['BranchId'] = $dataStore['CurrentBranchId'] ?? 'NULL';
        }
        
        $storeReport = [];
        $totalRow = [];
        try {
            $data = $this->getStockInOutReport($dataStore);
            $storeReport = $data['Data'];
            $totalRow = $data['TotalRow'];
        } catch (\Exception $e) {
            Log::error('Error ', [$e->getMessage()]);
            return "";
        }

        if (isset($storeReport) && !empty($storeReport)) {
            $exportValue = [];
            $fileExportName = 'Stock_InOut_' . '_report_' . date('Y_m') . '_' . time() . '_' . $staffId . '.xlsx';
            $filePathExport = storage_path('app/excel') . '/' . $fileExportName;
            $headings = ['Mã sản phẩm', 'Tên hàng hóa', 'Đơn vị tính', 'Kho', 'Tồn đầu kì', 'Tồn cuối kì', 'SL Nhập', 'SL xuất', 'Tổng tiền đầu kì (VNĐ)', 'Tổng tiền cuối kì (VNĐ)', 'Tổng tiền nhập (VNĐ)', 'Tổng tiền xuất (VNĐ)'];
            try {

                foreach ($storeReport as $value) {
                    $exportValue[] = [
                        'SKU' => $value->SKU ?? '',
                        'ProductName' => $value->ProductName ?? '',
                        'UnitName' => $value->UnitName ?? '',
                        'WareHouse' => $value->WareHouse ?? '',
                        'StartingAvailableQty' => $value->StartingAvailableQty ?? 0,
                        'EndingAvailableQty' => $value->EndingAvailableQty ?? 0,
                        'QuantityIn' => $value->QuantityIn ?? 0,
                        'QuantityOut' => $value->QuantityOut ?? 0,
                        'StartingAmount' => $value->StartingAmount ?? 0,
                        'EndingAmount' => $value->EndingAmount ?? 0,
                        'TotalImportAmount' => $value->TotalImportAmount ?? 0,
                        'TotalExportAmount' => $value->TotalExportAmount ?? 0,
                    ];
                }

                array_unshift($exportValue, [
                    'Tổng',
                    '',
                    '',
                    '',
                    $totalRow->StartingAvailableQty ?? 0,
                    $totalRow->EndingAvailableQty ?? 0,
                    $totalRow->QuantityIn ?? 0,
                    $totalRow->QuantityOut ?? 0,
                    $totalRow->StartingAmount ?? 0,
                    $totalRow->EndingAmount ?? 0,
                    $totalRow->TotalImportAmount ?? 0,
                    $totalRow->TotalExportAmount ?? 0,
                ]);
            } catch (\Exception $e) {
                Log::error('Report Export By Product fail: ', [$e->getMessage()]);
            }

            $orderRepo = new OrderRepository();
            $exportFile = $orderRepo->storeReportExport($exportValue, $headings, $fileExportName, $filePathExport, true);
            if ($exportFile) {
                return $exportFile;
            }
        }

        return "";
    }

    public function exportStockInOutReportByPartner($data)
    {
        $dataStore = [
            "FromDate" => $data['FromDate'] ?? Carbon::parse('00:00:00')->subMonths(1)->startOfMonth()->toDateTimeString(),
            "ToDate" => $data['ToDate'] ?? Carbon::parse('23:59:59')->subMonths(1)->endOfMonth()->toDateTimeString(),
            "lmstart" => 0,
            "limit" => 'NULL',
            'BranchId' => $data['BranchId'] ?? 0,
            "CurrentBranchId" => $data['CurrentBranchId'] ?? 'NULL',
            "CategoryId" => $data['CategoryId'] ?? 'NULL',
            "Keyword" => $data['Keyword'] ?? '',
            "BrandId" => $data['BrandId'] ?? 'NULL',
            'PartnerId' => $data['PartnerId'] ?? 'NULL',
        ];
        $staffId = Auth::user()['StaffId'];

        if (!isset($dataStore['lmstart']) || empty($dataStore['lmstart'])) {
            $dataStore['lmstart'] = 0;
        }

        if (!isset($dataStore['limit']) || empty($dataStore['limit'])) {
            $dataStore['limit'] = 20;
        }

        if (!isset($dataStore['CategoryId']) || empty($dataStore['CategoryId'])) {
            $dataStore['CategoryId'] = 'NULL';
        }

        if (!isset($dataStore['BrandId']) || empty($dataStore['BrandId'])) {
            $dataStore['BrandId'] = 'NULL';
        }

        if (!isset($dataStore['BranchId']) || empty($dataStore['BranchId']) || !is_numeric($dataStore['BranchId']) || $dataStore['BranchId'] < 1) {
            $dataStore['BranchId'] = $dataStore['CurrentBranchId'] ?? 'NULL';
        }

        if (!isset($dataStore['PartnerId']) || empty($dataStore['PartnerId'])) {
            $dataStore['PartnerId'] = 'NULL';
        }
        
        $storeReport = [];
        $totalRow = [];
        try {
            $data = $this->getStockInOutReportsByPartner($dataStore);
            $storeReport = $data['Data'];
            $totalRow = $data['TotalRow'];
        } catch (\Exception $e) {
            Log::error('Error at function getStockInOutReportsByPartner', [$e->getMessage()]);
            return false;
        }

        if (isset($storeReport) && !empty($storeReport)) {
            $exportValue = [];
            $fileExportName = 'Stock_InOut_By_Partner_' . '_report_' . date('Y_m') . '_' . time() . '_' . $staffId . '.xlsx';
            $filePathExport = storage_path('app/excel') . '/' . $fileExportName;
            $headings = ['Mã nhà cung cấp', 'Tên nhà cung cấp', 'Mã sản phẩm', 'Tên sản phẩm', 'Đơn vị tính', 'SL nhập','Tổng tiền sản phẩm (VNĐ)', 'Loại nhập', 'Kho', 'Danh mục', 'Nhãn hàng'];
            try {
                foreach ($storeReport as $value) {
                    $exportValue[] = [
                        'PartnerCode' => $value->PartnerCode ?? '',
                        'PartnerName' => $value->PartnerName ?? '',
                        'SKU' => $value->SKU ?? '',
                        'ProductName' => $value->ProductName ?? '',
                        'UnitName' => $value->UnitName ?? '',
                        'TotalIR' => $value->TotalIR ?? 0,
                        'TotalValue' => $value->TotalValue ?? 0,
                        'IRType' => $value->IRType ?? '',
                        'BranchName' => $value->BranchName ?? '',
                        'CategoryName' => $value->CategoryName ?? '',
                        'BrandName' => $value->BrandName ?? '',
                        
                    ];
                }

                array_unshift($exportValue, [
                    'Tổng',
                    '',
                    '',
                    '',
                    '',
                    $totalRow->TotalIR ?? 0,
                    $totalRow->TotalValue ?? 0,
                ]);
            } catch (\Exception $e) {
                Log::error('Report Export By Product fail: ', [$e->getMessage()]);
            }

            $orderRepo = new OrderRepository();
            $exportFile = $orderRepo->storeReportExport($exportValue, $headings, $fileExportName, $filePathExport, true);
            if ($exportFile) {
                return $exportFile;
            }
        }

        return false;
    }

    public function checkCreateBy($id)
    {
        $staffId = Auth::user()['StaffId'] ?? 0;
        $query = $this->_model->newQuery();
        $query->select('IRId')
            ->from('sale.IR')
            ->where('IRId', $id)
            ->where('CreatedBy', $staffId);
        $result = $query->first();
        return $result;
    }

    public function processExcelInbound($file)
    {
        try {
            //Validate file
            $import = new InboundProductValidation();
            Excel::import($import, $file);
            $data = $import->getData();
            $invalidCounts = $import->getInvalidCounts();

            if (!$data || count($data) < 1) {
                return [
                    'ExportUrl' => '',
                    'TotalInvalid' => $invalidCounts,
                    'DataValidated' => $data
                ];
            }

            //Export file
            $exportData = [];
            foreach ($data as $item) {
                $exportData[] = [
                    'SKU' => $item['SKU'] ?? '',
                    'UnitCode' => $item['UnitCode'] ?? '',
                    'ExpectQuantity' => $item['ExpectQuantity'] ?? 0,
                    'ErrorMessage' => $item['ErrorMessage'] ?? ''
                ];
            }

            $baseExport = new BaseReport($exportData);
            $s3ExportStorage = new S3ExportStorage();

            $exportFileName = 'inbound_produc_validation_' . time() . '.xlsx';
            $exportFilePath = storage_path('app/excel') . '/' . $exportFileName;
            
            
            $exportURL =  $baseExport->setStorage($s3ExportStorage)
                ->setHeadings(['SKU', 'Mã đơn vị', 'Số lượng dự kiến', 'Lỗi'])
                ->setBackgroundColor('A1:E1', 'B2E4D2')
                ->setBold('A1:E1')
                ->store('excel'.'/'.$exportFileName)
                ->export($exportFilePath,  "InboundProductValidation");
            
            //unlink file
            try {
                $baseExport->unlink($exportFilePath);
            } catch (\Exception $e) {
                Log::error('Error at function processExcelInbound', [$e->getMessage()]);
            }

            return [
                'ExportUrl' => $exportURL,
                'TotalInvalid' => $invalidCounts,
                'DataValidated' => $data
            ];

        } catch (\App\Exceptions\ImportExcelException $iee) {
            Log::error('Error at function processExcelInbound', [$iee->errorMessage()]);
            $result = [
                'Error' => $iee->errorMessage()
            ];
        } catch (\Exception $e) {
            Log::error('Error at function processExcelInbound', [$e->getMessage()]);
            $result = [
                'Error' => 'Có lỗi xảy ra, vui lòng thử lại sau.'
            ];
        }
        return $result;
    }

    public function getORIdRef()
    {
        $query = $this->_model->newQuery();
        $query->select('ORIDRef')->where('IRType', 3);

        $result = $query->get();

        return $result->toArray();
    }
}