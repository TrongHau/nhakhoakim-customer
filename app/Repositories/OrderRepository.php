<?php

namespace App\Repositories;

use App\Repositories\Abstracts\EloquentRepository;
use App\ProductOR;
use App\ORDetail;
use App\ConditionType;
use App\Unit;
use App\InvProduct;
use App\Deposit;
use App\Exports\ReportExport;
use App\InvTransaction;
use App\InvProductExpired;
use App\AffiliateAccount;
use App\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Libs\Helper;
use App\VnDistrict;
use App\VnProvince;
use App\VnWard;
use App\OrderDetail;
use App\ShipmentProvider;
use App\Shipment;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class OrderRepository extends EloquentRepository
{
    protected function getModel()
    {
        return ProductOR::class;
    }

    public function createOrderByCustomer($request)
    {
        $staffId = Auth::user()['StaffId'] ?? 0;
        $customerId = $request['CustomerId'] ?? 0;
        $customerName = $request['CustomerName'] ?? '';
        $customerPhoneNumber = $request['CustomerPhoneNumber'] ?? 0;
        $branchId = $request['BranchId'] ?? 0;
        $currentBranchId = $request['CurrentBranchId'] ?? 0;
        $products = $request['Products'] ?? [];
        $discountAmount = $request['DiscountAmount'] != '' ? $request['DiscountAmount'] : 0;
        $paidAmount = $request['PaidAmount'] != '' ? $request['PaidAmount'] : 0;
        $shippingFeeAmount = $request['ShippingFeeAmount'] != '' ? $request['ShippingFeeAmount'] : 0;
        $shippingFeePaidSide = $request['ShippingFeePaidSide'] ?? 1;
        $totalRevenue = $request['TotalRevenue'] ?? 0;
        $totalRevenue = intval($totalRevenue - $shippingFeeAmount);
        $shippingFullAddress = $request['FullAddress'] ?? '';
        $districtId = $request['DistrictId'] ?? 0;
        $provinceId = $request['ProvinceId'] ?? 0;
        $ShippingAddressNumber = $request['Address'] ?? '';
        $note = $request['Note'] ?? '';
        $staffIdOrDoctor = $request['StaffId'] ?? 0;
        $recipient = $customerName;
        $recipientPhone = $customerPhoneNumber;
        $isDelivery = $request['IsDelivery'] ?? 0;
        if(isset($request['Recipient']) && $request['Recipient'] != ""){
            $recipient = $request['Recipient'];
        }
        if(isset($request['RecipientPhone']) && $request['RecipientPhone'] != ""){
            $recipientPhone = $request['RecipientPhone'];
        }
        if(!$staffIdOrDoctor) {
            $staffIdOrDoctor = 0;
        }
        if (!$shippingFeePaidSide) {
            $shippingFeePaidSide = 1;
        }
        if (!$branchId || empty($branchId) || !is_numeric($branchId)){
            $branchId =  $currentBranchId;
        }

        // handle note
        if ($note && !empty($note)) {
            $note = base64_encode($note);
        }
        
        try {

            $districtInfo = DB::table('in.VnDistrict')->select('LabelVi','NameVi')->where('VnDistrictId', $districtId)->first();
            $provinceInfo = DB::table('in.VnProvince')->select('LabelVi','NameVi')->where('VnProvinceId', $provinceId)->first();

            $noAddressParts = [];

            if ($districtInfo) {
                $noAddressParts[] = $districtInfo->LabelVi . ' ' . $districtInfo->NameVi;
            }
            if ($provinceInfo) {
                $noAddressParts[] = $provinceInfo->LabelVi . ' ' . $provinceInfo->NameVi;
            }

            $shippingAddressNo = implode(', ', $noAddressParts);

            $jsonProduct = '';
            if(count($products) > 0){
                foreach($products as $value){
                    $salePrice = (int)$value['SalePrice'];
                    $priceAfterDiscount = isset($value['PriceAfterDiscount']) ? (int)$value['PriceAfterDiscount'] : 0;
                    $dataInsertDetail[] = [
                        'ProductId' => $value['ProductId'],
                        'ConditionTypeId' => 1,
                        'UnitId' => $value['UnitId'],
                        'SKU' => $value['SKU'],
                        'OrderQty' => $value['Qty'],
                        'ManufactureDate' => '',
                        'ExpirationDate' => '',
                        'RefPrice' => "0",    
                        'AffiliatePrice' => "0",
                        'SalePrice' => "$salePrice",
                        'PriceAfterDiscount' => "$priceAfterDiscount",
                        'DiscountType' => 0,
                        'DiscountAmount' => 0,
                        'Note' => ''

                    ];
                }
                $jsonProduct = json_encode($dataInsertDetail);
            }
            $queryStore = "CALL sale.sp_ManageOutboundReceipt('CREATE', NULL, ".$branchId.", NULL, 1, 1, 110, ".$customerId.", '".$customerName."', '".$customerPhoneNumber."', NULL, NULL, '".$recipient."','".$recipientPhone."','".$shippingFullAddress."', '".$shippingAddressNo."', '$ShippingAddressNumber', 0, NULL, 0, NULL, 0, NULL, ".$discountAmount.", 0, 0, 0, NULL, '".$note."', ".$staffId.", 0, ".$paidAmount.", 0, ".$shippingFeeAmount.", ".$shippingFeePaidSide.", ".$staffIdOrDoctor.", 1, ".$isDelivery.", '".$jsonProduct."')";
            $result = DB::select(DB::raw($queryStore));
            
            Log::info("createOrderByCustomer: " . $queryStore);
            
            return $result;
        } catch (\Exception $e) {
            Log::error("createOrder error: " . $e->getMessage());
        }
        return false;
    }

    public function createOrder($request)
    {
        $branchId = $request['BranchId'] ?? 0;
        $currentBranchId = $request['CurrentBranchId'] ?? 0;
        $sourceType = $request['SourceType'] ?? 0;
        $refCode = $request['RefCode'] ?? '';
        $affiliateAccountId = $request['AffiliateAccountId'] ?? 0;
        if (!$affiliateAccountId) {
            $affiliateAccountId = 0;
        }
        $affiliateAmount = $request['AffiliateAmount'] ?? 0;
        $note = $request['Note'] ?? '';
        $customerId = $request['CustomerId'] != '' ? $request['CustomerId'] : 0;
        $customerName = $request['CustomerName'] ?? '';
        $customerPhoneNumber = $request['CustomerPhoneNumber'] ?? 0;
        $customerEmail = $request['Email'] ?? '';
        $provinceId = $request['ProvinceId'] != '' ? $request['ProvinceId'] : 0;
        $provinceName = $request['ProvinceName'] ?? '';
        $labelProvince = $request['LabelProvince'] ?? '';
        $districtId = $request['DistrictId'] != '' ? $request['DistrictId'] : 0;
        $districtName = $request['DistrictName'] ?? '';
        $labelDistrict = $request['LabelDistrict'] ?? '';
        $wardId = $request['WardId'] != '' ? $request['WardId'] : 0;
        $wardName = $request['WardName'] ?? '';
        $labelWard = $request['LabelWard'] ?? '';
        $address = $request['Address'] ?? '';
        $staffId = Auth::user()['StaffId'] ?? 0;
        $products = $request['Products'] ?? [];
        $discountAmount = $request['DiscountAmount'] ?? 0;
        $paidAmount = $request['PaidAmount'] ?? 0;
        $shippingFeeAmount = $request['ShippingFeeAmount'] ?? 0;
        $shippingFeePaidSide = $request['ShippingFeePaidSide'] ?? 1;
        $recipient = $customerName;
        $recipientPhone = $customerPhoneNumber;
        $isDelivery = $request['IsDelivery'] ?? 0;
        if(isset($request['Recipient']) && $request['Recipient'] != ""){
            $recipient = $request['Recipient'];
        }
        if(isset($request['RecipientPhone']) && $request['RecipientPhone'] != ""){
            $recipientPhone = $request['RecipientPhone'];
        }
        if (!$shippingFeePaidSide) {
            $shippingFeePaidSide = 1;
        }
        if (!$affiliateAmount) {
            $affiliateAmount = 0;
        }
        $totalRevenue = $request['TotalRevenue'] ?? 0;
        $totalRevenue = intval($totalRevenue - $shippingFeeAmount);
        $addressParts = [];
        $noAddressParts = [];

        if (!empty($address)) {
            $addressParts[] = $address;
        }

        if (!empty($labelWard) && !empty($wardName)) {
            $addressParts[] = $labelWard . ' ' . $wardName;
        }

        if (!empty($labelDistrict) && !empty($districtName)) {
            $addressParts[] = $labelDistrict . ' ' . $districtName;
            $noAddressParts[] = $labelDistrict . ' ' . $districtName;
        }

        if (!empty($labelProvince) && !empty($provinceName)) {
            $addressParts[] = $labelProvince . ' ' . $provinceName;
            $noAddressParts[] = $labelProvince . ' ' . $provinceName;
        }

        $shippingFullAddress = implode(', ', $addressParts);
        $shippingAddressNo = implode(', ', $noAddressParts);

        if($address == '' && $wardName == '' && $districtName && $provinceName){
            $shippingFullAddress = NULL;
            $shippingAddressNo = NULL;
        }


        if (!$branchId || empty($branchId) || !is_numeric($branchId)){
            $branchId =  $currentBranchId;
        }
        // handle note
        if ($note && !empty($note)) {
            $note = base64_encode($note);
        }

        try {

            $jsonProduct = '';
            if(count($products) > 0){
                foreach($products as $value){
                    $salePrice = (int)$value['SalePrice'];
                    $affiliatePrice = isset($value['AffiliatePrice']) ? (int)$value['AffiliatePrice'] : 0;
                    $priceAfterDiscount = isset($value['PriceAfterDiscount']) ? (int)$value['PriceAfterDiscount'] : 0;
                    $dataInsertDetail[] = [
                        'ProductId' => $value['ProductId'],
                        'ConditionTypeId' => 1,
                        'UnitId' => $value['UnitId'],
                        'SKU' => $value['SKU'],
                        'OrderQty' => $value['Qty'],
                        'ManufactureDate' => '',
                        'ExpirationDate' => '',
                        'RefPrice' => "0",    
                        'AffiliatePrice' => "$affiliatePrice",
                        'SalePrice' => "$salePrice",
                        'PriceAfterDiscount' => "$priceAfterDiscount",
                        'DiscountType' => 0,
                        'DiscountAmount' => 0,
                        'Note' => ''

                    ];
                }
                $jsonProduct = json_encode($dataInsertDetail);
            }
            $queryStore = "CALL sale.sp_ManageOutboundReceipt('CREATE', NULL, ".$branchId.", '".$refCode."', 1, 1, ".$sourceType.", ".$customerId.", '".$customerName."', '".$customerPhoneNumber."', '".$customerEmail."', NULL,'".$recipient."','".$recipientPhone."', '".$shippingFullAddress."', '".$shippingAddressNo."', '$address', ".$provinceId.", '".$provinceName."', ".$districtId.", '".$districtName."', ".$wardId.", '".$wardName."', ".$discountAmount.", 0, ".$affiliateAccountId.",".$affiliateAmount.", NULL, '".$note."', ".$staffId.", 0, ".$paidAmount.", 0, ".$shippingFeeAmount.", ".$shippingFeePaidSide.", 0, 0, ".$isDelivery.", '".$jsonProduct."')";
            $result = DB::select(DB::raw($queryStore));
            
            Log::info("createOrder: " . $queryStore);
            
            return $result;
        } catch (\Exception $e) {
            Log::error("createOrder error: " . $e->getMessage());
        }
        return false;
    }

    public  function createCodeOrder($branchId)
    {

        $branch = DB::table('in.Branch')->select('BranchCode')->where('BranchId', $branchId)->where('State', 1)->first();
        if($branch){
            $branchCode = $branch->BranchCode;
        }else{
            return false;
        }
        $code = "OR".$branchCode;

        $max = DB::table('sale.OR')
            ->select(DB::raw('MAX(CAST(SUBSTRING(ORCode, 10) AS UNSIGNED)) as ORCode'))
            ->where(DB::raw('LEFT(ORCode, 9)'), '=', DB::raw("CONCAT_WS('', '$code', RIGHT(YEAR(CURDATE()), 2), DATE_FORMAT(CURDATE(), '%m'))"))
            ->first();
        $maxOrCode = $max->ORCode;  
        
        $stt = sprintf('%05d', ($maxOrCode ?? 0) + 1);
        return  $code . substr(date('Y'), 2) . date('m') . $stt;
    }

    public function getListOrderByCustomer($request)
    {
        $customerId = $request['CustomerId'] ?? 0;
        $branchId = $request['BranchId'] ?? 0;
        $currentBranchId = $request['CurrentBranchId'] ?? 0;

        try {

            $query = ProductOR::select(['ORId','BranchId','ORCode','ORStatus','CustomerId','CustomerName','CustomerPhoneNumber', 'ShippingFullAddress', 'ShippingAddressNo', 'ShippingAddressNumber','TotalAmount','DiscountAmount','PaymentAmount', 'PaymentStatus','TotalRevenue','TotalItem','TotalSKU','CreatedBy','CreatedDate','UpdatedBy','UpdatedDate','PaidAmount','ShippingFeeAmount','ShippingFeePaidSide','Note','ConsultingStaffId','Recipient','RecipientPhone'])
                    ->where('BranchId', $branchId)->where('CustomerId', $customerId)->where('ORStatus', '<>', 100)->where('SourceType', 110);
            // if($currentBranchId && $currentBranchId > 0){
            //     $query->where('BranchId', $currentBranchId);
            // }
            $query->with(['createdByStaff' => function ($query) {
                $query->select('StaffId', 'FullName', 'StaffCode');
            }]);
            $query->with(['assignByDoctor' => function ($query) {
                $query->select('StaffId', 'FullName', 'StaffCode');
            }]);
            $query->with(['branch' => function ($query) {
                $query->select('BranchId', 'BranchCode', 'Name', 'Address');
            }]);
                                
            $data = $query->get();
            if($data && count($data) > 0){
                $data = $data->toArray();
                foreach($data as $key => $value){
                    $data[$key]["IsEdit"] = false;
                    if($currentBranchId == $value['BranchId'] && $value['ORStatus'] == 1){
                        $data[$key]["IsEdit"] = true;
                    }
                    $orderDetail = self::getListOrderDetail($value['ORId'], $currentBranchId);
                    $mergedOrders = [];
                    foreach ($orderDetail as $order) {
                        $k = $order['ProductId'] . '-' . $order['UnitId'];
                        if (!isset($mergedOrders[$k])) {
                            $mergedOrders[$k] = $order;
                        } else {
                            $mergedOrders[$k]['OrderQty'] += $order['OrderQty'];
                        }
                    }
                    $mergedOrders = array_values($mergedOrders);
                    $data[$key]["order_detail"] = $mergedOrders;

                    // add address
                    $data[$key]["Address"] = "";
                    $address = $value['branch'] ?? null;
                    if($address && !empty($address)){
                        $data[$key]["Address"] = $address['Address'] ?? "";
                    }
                    $data[$key]["LastPaymentAmount"] = $this->calLastPaymentAmount($value);
                }
            }

            return $data;

        } catch (\Exception $e) {
            Log::error("getListOrderByCustomer error", [$e->getMessage()]);
            return [];
        }
    }

    public function getListOrderDetail($oRId, $branchId)
    {
        $query = ORDetail::select('ORDetail.ORDetailId', 'ORDetail.ORId', 'ORDetail.ProductId', 'ORDetail.SKU', 'ORDetail.UnitId', 'ORDetail.ConditionTypeId', 'ORDetail.OrderQty', 'ORDetail.AffiliatePrice', 'ORDetail.SalePrice', 'ORDetail.DiscountType', 'ORDetail.DiscountAmount', 'ORDetail.TotalAmount', 'ORDetail.Note', 'ORDetail.CreatedBy', 'ORDetail.CreatedDate', 'ORDetail.UpdatedBy', 'ORDetail.UpdatedDate', 'ORDetail.PriceAfterDiscount', 'p.ProductName','p.IsExpiryDate')->where('ORId', $oRId);
        $query->join('sale.Product as p', 'p.ProductId', 'ORDetail.ProductId');
        $data = $query->get()->toArray();
        if(count($data) > 0){
            foreach($data as $key => $value){
                $data[$key]["UnitName"] = self::getUnitName($value['UnitId']);
                $data[$key]["AvailableQty"] = self::getAvailableQty($value['UnitId'], $value['ProductId'], $branchId);
                $data[$key]["ProductExpired"] = self::getProductExpired($value['UnitId'], $value['ProductId'], $branchId);
                if($data[$key]["ProductExpired"] == NULL){
                    $data[$key]["IsExpiryDate"] = 0;
                }
            }
        }
        
        return $data;
    }

    public function getProductExpired($unitId, $productId, $branchId)
    {
        $data = InvProductExpired::select(['ProductId','UnitId','AvailableQty','ManufactureDate','ExpiredDate','LOT'])->where('ProductId', $productId)->where('UnitId', $unitId)->where('BranchId', $branchId)->where('AvailableQty', '>', 0)->orderBy('ExpiredDate')->get()->toArray();
    
        return $data;
    }

    public function getUnitName($unitId)
    {
        $data = Unit::select(['Name'])->where('UnitId', $unitId)->first();
        $name = NULL;
        if($data){
            $name = $data->Name;
        }
        return $name;
    }

    public function getAvailableQty($unitId, $productId, $branchId)
    {
        $data = InvProduct::select(['AvailableQty'])->where('UnitId', $unitId)->where('ProductId', $productId)->where('BranchId', $branchId)->first();
        $availableQty = 0;
        if($data){
            $availableQty = $data->AvailableQty;
        }
        return $availableQty;
    }

    public function updateOrderStatus($request)
    {

        $oRId = $request['ORId'] ?? 0;
        $staffId = Auth::user()['StaffId'] ?? 0;
        $status = $request['Status'] ?? 0;
        $note = $request['Note'] ?? '';
        $products = $request['Products'] ?? [];
        $productExpired = $request['ProductExpired'] ?? [];
        $discountAmount = $request['DiscountAmount'] ?? 0;
        $paidAmount = $request['PaidAmount'] ?? 0;
        $shippingFeeAmount = $request['ShippingFeeAmount'] ?? 0;
        $shippingFeePaidSide = $request['ShippingFeePaidSide'] ?? 1;
        $totalRevenue = $request['TotalRevenue'] ?? 0;
        $totalRevenue = intval($totalRevenue - $shippingFeeAmount);
        $staffIdOrDoctor = $request['StaffId'] ?? 0;
        // Thông tin người giao hàng
        $shipperName = $request['ShipperName'] ?? '';
        $shipperPhone = $request['ShipperPhone'] ?? '';
        $providerId = $request['ProviderId'] ?? 0;
        $trackingCode = $request['TrackingCode'] ?? '';
        $isDeliveryUpdate = $request['IsDelivery'] ?? 0;
        if(!$staffIdOrDoctor) {
            $staffIdOrDoctor = 0;
        }

        // handle note
        if ($note && !empty($note)) {
            $note = base64_encode($note);
        }

        try {

            $obProduct = ProductOR::select(['*'])->where('ORId', $oRId)->first();
            $detailProduct = ORDetail::select(['*'])->where('ORId', $oRId)->get()->toArray();
            if(!$obProduct){
                $result[0] = (Object) [
                    "Result" => 0,
                    "ResultMessage" => "Đơn hàng không tồn tại hoặc đã bị xoá."
                ];
                return $result;
            }
            $branchId = $obProduct->BranchId;

            $paymentStatus = $obProduct->PaymentStatus;
            $sourceType = $obProduct->SourceType;
            $customerId = $obProduct->CustomerId;
            /**
             * Status 90: Returned
             * Status 99: Completed
             */
            if($sourceType == 1 & ($status == 90 || $status == 99) & $paymentStatus != 20 && $customerId == 0){
                $result[0] = (Object) [
                    "Result" => 0,
                    "ResultMessage" => "Bạn chỉ có thể hoàn thành đơn hàng offline khi đơn hàng có TT thanh toán là 'Đã thanh toán'"
                ];
                return $result;
            }

            if($status == 1){ // Chỉnh sửa đơn hàng
                $jsonProduct = '';
                if(count($products) > 0){
                    foreach($products as $value){
                        $affiliatePrice = isset($value['AffiliatePrice']) ? (int)$value['AffiliatePrice'] : 0;
                        $salePrice = isset($value['SalePrice']) ? (int)$value['SalePrice'] : 0;
                        $priceAfterDiscount = isset($value['PriceAfterDiscount']) ? (int)$value['PriceAfterDiscount'] : 0;
                        $dataInsertDetail[] = [
                            'ProductId' => $value['ProductId'],
                            'ConditionTypeId' => 1,
                            'UnitId' => $value['UnitId'],
                            'SKU' => $value['SKU'],
                            'OrderQty' => $value['Qty'],
                            'ManufactureDate' => '',
                            'ExpirationDate' => '',
                            'RefPrice' => "0",    
                            'AffiliatePrice' => "$affiliatePrice",
                            'SalePrice' => "$salePrice",
                            'PriceAfterDiscount' => "$priceAfterDiscount",
                            'DiscountType' => 0,
                            'DiscountAmount' => 0,
                            'Note' => ''

                        ];
                    }
                    $jsonProduct = json_encode($dataInsertDetail);
                }
                
                $queryStore = "CALL sale.sp_ManageOutboundReceipt('UPDATE', ".$oRId.", ".$branchId.", NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, ".$discountAmount.", 0, 0, 0, NULL, '".$note."', ".$staffId.", 0, ".$paidAmount.", 0, ".$shippingFeeAmount.", ".$shippingFeePaidSide.", ".$staffIdOrDoctor.", 1, ".$isDeliveryUpdate.", '".$jsonProduct."')";
                $result = DB::select(DB::raw($queryStore));
                Log::info("Update ORDER: " . $queryStore);
            
            }else{

                if($status == 20){ // Xác nhận đơn hàng

                    $queryStore = "CALL sale.sp_ManageOutboundReceipt('CONFIRM', ".$oRId.", ".$branchId.", NULL, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,NULL, NULL, '".$note."', ".$staffId.", NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL)";
                    $result = DB::select(DB::raw($queryStore));
                    Log::info("CONFIRM ORDER: " . $queryStore);
                
                } else if($status == 30){ // Giao hàng dành cho điều chuyển kho
                    $queryStore = "CALL sale.sp_ManageOutboundReceipt('DELIVERING', ".$oRId.", ".$branchId.", NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,NULL, NULL, NULL, ".$staffId.", NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL)";
                    $result = DB::select(DB::raw($queryStore));
                    Log::info("DELIVERING ORDER: " . $queryStore);
                    // Thông tin người giao hàng
                    $isDelivery = $obProduct->IsDelivery;
                    if($isDelivery == 1){
                        $data = [
                            'TrackingCode' => $trackingCode,
                            'ProviderId' => $providerId,
                            'ShipperName' => $shipperName,
                            'ShipperPhone' => $shipperPhone,
                            'Status' => 1,
                            'ShippedDate' => date('Y-m-d H:i:s'),
                            'CreatedDate' => date('Y-m-d H:i:s'),
                        ];
                        $shipmentId = Shipment::insertGetId($data);
                        $dataUpdateOR = [
                            'ShipmentId' => $shipmentId
                        ];
                        ProductOR::where('ORId', $oRId)->update($dataUpdateOR);
                        Log::info("INSERT SHIPMENT PROVIDER: " . json_encode($data));
                    }
                } else if($status == 35){ // Giao hàng thất bại
                    $queryStore = "CALL sale.sp_ManageOutboundReceipt('DELIVERY_FAILED', ".$oRId.", ".$branchId.", NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,NULL, NULL, NULL, ".$staffId.", NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL)";
                    $result = DB::select(DB::raw($queryStore));
                    Log::info("DELIVERY FAILED ORDER: " . $queryStore);
                } else if($status == 85){ // Hoàn trả hàng toàn bộ thành công
                    $queryStore = "CALL sale.sp_ManageOutboundReceipt('SUCCESSFULLY_RETURNED', ".$oRId.", ".$branchId.", NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,NULL, NULL, NULL, ".$staffId.", NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL)";
                    $result = DB::select(DB::raw($queryStore));
                    Log::info("SUCCESSFULLY RETURNED ORDER: " . $queryStore);
                } else if($status == 100){ // Huỷ đơn hàng
                    $queryStore = "CALL sale.sp_ManageOutboundReceipt('CANCEL', ".$oRId.", ".$branchId.", NULL, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,NULL, NULL, '".$note."', ".$staffId.", NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL)";
                    $result = DB::select(DB::raw($queryStore));
                    Log::info("CANCEL ORDER: " . $queryStore);
            
                } else { // Hoàn thành đơn hàng
                    $jsonProduct = '';
                    if(count($productExpired) > 0){
                        foreach($productExpired as $v){
                            $affiliatePrice = isset($v['AffiliatePrice']) ? (int)$v['AffiliatePrice'] : 0;
                            $salePrice = isset($v['SalePrice']) ? (int)$v['SalePrice'] : 0;
                            $priceAfterDiscount = isset($v['PriceAfterDiscount']) ? (int)$v['PriceAfterDiscount'] : 0;
                            if($v['IsExpiryDate'] && isset($v['Expiry'])){
                                if(count($v['Expiry']) > 0){
                                    foreach($v['Expiry'] as $expiry){
                                        $data[] = [
                                            'ProductId' => $expiry['ProductId'],
                                            'ConditionTypeId' => 1,
                                            'UnitId' => $expiry['UnitId'],
                                            'SKU' => $expiry['SKU'],
                                            'OrderQty' => $expiry['Qty'],
                                            'ManufactureDate' => $expiry['ManufactureDate'],
                                            'ExpirationDate' => $expiry['ExpiredDate'],
                                            'RefPrice' => "0",    
                                            'AffiliatePrice' => "$affiliatePrice",
                                            'SalePrice' => "$salePrice",
                                            'PriceAfterDiscount' => "$priceAfterDiscount",
                                            'DiscountType' => 0,
                                            'DiscountAmount' => 0,
                                            'Note' => '',
                                            'LOT' => Helper::cleanString($expiry['LOT'])
                                        ];
                                    }
                                }
                            } else {
                                $data[] = [
                                    'ProductId' => $v['ProductId'],
                                    'ConditionTypeId' => 1,
                                    'UnitId' => $v['UnitId'],
                                    'SKU' => $v['SKU'],
                                    'OrderQty' => $v['OrderQty'],
                                    'ManufactureDate' => '',
                                    'ExpirationDate' => '',
                                    'RefPrice' => "0",    
                                    'AffiliatePrice' => "$affiliatePrice",
                                    'SalePrice' => "$salePrice",
                                    'PriceAfterDiscount' => "$priceAfterDiscount",
                                    'DiscountType' => 0,
                                    'DiscountAmount' => 0,
                                    'Note' => '',
                                    'LOT' => ''
                                ];
                            }
                        }
                        $jsonProduct = json_encode($data);
                    }
                    $queryStore = "CALL sale.sp_ManageOutboundReceipt('FINISH', ".$oRId.", ".$branchId.", NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,NULL, NULL, '".$note."', ".$staffId.", NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '".$jsonProduct."')";
                    $result = DB::select(DB::raw($queryStore));
                    Log::info("FINISH ORDER: " . $queryStore);
                    // Cập nhật thời gian giao hàng
                    $isDelivery = $obProduct->IsDelivery;
                    $shipmentId = $obProduct->ShipmentId;
                    if($isDelivery == 1){
                        $dataUpdateShipment = [
                            'DeliveredDate' => date('Y-m-d H:i:s'),
                        ];
                        Shipment::where('ShipmentId', $shipmentId)->update($dataUpdateShipment);
                    }
                } 
            }
            return $result;

        } catch (\Exception $e) {
            Log::error("updateOrderStatus error", [$e->getMessage()]);
            return false;
        }
        return false;
    }

    public function getShipmentProvider()
    {
        $data = ShipmentProvider::select(['*'])->where('Status', 1)->get()->toArray();
        return $data;
    }

    public function checkTrackingCode($providerId, $trackingCode)
    {
        $data = Shipment::select(['*'])->where('ProviderId', $providerId)->where('TrackingCode', $trackingCode)->first();
        return $data;
    }

    public function updateOR($request)
    {
        $oRId = $request['ORId'] ?? 0;
        $branchId = $request['BranchId'] ?? 0;
        $currentBranchId = $request['CurrentBranchId'] ?? 0;
        $sourceType = $request['SourceType'] ?? 0;
        $refCode = $request['RefCode'] ?? '';
        $affiliateAccountId = $request['AffiliateAccountId'] ?? 0;
        if (!$affiliateAccountId) {
            $affiliateAccountId = 0;
        }
        $affiliateAmount = $request['AffiliateAmount'] ?? 0;
        $note = $request['Note'] ?? '';
        $customerId = $request['CustomerId'] != '' ? $request['CustomerId'] : 0;
        $customerName = $request['CustomerName'] ?? '';
        $customerPhoneNumber = $request['CustomerPhoneNumber'] ?? 0;
        $customerEmail = $request['Email'] ?? '';
        $provinceId = $request['ProvinceId'] != '' ? $request['ProvinceId'] : 0;
        $provinceName = $request['ProvinceName'] ?? '';
        $labelProvince = $request['LabelProvince'] ?? '';
        $districtId = $request['DistrictId'] != '' ? $request['DistrictId'] : 0;
        $districtName = $request['DistrictName'] ?? '';
        $labelDistrict = $request['LabelDistrict'] ?? '';
        $wardId = $request['WardId'] != '' ? $request['WardId'] : 0;
        $wardName = $request['WardName'] ?? '';
        $labelWard = $request['LabelWard'] ?? '';
        $address = $request['Address'] ?? '';
        $staffId = Auth::user()['StaffId'] ?? 0;
        $products = $request['Products'] ?? [];
        $discountAmount = $request['DiscountAmount'] ?? 0;
        $paidAmount = $request['PaidAmount'] ?? 0;
        $shippingFeeAmount = $request['ShippingFeeAmount'] ?? 0;
        $shippingFeePaidSide = $request['ShippingFeePaidSide'] ?? 1;
        $staffIdOrDoctor = $request['StaffId'] ?? 0;
        $recipient = $customerName;
        $recipientPhone = $customerPhoneNumber;
        $isDelivery = $request['IsDelivery'] ?? 0;
        if(isset($request['Recipient']) && $request['Recipient'] != ""){
            $recipient = $request['Recipient'];
        }
        if(isset($request['RecipientPhone']) && $request['RecipientPhone'] != ""){
            $recipientPhone = $request['RecipientPhone'];
        }
        if (!isset($shippingFeePaidSide) || empty($shippingFeePaidSide)) {
            $shippingFeePaidSide = 0;
        }
        if (!isset($affiliateAmount) || empty($affiliateAmount)) {
            $affiliateAmount = 0;
        }
        if (!isset($staffIdOrDoctor) || empty($staffIdOrDoctor)) {
            $staffIdOrDoctor = 0;
        }
        $totalRevenue = $request['TotalRevenue'] ?? 0;
        $totalRevenue = intval($totalRevenue - $shippingFeeAmount);
        $addressParts = [];
        $noAddressParts = [];

        if (!empty($address)) {
            $addressParts[] = $address;
        }

        if (!empty($labelWard) && !empty($wardName)) {
            $addressParts[] = $labelWard . ' ' . $wardName;
        }

        if (!empty($labelDistrict) && !empty($districtName)) {
            $addressParts[] = $labelDistrict . ' ' . $districtName;
            $noAddressParts[] = $labelDistrict . ' ' . $districtName;
        }

        if (!empty($labelProvince) && !empty($provinceName)) {
            $addressParts[] = $labelProvince . ' ' . $provinceName;
            $noAddressParts[] = $labelProvince . ' ' . $provinceName;
        }

        $shippingFullAddress = implode(', ', $addressParts);
        $shippingAddressNo = implode(', ', $noAddressParts);

        if($address == '' && $wardName == '' && $districtName && $provinceName){
            $shippingFullAddress = NULL;
            $shippingAddressNo = NULL;
        }


        if (!$branchId || empty($branchId) || !is_numeric($branchId)){
            $branchId =  $currentBranchId;
        }

        // handle note
        if ($note && !empty($note)) {
            $note = base64_encode($note);
        }

        try {

            $jsonProduct = '';
            if(count($products) > 0){
                foreach($products as $value){
                    $salePrice = (int)$value['SalePrice'];
                    $affiliatePrice = isset($value['AffiliatePrice']) ? (int)$value['AffiliatePrice'] : 0;
                    $priceAfterDiscount = isset($value['PriceAfterDiscount']) ? (int)$value['PriceAfterDiscount'] : 0;
                    $dataInsertDetail[] = [
                        'ProductId' => $value['ProductId'],
                        'ConditionTypeId' => 1,
                        'UnitId' => $value['UnitId'],
                        'SKU' => $value['SKU'],
                        'OrderQty' => $value['Qty'],
                        'ManufactureDate' => '',
                        'ExpirationDate' => '',
                        'RefPrice' => "0",    
                        'AffiliatePrice' => "$affiliatePrice",
                        'SalePrice' => "$salePrice",
                        'PriceAfterDiscount' => "$priceAfterDiscount",
                        'DiscountType' => 0,
                        'DiscountAmount' => 0,
                        'Note' => ''

                    ];
                }
                $jsonProduct = json_encode($dataInsertDetail);
            }
            $params = [
                'action' => 'UPDATE',
                'oRId' => $oRId,
                'branchId' => $branchId,
                'refCode' => $refCode,
                'sourceType' => $sourceType,
                'customerId' => $customerId,
                'customerName' => $customerName,
                'customerPhoneNumber' => $customerPhoneNumber,
                'customerEmail' => $customerEmail,
                'recipient' => $recipient,
                'recipientPhone' => $recipientPhone,
                'shippingFullAddress' => $shippingFullAddress,
                'shippingAddressNo' => $shippingAddressNo,
                'address' => $address,
                'provinceId' => $provinceId,
                'provinceName' => $provinceName,
                'districtId' => $districtId,
                'districtName' => $districtName,
                'wardId' => $wardId,
                'wardName' => $wardName,
                'discountAmount' => $discountAmount,
                'affiliateAccountId' => $affiliateAccountId,
                'affiliateAmount' => $affiliateAmount,
                'note' => $note,
                'staffId' => $staffId,
                'paidAmount' => $paidAmount,
                'shippingFeeAmount' => $shippingFeeAmount,
                'shippingFeePaidSide' => $shippingFeePaidSide,
                'staffIdOrDoctor' => $staffIdOrDoctor,
                'isDelivery' => $isDelivery,
                'jsonProduct' => $jsonProduct,
            ];
            $queryStore = "CALL sale.sp_ManageOutboundReceipt(:action, :oRId, :branchId, :refCode, 1, 1, :sourceType, :customerId, :customerName, :customerPhoneNumber, :customerEmail, NULL, :recipient, :recipientPhone, :shippingFullAddress, :shippingAddressNo, :address, :provinceId, :provinceName, :districtId, :districtName, :wardId, :wardName, :discountAmount, 0, :affiliateAccountId, :affiliateAmount,NULL, :note, :staffId, 0, :paidAmount, 0, :shippingFeeAmount, :shippingFeePaidSide,:staffIdOrDoctor, 1, :isDelivery, :jsonProduct)";
            $result = DB::select(DB::raw($queryStore), $params);
            Log::info("updateOR: " . $queryStore);
            return $result;

        } catch (\Exception $e) {
            Log::error("updateOR error", [$e->getMessage()]);
            return false;
        }
        return false;
    }

    public function checkReceiptOrder($oRId)
    {
        $oRId = ProductOR::select(['ORId'])
            ->where('ORId', $oRId)->where('SourceType', 1)->where('ORStatus', 1)->whereNull('ReceiptId')->first();
        return $oRId;
    }

    public function checkInvProductExpired($status, $productExpired, $currentBranchId)
    {
        if($status == 99){
            if(count($productExpired) > 0){
                foreach($productExpired as $v){
                    if($v['IsExpiryDate'] && isset($v['Expiry'])){
                        if(count($v['Expiry']) > 0){
                            foreach($v['Expiry'] as $expiry){
                                $expiredDate = date('Y-m-d 00:00:00');
                                if($expiry['ExpiredDate']){
                                    $expiredDate = $expiry['ExpiredDate'];
                                }
                                $manufactureDate = date('Y-m-d 00:00:00');
                                if($expiry['ManufactureDate']){
                                    $manufactureDate = $expiry['ExpiredDate'];
                                }
                                $result = InvProductExpired::where('ProductId', $expiry['ProductId'])->where('UnitId', $expiry['UnitId'])->where('LOT', $expiry['LOT'])->where('ExpiredDate', $expiredDate)->where('ManufactureDate', $manufactureDate)->where('BranchId', $currentBranchId)->where('AvailableQty','<',$v['Qty'])->first();
                                if($result){
                                    return false;
                                }
                            }
                        }
                    }
                }
            }
        }
        return true;
    }

    public function checkAvailableQtyOrder($oRId, $status, $currentBranchId)
    {
        $detailProduct = ORDetail::select(['*'])->where('ORId', $oRId)->get()->toArray();

        if(count($detailProduct) > 0){
            foreach($detailProduct as $va){
                if($status == 99){
                    $result = InvProduct::where('ProductId', $va['ProductId'])->where('UnitId', $va['UnitId'])->where('BranchId', $currentBranchId)->where('AvailableQty','<',$va['OrderQty'])->first();
                    if($result){
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function checkOrder($oRId, $status)
    {
        $oRId = ProductOR::select(['ORId'])
            ->where('ORId', $oRId)->where('ORStatus', $status)->where('ORStatus', '<>', 1)->first();

        return $oRId;
    }

    public function checkOrderIsNew($oRId)
    {
        $oRId = ProductOR::select(['ORId'])
            ->where('ORId', $oRId)->where('ORStatus', 1)
            ->first();
    }

    public function getListConditionProduct()
    {
        try {

            $query = ConditionType::select(['*'])->where('IsDeleted', 0);
            $query->with(['createdByStaff' => function ($query) {
                $query->select('StaffId', 'FullName', 'StaffCode');
            }]);
            $data = $query->orderByRaw('Priority ASC')->get()->toArray();

            return $data;

        } catch (\Exception $e) {
            Log::error("getListConditionProduct error", [$e->getMessage()]);
            return [];
        }
    }

    public function getOrderUnPaidByCustomer($customerId)
    {
        
        if (!$customerId || empty($customerId)){
            return [];
        }
        $data = [];

        try {

            $query = ProductOR::select(['OR.ORId','OR.BranchId','OR.ORCode','OR.ORStatus','OR.SourceType','OR.CustomerId','OR.CustomerName','OR.CustomerPhoneNumber','OR.ShippingFullAddress','OR.TotalAmount','OR.DiscountAmount','OR.PaymentAmount','OR.CODAmount','OR.TotalRevenue','OR.TotalItem','OR.TotalSKU','OR.CreatedBy','OR.CreatedDate','OR.UpdatedBy','OR.UpdatedDate','OR.AffiliateAccountId','OR.Recipient','OR.RecipientPhone','b.BranchCode','b.Name as BranchName'])
                ->join('in.Branch as b', 'b.BranchId', 'OR.BranchId')    
                ->where('OR.CustomerId', $customerId);
            
            $query->where('OR.PaymentStatus', 0); //Unpaid

            //Theo yêu cầu của anh Bình mình chỉ lấy đơn hàng là đơn thuốc hoặc offline
            $query->whereIn('OR.SourceType', [1,110]); //Offline

            $query->where('OR.ORStatus', '<>', 100); //Cancelled

            $query->where(function($subQuery) {
                $subQuery->whereNull('ReceiptId')
                    ->orWhere('ReceiptId', 0);
            });

            //Relationship
            $query->with(['createdByStaff' => function ($query) {
                $query->select('StaffId', 'FullName', 'StaffCode');
            }]);

            $query->orderByDesc('OR.ORId');
            
            $data = $query->get();
            
            if(count($data) > 0){
                foreach($data as $value){
                    $value->LastPaymentAmount = $this->calLastPaymentAmount($value);
                    $value->OrderDetail = self::getListOrderDetail($value->ORId, 0);
                }
            }
            return $data;

        } catch (\Exception $e) {
            Log::error("getOrderUnPaidByCustomer error", [$e->getMessage()]);
            return [];
        }
        return $data;

    }

    public function getAllListOrder($request)
    {
        $fromDate = $request['FromDate'] ?? '';
        $toDate = $request['ToDate'] ?? '';
        $affiliateAccountId = $request['AffiliateAccountId'] ?? 0;
        $keyword = $request['Keyword'] ?? '';
        $filterType = $request['FilterType'] ?? 1;
        $filterPayment = $request['FilterPayment'] ?? 0;
        $sourceType = $request['SourceType'] ?? 0;
        $branchId = $request['BranchId'] ?? 0;
        $currentBranchId = $request['CurrentBranchId'] ?? 0;
        $lmstart = $request['lmstart'] ?? 0;
        $limit = $request['limit'] ?? 20;
        $staffId = Auth::user()['StaffId'] ?? 0;

        if (empty($branchId) || !is_numeric($branchId) || $branchId < 0){
            $branchId =  $currentBranchId;
        }

        try {
            
            $query = ProductOR::select(['OR.ORId','OR.BranchId','OR.ORCode','OR.ORType','OR.RefCode','OR.ORStatus','OR.CustomerId','OR.CustomerName','OR.CustomerPhoneNumber','OR.CustomerEmail','OR.ShippingFullAddress','OR.ShippingAddressNo','OR.TotalAmount','OR.DiscountAmount','OR.PaymentAmount', 'OR.PaymentStatus', 'OR.CODAmount','OR.TotalRevenue','OR.TotalItem','OR.TotalSKU','OR.CreatedBy','OR.CreatedDate','OR.UpdatedBy','OR.UpdatedDate','OR.AffiliateAccountId','OR.AffiliateAmount','OR.Note','OR.SourceType','OR.ShippingFeeAmount','OR.ShippingFeePaidSide','OR.PaidAmount','OR.ConsultingStaffId','OR.ShipmentId','OR.IsDelivery','b.BranchCode','b.Name as BranchName', 'b.Address','aa.FullName as AffiliateAccountName','aa.AffCode','OR.Recipient','OR.RecipientPhone'])
                ->join('in.Branch as b', 'b.BranchId', 'OR.BranchId')
                ->leftJoin('sale.AffiliateAccount as aa', 'aa.AffiliateAccountId', 'OR.AffiliateAccountId');
            $query->with(['createdByStaff' => function ($query) {
                $query->select('StaffId', 'FullName', 'StaffCode');
            }]);
            $query->with(['assignByDoctor' => function ($query) {
                $query->select('StaffId', 'FullName', 'StaffCode');
            }]);
            $query->with(['shipment' => function ($query) {
                $query->select('ShipmentId', 'TrackingCode', 'ProviderId', 'ShipperName', 'ShipperPhone');
                $query->with(['shipmentProvider' => function ($query) {
                    $query->select('ProviderId', 'ProviderCode', 'ProviderName');
                }]);
            }]);
            
            if($branchId && $branchId > 0){
                $query->where('OR.BranchId', $branchId);
            }

            if($keyword != '') {
                if($filterType == 1){
                    $query->where('OR.ORCode', 'LIKE', '%' . $keyword . '%');
                }elseif($filterType == 2){
                    $query->join('sale.ORDetail as ord', 'ord.ORId', '=', 'OR.ORId')
                    ->join('sale.Product as p', 'p.ProductId', '=', 'ord.ProductId')
                    ->where('p.SKU', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('p.ProductName', 'LIKE', '%' . $keyword . '%')
                    ->groupBy('OR.ORId');
                }elseif($filterType == 3){
                    $query->where('OR.CustomerName', 'LIKE', '%' . $keyword . '%');
                }else{
                    $query->where('OR.RefCode', 'LIKE', '%' . $keyword . '%');
                }
            }

            if($filterPayment > 0){
                $query->where('OR.PaymentStatus', 0);
            }

            if($affiliateAccountId > 0){
                $query->where('OR.AffiliateAccountId', $affiliateAccountId);
            }

            if($sourceType > 0){
                $query->where('OR.SourceType', $sourceType);
            }
            $query->where('OR.CreatedDate', '>=', $fromDate." 00:00:01");
            $query->where('OR.CreatedDate', '<=', $toDate." 23:59:59");

            $query->orderByDesc('OR.ORId');

            $data = $query->paginate($limit, ['*'], 'page', round((int) $lmstart/ (int) $limit) + 1);
            
            if(count($data) > 0){
                foreach($data as $key => $value){
                    $orderDetail = self::getListOrderDetail($value->ORId, $value->BranchId);
                    $mergedOrders = [];
                    foreach ($orderDetail as $order) {
                        $key = $order['ProductId'] . '-' . $order['UnitId'];
                        if (!isset($mergedOrders[$key])) {
                            $mergedOrders[$key] = $order;
                        } else {
                            $mergedOrders[$key]['OrderQty'] += $order['OrderQty'];
                        }
                    }
                    $mergedOrders = array_values($mergedOrders);
                    $value->OrderDetail = $mergedOrders;

                    // Chỉ cho phép người tạo được chỉnh sửa
                    $value->IsEdit = false;
                    if($value->CreatedBy == $staffId){
                        $value->IsEdit = true;
                    }
                    $value->LastPaymentAmount = $this->calLastPaymentAmount($value);
                }
            }
            return $data;

        } catch (\Exception $e) {
            Log::error("getAllListOrder error", [$e->getMessage()]);
            return [];
        }
    }

    public function getOrderById($request)
    {
        $ORId = $request['ORId'] ?? 0;

        try {

            $query = ProductOR::select(['OR.ORId','OR.BranchId','OR.RefCode','OR.ORCode','OR.ORType','OR.ORStatus','OR.CustomerId','OR.CustomerName','OR.CustomerPhoneNumber','OR.CustomerEmail','OR.ShippingFullAddress','OR.ShippingAddressNo', 'OR.ShippingAddressNumber as Address', 'OR.ShippingProvinceId as ProvinceId', 'OR.ShippingProvinceName as ProvinceName', 'OR.ShippingDistrictId as DistrictId', 'OR.ShippingDistrictName as DistrictName', 'OR.ShippingWardId as WardId', 'OR.ShippingWardName as WardName','OR.TotalAmount','OR.DiscountAmount','OR.PaymentAmount', 'OR.PaymentStatus', 'OR.CODAmount','OR.TotalRevenue','OR.TotalItem','OR.TotalSKU','OR.CreatedBy','OR.CreatedDate','OR.UpdatedBy','OR.UpdatedDate','OR.AffiliateAccountId','OR.AffiliateAmount','OR.Note','OR.SourceType','OR.ShippingFeeAmount','OR.ShippingFeePaidSide','OR.PaidAmount','OR.ConsultingStaffId','OR.ShipmentId','OR.IsDelivery','b.BranchCode','b.Name as BranchName','aa.FullName as AffiliateAccountName','aa.AffCode','OR.Recipient','OR.RecipientPhone'])
                ->join('in.Branch as b', 'b.BranchId', 'OR.BranchId')
                ->leftJoin('sale.AffiliateAccount as aa', 'aa.AffiliateAccountId', 'OR.AffiliateAccountId')
                ->where('OR.ORId', $ORId);
            $query->with(['createdByStaff' => function ($query) {
                $query->select('StaffId', 'FullName', 'StaffCode');
            }]);
            $query->with(['assignByDoctor' => function ($query) {
                $query->select('StaffId', 'FullName', 'StaffCode');
            }]);
            $query->with(['shipment' => function ($query) {
                $query->select('ShipmentId', 'TrackingCode', 'ProviderId', 'ShipperName', 'ShipperPhone');
                $query->with(['shipmentProvider' => function ($query) {
                    $query->select('ProviderId', 'ProviderCode', 'ProviderName');
                }]);
            }]);

            $data = $query->get();

            if(count($data) > 0){
                foreach($data as $key => $value){
                    $orderDetail = self::getListOrderDetail($value->ORId, $value->BranchId);
                    $mergedOrders = [];
                    foreach ($orderDetail as $order) {
                        $key = $order['ProductId'] . '-' . $order['UnitId'];
                        if (!isset($mergedOrders[$key])) {
                            $mergedOrders[$key] = $order;
                        } else {
                            $mergedOrders[$key]['OrderQty'] += $order['OrderQty'];
                        }
                    }
                    $mergedOrders = array_values($mergedOrders);
                    $value->OrderDetail = $mergedOrders;
                    $value->LastPaymentAmount = $this->calLastPaymentAmount($value);
                }
            }
            return $data;

        } catch (\Exception $e) {
            Log::error("getOrderById error", [$e->getMessage()]);
            return [];
        }
    }

    public function getOrderDetailById($request)
    {
        $data = $this->getOrderById($request);

        if (!isset($data) || empty($data)) {
            return [];
        }

        // Bổ sung label cho Quận/Huyện Phường
        foreach ($data as $value) {
            $value->LabelProvince = '';
            $value->LabelDistrict = '';
            $value->LabelWard = '';

            if (isset($value->ProvinceId) && !empty($value->ProvinceId)) {
                $labelProvince = VnProvince::select('LabelVi')->where('VnProvinceId', $value->ProvinceId)->first();
                $value->LabelProvince = $labelProvince->LabelVi ?? '';
            }
            if (isset($value->DistrictId) && !empty($value->DistrictId)) {
                $labelDistrict = VnDistrict::select('LabelVi')->where('VnDistrictId', $value->DistrictId)->first();
                $value->LabelDistrict = $labelDistrict->LabelVi ?? '';
            }
            if (isset($value->WardId) && !empty($value->WardId)) {
                $labelWard = VnWard::select('LabelVi')->where('VnWardId', $value->WardId)->first();
                $value->LabelWard = $labelWard->LabelVi ?? '';
            }
        }

        return $data;
    }

    public function getTotalReceiptByCustomerId($request)
    {
        $customerId = $request['CustomerId'] ?? 0;

        $totalReceipt = Deposit::select(DB::raw('SUM(r.TotalAmount) as TotalAmount'))->join('pos.Receipt as r', 'r.DepositId', 'Deposit.DepositId')->where('Deposit.CustomerId', $customerId)->first();
        return $totalReceipt->TotalAmount ?? 0;
    }

    public function getAffiliateAccount() 
    {

        try {

            $data = AffiliateAccount::select(['*'])->get()->toArray();

            return $data;

        } catch (\Exception $e) {
            Log::error("getAffiliateAccount error", [$e->getMessage()]);
            return [];
        }
    }
    
    public function getReportOrderByBranch($data)
    {
        $fromDate = $data['FromDate'] ?? Carbon::parse('00:00:00')->subMonths(1)->startOfMonth()->toDateTimeString();
        $toDate = $data['ToDate'] ?? Carbon::parse('23:59:59')->subMonths(1)->endOfMonth()->toDateTimeString();
        $sourceType = $data['SourceType'] ?? 'NULL';
        $branchId = $data['BranchId'] ?? 'NULL';
        $currentBranchId = $data['CurrentBranchId'] ?? 'NULL';
        $lmstart = $data['lmstart'] ?? 0;
        $limit = $data['limit'] ?? 20;

        if (!isset($lmstart) || empty($lmstart)) {
            $lmstart = 0;
        }

        if (!isset($limit) || empty($limit)) {
            $limit = 20;
        }

        if (!isset($sourceType) || empty($sourceType)) {
            $sourceType = 'NULL';
        }

        if (!isset($branchId) || empty($branchId) || !is_numeric($branchId) || $branchId < 1) {
            $branchId = 'NULL';
        }

        try {
            $paramStore = "$branchId, $sourceType, '$fromDate', '$toDate'";
            $storeReport = DB::select(DB::raw("CALL sale.usp_GetSalesReportByBranch($paramStore, $lmstart, $limit)"));
            $totalStoreReport = DB::select(DB::raw("CALL sale.usp_GetSalesReportByBranch($paramStore, 0, NULL)"));
            Log::info("CALL sale.usp_GetSalesReportByBranch($paramStore, $lmstart, $limit)");
            // Tính thêm dòng tổng cho issue 251
            $totalRow = (object) [
                'TotalOR' => 0,
                'TotalAmount' => 0,
                'TotalDiscountAmount' => 0,
                'TotalProduct' => 0,
                'TotalRevenue' => 0,
                'TotalAffiliateAmount' => 0,
            ];
            
            if (!empty($totalStoreReport)) {
                foreach ($totalStoreReport as $value) {
                    $totalRow->TotalOR += $value->TotalOR ?? 0;
                    $totalRow->TotalAmount += $value->TotalAmount ?? 0;
                    $totalRow->TotalDiscountAmount += $value->TotalDiscountAmount ?? 0;
                    $totalRow->TotalProduct += $value->TotalProduct ?? 0;
                    $totalRow->TotalRevenue += $value->TotalRevenue ?? 0;
                    $totalRow->TotalAffiliateAmount += $value->TotalAffiliateAmount ?? 0;
                }
            }
            return [
                'Data' => $storeReport, 
                'TotalRow' => $totalRow
            ];
        } catch (\Exception $e) {
            Log::error('Error ', [$e->getMessage()]);
            Log::info("CALL sale.usp_GetSalesReportByBranch($branchId, $sourceType, '$fromDate', '$toDate', $lmstart, $limit)");
            return [];
        }

        return $storeReport;
    }

    public function getOrderReportByTime($data)
    {
        $sourceType = $data['SourceType'] ?? 'NULL';
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

        if (isset($sourceType) && empty($sourceType)) {
            $sourceType = 'NULL';
        }

        try {
            $storeReport = DB::select("CALL sale.usp_GetSalesReportByTime($sourceType, '$fromDate', '$toDate', $lmstart, $limit)");
            $totalStoreReport = DB::select("CALL sale.usp_GetSalesReportByTime($sourceType, '$fromDate', '$toDate', 0, NULL)");
            Log::info("CALL sale.usp_GetSalesReportByTime($sourceType, '$fromDate', '$toDate', $lmstart, $limit)");
            // Tính thêm dòng tổng cho issue 251
            $totalRow = (object) [
                'TotalAllOR' => 0,
                'TotalAllPaymentAmount' => 0,
                'TotalAllDiscountAmount' => 0,
                'TotalAllProduct' => 0,
                'TotalAllRevenue' => 0,
                'TotalAllAffiliateAmount' => 0,
            ];
            
            if (!empty($totalStoreReport)) {
                foreach ($totalStoreReport as $value) {
                    $totalRow->TotalAllOR += $value->TotalOR ?? 0;
                    $totalRow->TotalAllPaymentAmount += $value->TotalPaymentAmount ?? 0;
                    $totalRow->TotalAllDiscountAmount += $value->TotalDiscountAmount ?? 0;
                    $totalRow->TotalAllProduct += $value->TotalProduct ?? 0;
                    $totalRow->TotalAllRevenue += $value->TotalRevenue ?? 0;
                    $totalRow->TotalAllAffiliateAmount += $value->TotalAffiliateAmount ?? 0;
                }
            }
            return [
                'Data' => $storeReport, 
                'TotalRow' => $totalRow
            ];
        } catch (\Exception $e) {
            Log::error('Error ', [$e->getMessage()]);
            Log::info("CALL sale.usp_GetSalesReportByTime($sourceType, '$fromDate', '$toDate', $lmstart, $limit)");
        }

        return [];
    }

    public function getOrderReportByProduct($data)
    {
        $branchId = $data['BranchId'] ?? 0;
        $categoryId = $data['CategoryId'] ?? 'NULL';
        $brandId = $data['BrandId'] ?? 'NULL';
        $keyword = $data['Keyword'] ?? '';
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

        if (isset($categoryId) && empty($categoryId)) {
            $categoryId = 'NULL';
        }

        if (isset($brandId) && empty($brandId)) {
            $brandId = 'NULL';
        }

        if (!isset($branchId) || empty($branchId) || !is_numeric($branchId) || $branchId < 1) {
            $branchId = 'NULL';
        }
        
        try {
            $paramStore = "$branchId, $categoryId, '$keyword', $brandId, '$fromDate', '$toDate'";
            $storeReport = DB::select("CALL sale.usp_GetSalesReportByProduct($paramStore, $lmstart, $limit)");
            $totalStoreReport = DB::select("CALL sale.usp_GetSalesReportByProduct($paramStore, 0, NULL)");
            Log::info("CALL sale.usp_GetSalesReportByProduct($paramStore, $lmstart, $limit)");
            // Tính thêm dòng tổng cho issue 251
            $totalRow = (object) [
                'TotalProduct' => 0,
                'TotalSale' => 0,
                'TotalAffiliateAmount' => 0,
            ];
            
            if (!empty($totalStoreReport)) {
                foreach ($totalStoreReport as $value) {
                    $totalRow->TotalProduct += $value->TotalProduct ?? 0;
                    $totalRow->TotalSale += $value->TotalSale ?? 0;
                    $totalRow->TotalAffiliateAmount += $value->TotalAffiliateAmount ?? 0;
                }
            }
            return [
                'Data' => $storeReport, 
                'TotalRow' => $totalRow
            ];
        } catch (\Exception $e) {
            Log::error('Error ', [$e->getMessage()]);
            Log::info("CALL sale.usp_GetSalesReportByProduct($branchId, $categoryId, '$keyword', $brandId, '$fromDate', '$toDate', $lmstart, $limit)");
        }

        return [];
    }

    public function getOrderReportByStaff($data)
    {
        $keyword = $data['Keyword'] ?? '';
        $sourceType = $data['SourceType'] ?? 'NULL';
        $fromDate = $data['FromDate'] ?? Carbon::parse('00:00:00')->subMonths(1)->startOfMonth()->toDateTimeString();
        $toDate = $data['ToDate'] ?? Carbon::parse('23:59:59')->subMonths(1)->endOfMonth()->toDateTimeString();
        $lmstart = $data['lmstart'] ?? 0;
        $limit = $data['limit'] ?? 20;

        if (!isset($lmstart) || empty($lmstart)) {
            $lmstart = 0;
        }

        if (!isset($sourceType) || empty($sourceType)) {
            $sourceType = 'NULL';
        }

        if (!isset($limit) || empty($limit)) {
            $limit = 20;
        }

        if (!isset($branchId) || empty($branchId) || !is_numeric($branchId) || $branchId < 1) {
            $branchId = 'NULL';
        }
        
        $paramStore = "'$keyword', $sourceType, '$fromDate', '$toDate'";
        try {
            $storeReport = DB::select(DB::raw("CALL sale.usp_GetSalesReportByEmployeeDoctor($paramStore, $lmstart, $limit)"));
            $totalStoreReport = DB::select(DB::raw("CALL sale.usp_GetSalesReportByEmployeeDoctor($paramStore, 0, NULL)"));
            Log::info("CALL sale.usp_GetSalesReportByEmployeeDoctor($paramStore, $lmstart, $limit)");
            // Tính thêm dòng tổng cho issue 251
            $totalRow = (object) [
                'TotalOR' => 0,
                'TotalAmount' => 0,
                'TotalDiscountAmount' => 0,
                'TotalSKU' => 0,
                'TotalRevenue' => 0,
                'TotalAffiliateAmount' => 0,
            ];
            
            if (!empty($totalStoreReport)) {
                foreach ($totalStoreReport as $value) {
                    $totalRow->TotalOR += $value->TotalOR ?? 0;
                    $totalRow->TotalAmount += $value->TotalAmount ?? 0;
                    $totalRow->TotalDiscountAmount += $value->TotalDiscountAmount ?? 0;
                    $totalRow->TotalSKU += $value->TotalSKU ?? 0;
                    $totalRow->TotalRevenue += $value->TotalRevenue ?? 0;
                    $totalRow->TotalAffiliateAmount += $value->TotalAffiliateAmount ?? 0;
                }
            }

            return [
                'Data' => $storeReport, 
                'TotalRow' => $totalRow
            ];
        } catch (\Exception $e) {
            Log::error('Error ', [$e->getMessage()]);
            Log::info("CALL sale.usp_GetSalesReportByEmployeeDoctor($paramStore, $lmstart, $limit)");
        }

        return [];
    }

    public function exportReportOrderByBranch($data)
    {
        $dataStore = [
            "FromDate" => $data['FromDate'] ?? Carbon::parse('00:00:00')->subMonths(1)->startOfMonth()->toDateTimeString(),
            "ToDate" => $data['ToDate'] ?? Carbon::parse('23:59:59')->subMonths(1)->endOfMonth()->toDateTimeString(),
            "SourceType" => $data['SourceType'] ?? 'NULL',
            "BranchId" => $data['BranchId'] ?? 0,
            "CurrentBranchId" => $data['CurrentBranchId'] ?? 'NULL',
            "lmstart" => 0,
            "limit" => 'NULL',
        ];
        $cellNumber = 'B2:G';
        $lastRow = 1;
        $staffId = Auth::user()['StaffId'] ?? 0;

        if (!isset($dataStore['SourceType']) || empty($dataStore['SourceType'])) {
            $dataStore['SourceType'] = 'NULL';
        }

        if (!isset($dataStore['BranchId']) || empty($dataStore['BranchId']) || !is_numeric($dataStore['BranchId']) || $dataStore['BranchId'] < 1) {
            $dataStore['BranchId'] = $dataStore['CurrentBranchId'] ?? 'NULL';
        }

        $storeReport = [];
        $totalRow = [];
        try {
            $data = $this->getReportOrderByBranch($dataStore);
            $storeReport = $data['Data'];
            $totalRow = $data['TotalRow'];
        } catch (\Exception $e) {
            Log::error('Error at function exportReportOrderByBranch', [$e->getMessage()]);
            return "";
        }

        if (isset($storeReport) && !empty($storeReport)) {
            $exportValue = [];
            $fileExportName = 'Order_By_Branch_' . '_report_' . date('Y_m') . '_' . time() . '_' . $staffId . '.xlsx';
            $filePathExport = storage_path('app/excel') . '/' . $fileExportName;
            $headings = ['Chi Nhánh', 'SL đơn bán',	'Tổng tiền hàng (VNĐ)', 'Giảm giá (VNĐ)', 'Tổng SL sản phẩm', 'Doanh thu Công ty (VNĐ)', 'Doanh thu CTV (VNĐ)'];
            try {
                foreach ($storeReport as $value) {
                    $lastRow += 1;

                    $exportValue[] = [
                        'BranchCode' => $value->BranchCode ?? '',
                        'TotalOR' => $value->TotalOR ?? 0,
                        'TotalAmount' => $value->TotalAmount ?? 0,
                        'TotalDiscountAmount' => $value->TotalDiscountAmount ?? 0,
                        'TotalProduct' => $value->TotalProduct ?? 0,
                        'TotalRevenue' => $value->TotalRevenue ?? 0,
                        'TotalAffiliateAmount' => $value->TotalAffiliateAmount ?? 0
                    ];
                }

                $cellNumber = $cellNumber . ($lastRow + 1);

                array_unshift($exportValue, [
                    'Tổng',
                    $totalRow->TotalOR ?? 0,
                    $totalRow->TotalAmount ?? 0,
                    $totalRow->TotalDiscountAmount ?? 0,
                    $totalRow->TotalProduct ?? 0,
                    $totalRow->TotalRevenue ?? 0,
                    $totalRow->TotalAffiliateAmount ?? 0,
                ]);
            } catch (\Exception $e) {
                Log::error('Report Export fail: ', [$e->getMessage()]);
            }

            $exportFile = $this->storeReportExport($exportValue, $headings, $fileExportName, $filePathExport, true, $cellNumber, 'G');
            if ($exportFile) {
                return $exportFile;
            }
        }

        return "";
    }

    public function exportReportOrderByTime($data)
    {
        $dataStore = [
            "FromDate" => $data['FromDate'] ?? Carbon::parse('00:00:00')->subMonths(1)->startOfMonth()->toDateTimeString(),
            "ToDate" => $data['ToDate'] ?? Carbon::parse('23:59:59')->subMonths(1)->endOfMonth()->toDateTimeString(),
            "SourceType" => $data['SourceType'] ?? 'NULL',
            "lmstart" => 0,
            "limit" => 'NULL',
        ];
        $staffId = Auth::user()['StaffId'] ?? 0;
        $cellNumber = 'B2:G';
        $lastRow = 1;

        if (!isset($dataStore['SourceType']) || empty($dataStore['SourceType'])) {
            $dataStore['SourceType'] = 'NULL';
        }
        $storeReport = [];
        $totalRow = [];

        try {
            $data = $this->getOrderReportByTime($dataStore);
            $storeReport = $data['Data'];
            $totalRow = $data['TotalRow'];
        } catch (\Exception $e) {
            Log::error('Error at function exportReportOrderByTime', [$e->getMessage()]);
            return "";
        }

        if (isset($storeReport) && !empty($storeReport)) {
            $exportValue = [];
            $fileExportName = 'Order_By_Time_' . '_report_' . date('Y_m') . '_' . time() . '_' . $staffId . '.xlsx';
            $filePathExport = storage_path('app/excel') . '/' . $fileExportName;
            $headings = ['Thời gian', 'SL đơn bán', 'Tổng tiền hàng (VNĐ)', 'Giảm giá (VNĐ)', 'Tổng SL sản phẩm', 'Doanh thu Công ty (VNĐ)', 'Doanh thu CTV (VNĐ)'];
            try {
                foreach ($storeReport as $value) {
                    $lastRow += 1;

                    $exportValue[] = [
                        'Date' => $value->Date ?? '',
                        'TotalOR' => $value->TotalOR ?? 0, 
                        'TotalPaymentAmount' => $value->TotalPaymentAmount ?? 0,
                        'TotalDiscountAmount' => $value->TotalDiscountAmount ?? 0,
                        'TotalProduct' => $value->TotalProduct ?? 0, 
                        'TotalRevenue' => $value->TotalRevenue ?? 0,
                        'TotalAffiliateAmount' => $value->TotalAffiliateAmount ?? 0,
                    ];
                }

                $cellNumber = $cellNumber . ($lastRow + 1);

                array_unshift($exportValue, [
                    'Tổng',
                    $totalRow->TotalAllOR ?? 0,
                    $totalRow->TotalAllPaymentAmount ?? 0,
                    $totalRow->TotalAllDiscountAmount ?? 0,
                    $totalRow->TotalAllProduct ?? 0,
                    $totalRow->TotalAllRevenue ?? 0,
                    $totalRow->TotalAllAffiliateAmount ?? 0,
                ]);
            } catch (\Exception $e) {
                Log::error('Report Export fail: ', [$e->getMessage()]);
            }
    
            $exportFile = $this->storeReportExport($exportValue, $headings, $fileExportName, $filePathExport, true, $cellNumber, 'G');
            if ($exportFile) {
                return $exportFile;
            }
        }

        return "";
    }

    public function exportReportOrderByProduct($data)
    {
        $dataStore = [
            "FromDate" => $data['FromDate'] ?? Carbon::parse('00:00:00')->subMonths(1)->startOfMonth()->toDateTimeString(),
            "ToDate" => $data['ToDate'] ?? Carbon::parse('23:59:59')->subMonths(1)->endOfMonth()->toDateTimeString(),
            "SourceType" => $data['SourceType'] ?? 'NULL',
            "BranchId" => $data['BranchId'] ?? 0,
            "BrandId" => $data['BrandId'] ?? 'NULL',
            "CurrentBranchId" => $data['CurrentBranchId'] ?? 'NULL',
            "lmstart" => 0,
            "limit" => 'NULL',
            'CategoryId' => $data['CategoryId'] ?? 'NULL',
            'Keyword' => $data['Keyword'] ?? 'NULL',
        ];
        $staffId = Auth::user()['StaffId'] ?? 0;
        $cellNumber = 'F2:H';
        $lastRow = 1;

        if (!isset($dataStore['SourceType']) || empty($dataStore['SourceType'])) {
            $dataStore['SourceType'] = 'NULL';
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
            $data = $this->getOrderReportByProduct($dataStore);
            $storeReport = $data['Data'];
            $totalRow = $data['TotalRow'];
        } catch (\Exception $e) {
            Log::error('Error at function exportReportOrderByProduct', [$e->getMessage()]);
            return "";
        }

        if (isset($storeReport) && !empty($storeReport)) {
            $exportValue = [];
            $fileExportName = 'Order_By_Product_' . '_report_' . date('Y_m') . '_' . time() . '_' . $staffId . '.xlsx';
            $filePathExport = storage_path('app/excel') . '/' . $fileExportName;
            $headings = ['Mã sản phẩm', 'Tên sản phẩm', 'Đơn vị tính', 'Danh mục', 'Nhãn hàng', 'SL Bán', 'Tổng tiền hàng (VNĐ)', 'Doanh thu CTV (VNĐ)'];
            try {

                foreach ($storeReport as $value) {
                    $lastRow += 1;
                    $exportValue[] = [
                        'SKU' => $value->SKU ?? '',
                        'ProductName' => $value->ProductName ?? '',
                        'UnitName' => $value->UnitName ?? '',
                        'CategoryName' => $value->CategoryName ?? '',
                        'BrandName' => $value->BrandName ?? '',
                        'TotalProduct' => $value->TotalProduct ?? 0,
                        'TotalSale' => $value->TotalSale ?? 0,
                        'TotalAffiliateAmount' => $value->TotalAffiliateAmount ?? 0,
                    ];
                }
                
                $cellNumber = $cellNumber . ($lastRow + 1);

                array_unshift($exportValue, [
                    'Tổng',
                    '',
                    '',
                    '',
                    '',
                    $totalRow->TotalProduct ?? 0,
                    $totalRow->TotalSale ?? 0,
                    $totalRow->TotalAffiliateAmount ?? 0,
                ]);
            } catch (\Exception $e) {
                $cellNumber = 'F2:H0';
                Log::error('Report Export By Product fail: ', [$e->getMessage()]);
            }
    
            $exportFile = $this->storeReportExport($exportValue, $headings, $fileExportName, $filePathExport, true, $cellNumber, 'H');
            if ($exportFile) {
                return $exportFile;
            }
        }

        return "";
    }

    public function exportReportOrderByStaff($data)
    {
        $dataStore = [
            "FromDate" => $data['FromDate'] ?? Carbon::parse('00:00:00')->subMonths(1)->startOfMonth()->toDateTimeString(),
            "ToDate" => $data['ToDate'] ?? Carbon::parse('23:59:59')->subMonths(1)->endOfMonth()->toDateTimeString(),
            "SourceType" => $data['SourceType'] ?? 'NULL',
            "lmstart" => 0,
            "limit" => 'NULL',
        ];
        $staffId = Auth::user()['StaffId'] ?? 0;
        $cellNumber = 'B2:G';
        $lastRow = 1;

        if (!isset($dataStore['SourceType']) || empty($dataStore['SourceType'])) {
            $dataStore['SourceType'] = 'NULL';
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
            $data = $this->getOrderReportByStaff($dataStore);
            $storeReport = $data['Data'];
            $totalRow = $data['TotalRow'];
        } catch (\Exception $e) {
            Log::error('Error at function exportReportOrderByStaff', [$e->getMessage()]);
            return "";
        }
        if (isset($storeReport) && !empty($storeReport)) {
            $exportValue = [];
            $fileExportName = 'Order_By_EmployeeDoctor_' . '_report_' . date('Y_m') . '_' . time() . '_' . $staffId . '.xlsx';
            $filePathExport = storage_path('app/excel') . '/' . $fileExportName;
            $headings = ['Nhân viên/ Bác sĩ', 'SL đơn bán',	'Tổng tiền hàng (VNĐ)', 'Giảm giá (VNĐ)', 'Tổng SL sản phẩm', 'Doanh thu Công ty (VNĐ)', 'Doanh thu CTV (VNĐ)'];
            try {

                foreach ($storeReport as $value) {
                    $lastRow += 1;

                    $exportValue[] = [
                        'Employee' => $value->Employee ?? '',
                        'TotalOR' => $value->TotalOR ?? 0,
                        'TotalAmount' => $value->TotalAmount ?? 0,
                        'TotalDiscountAmount' => $value->TotalDiscountAmount ?? 0,
                        'TotalSKU' => $value->TotalSKU ?? 0,
                        'TotalRevenue' => $value->TotalRevenue ?? 0,
                        'TotalAffiliateAmount' => $value->TotalAffiliateAmount ?? 0,

                    ];
                }

                $cellNumber = $cellNumber . ($lastRow + 1);

                array_unshift($exportValue, [
                    'Tổng',
                    $totalRow->TotalOR ?? 0,
                    $totalRow->TotalAmount ?? 0,
                    $totalRow->TotalDiscountAmount ?? 0,
                    $totalRow->TotalSKU ?? 0,
                    $totalRow->TotalRevenue ?? 0,
                    $totalRow->TotalAffiliateAmount ?? 0
                ]);
            } catch (\Exception $e) {
                Log::error('Report Export By Product fail: ', [$e->getMessage()]);
            }
    
            $exportFile = $this->storeReportExport($exportValue, $headings, $fileExportName, $filePathExport, true, $cellNumber, 'G');
            if ($exportFile) {
                return $exportFile;
            }
        }

        return "";
    }

    public function storeReportExport($exportValue, $headings, $fileExportName, $filePathExport, $isHighColumnB1 = false, $cellNumber = '', $highestColumn = NULL, $exceptionColumnNotNumber = [])
    {
        $reportExport = new ReportExport($exportValue, $headings, $isHighColumnB1, $cellNumber);
        if ($highestColumn) {
            $reportExport->setHighestColumn($highestColumn);
        }
        if (!empty($exceptionColumnNotNumber)) {
            $reportExport->setExceptionColumnNotNumber($exceptionColumnNotNumber);
        }
        $reportExport = Excel::store($reportExport, 'excel/' . $fileExportName);
    
        if ($reportExport && file_exists($filePathExport)) {
            $filePathExport = new UploadedFile($filePathExport, $fileExportName, mime_content_type($filePathExport));
            $responeMediaExport = Helper::uploadFileToServer($filePathExport, 'Exports');
            if ($responeMediaExport && !empty($responeMediaExport)) {
                $exportFile = API_MEDIA . '/' . $responeMediaExport;
                return $exportFile;
            }
        }
        return '';
    }

    public function getAllOrderByCustomer($request)
    {
        $customerId = $request['CustomerId'] ?? 0;
        $lmstart = $request['lmstart'] ?? 0;
        $limit = $request['limit'] ?? 20;

        try {
            $query = ProductOR::select(['OR.ORId','OR.BranchId','OR.ORCode','OR.RefCode','OR.ORStatus','OR.CustomerId','OR.CustomerName','OR.CustomerPhoneNumber','OR.CustomerEmail','OR.ShippingFullAddress','OR.ShippingAddressNo','OR.TotalAmount','OR.DiscountAmount','OR.PaymentAmount', 'OR.PaymentStatus', 'OR.CODAmount','OR.TotalRevenue','OR.TotalItem','OR.TotalSKU','OR.CreatedBy','OR.CreatedDate','OR.UpdatedBy','OR.UpdatedDate','OR.AffiliateAccountId','OR.AffiliateAmount','OR.Note','OR.SourceType','OR.ShippingFeeAmount','OR.ShippingFeePaidSide','OR.PaidAmount','OR.ConsultingStaffId','b.BranchCode','b.Name as BranchName', 'b.Address','aa.FullName as AffiliateAccountName','aa.AffCode','OR.Recipient','OR.RecipientPhone'])
                ->join('in.Branch as b', 'b.BranchId', 'OR.BranchId')
                ->leftJoin('sale.AffiliateAccount as aa', 'aa.AffiliateAccountId', 'OR.AffiliateAccountId');
            $query->with(['createdByStaff' => function ($query) {
                $query->select('StaffId', 'FullName', 'StaffCode');
            }]);
            $query->with(['assignByDoctor' => function ($query) {
                $query->select('StaffId', 'FullName', 'StaffCode');
            }]);
            
            $query->where('OR.CustomerId', $customerId);
            $query->where('OR.ORStatus', 99);

            $query->orderByDesc('OR.ORId');

            $data = $query->paginate($limit, ['*'], 'page', round((int) $lmstart/ (int) $limit) + 1);
            
            if(count($data) > 0){
                foreach($data as $key => $value){
                    $orderDetail = self::getListOrderDetail($value->ORId, $value->BranchId);
                    $mergedOrders = [];
                    foreach ($orderDetail as $order) {
                        $key = $order['ProductId'] . '-' . $order['UnitId'];
                        if (!isset($mergedOrders[$key])) {
                            $mergedOrders[$key] = $order;
                        } else {
                            $mergedOrders[$key]['OrderQty'] += $order['OrderQty'];
                        }
                    }
                    $mergedOrders = array_values($mergedOrders);
                    $value->OrderDetail = $mergedOrders;
                    $value->LastPaymentAmount = $this->calLastPaymentAmount($value);
                }
            }
            return $data;

        } catch (\Exception $e) {
            Log::error("getAllListOrder error", [$e->getMessage()]);
            return [];
        }
    }
    
    public function getOrderDetail($orderDetailId = 0)
    {
        if (!$orderDetailId || empty($orderDetailId)) {
            return null;
        }
        $orderDetail = OrderDetail::where('OrderDetailId', $orderDetailId)->first();
        return $orderDetail;
    }

    public function createOrderFromWareHouse($data)
    {
        $staffId = Auth::user()['StaffId'] ?? 0;
        $branchId = $data['BranchId'] ?? 0;
        $refCode = $data['RefCode'] ?? '';
        $sourceType = 120; //Transfer Warehouse
        $customerId = 0; //Set customer empty
        $customerName = $recipient = $data['ContactPerson'] ?? '';
        $customerPhoneNumber = $recipientPhone = $data['ContactPhoneNumber'] ?? '';
        $customerEmail = $data['ContactEmail'] ?? '';
        $shippingFullAddress = $data['ShippingFullAddress'] ?? '';
        $shippingAddressNo = $data['ShippingAddressNo'] ?? '';
        $address = $data['Address'] ?? '';
        $provinceId = $data['ProvinceId'] ?? 0;
        $provinceName = $data['ProvinceName'] ?? '';
        $districtId = $data['DistrictId'] ?? 0;
        $districtName = $data['DistrictName'] ?? '';
        $wardId = $data['WardId'] ?? 0;
        $wardName = $data['WardName'] ?? '';
        $note = $data['Note'] ?? '';


        $warehouseTransferDetails = $data['WarehouseTransferDetails'] ?? [];
        // handle note
        if ($note && !empty($note)) {
            $note = base64_encode($note);
        }
        DB::beginTransaction();
        try {

            $jsonProduct = '';
            if (count($warehouseTransferDetails) > 0) {
                $dataInsertDetail = [];
                foreach ($warehouseTransferDetails as $value) {
                    $dataInsertDetail[] = [
                        'ProductId' => $value['ProductId'] ?? 0,
                        'ConditionTypeId' => 1,
                        'UnitId' => $value['UnitId'] ?? 0,
                        'SKU' => $value['SKU'] ?? '',
                        'OrderQty' => $value['Qty'] ?? 0,
                        'ManufactureDate' => '',
                        'ExpirationDate' => '',
                        'RefPrice' => "0",
                        'AffiliatePrice' => "0",
                        'SalePrice' => "0",
                        'PriceAfterDiscount' => "0",
                        'DiscountType' => 0,
                        'DiscountAmount' => 0,
                        'Note' => ''

                    ];
                }
                $jsonProduct = json_encode($dataInsertDetail);
            }
            $queryStore = "CALL sale.sp_ManageOutboundReceipt('CREATE', NULL, " . $branchId . ", '" . $refCode . "', 2, 1, " . $sourceType . ", " . $customerId . ", '" . $customerName . "', '" . $customerPhoneNumber . "', '" . $customerEmail . "', NULL,'" . $recipient . "','" . $recipientPhone . "', '" . $shippingFullAddress . "', '" . $shippingAddressNo . "', '$address', " . $provinceId . ", '" . $provinceName . "', " . $districtId . ", '" . $districtName . "', " . $wardId . ", '" . $wardName . "', 0, 0, 0, 0, NULL, '" . $note . "', " . $staffId . ", 0, 0, 0,  0, 1, 0, 0, 0, '" . $jsonProduct . "')";
            $result = DB::select(DB::raw($queryStore));
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::info("createOrderFromWareHouse error: " . $queryStore);
            Log::error("createOrderFromWareHouse error: " . $e->getMessage());
        }
        DB::rollBack();
        return false;
    }

    protected function calLastPaymentAmount($or)
    {
         /**
         * 1 Công ty
         * 2 Cộng tác viên
         * 3 Khách hàng
         * 
         * Nếu Công ty trả ship -> Cần thanh toán = Tổng cộng - giảm giá theo đơn - Đã thanh toán
         * Nếu CTV trả ship -> Cần thanh toán = Tổng cộng - giảm giá theo đơn - Đã thanh toán
         * Nếu KH trả ship -> Cần thanh toán = Tổng cộng - giảm giá theo đơn - Đã thanh toán + phí ship 
         * 
         * PaymentAmount = Tổng cộng - giảm giá theo đơn + Phí ship (nếu là KH chịu phí)
         */

        $lastPaymentAmount = 0;

        if (!$or || empty($or)) {
            return $lastPaymentAmount;
        }
        if (is_array($or)) {
            $or = (object) $or;
        }

        if (!isset($or->PaymentAmount)) {
            return $lastPaymentAmount;
        }
        $lastPaymentAmount = (int) ($or->PaymentAmount ?? 0);


        if (!isset($or->PaidAmount)) {
            return $lastPaymentAmount;
        }
        $lastPaymentAmount = (int) ($or->PaymentAmount ?? 0) - ($or->PaidAmount ?? 0);

        return $lastPaymentAmount;
    }

    public function getQuantityProductsSoldAndRevenue($condition)
    {
        $fromDate = $condition['FromDate'] ?? Carbon::now()->subDays(7)->toDateString();
        $toDate = $condition['ToDate'] ?? Carbon::now()->toDateString();
        $branchId = $condition['BranchId'] ?? 0;
        $configOtherBranch = config('constants.branch.otherBranch');

        $query = $this->_model->newQuery();

        $query->select(DB::raw('COUNT(1) AS TotalOR, SUM(PaymentAmount) AS AllRevenue'));

        if (isset($fromDate) && !empty($fromDate)) {
            $query->where('CreatedDate', '>=', $fromDate);
        }
        if (isset($toDate) && !empty($toDate)) {
            $query->where('CreatedDate', '<=', $toDate);
        }
        if (isset($branchId) && !empty($branchId) && $branchId > 0 && $branchId !== $configOtherBranch) {
            $query->where('BranchId', $branchId);
        }
        if (isset($branchId) && !empty($branchId) && $branchId == $configOtherBranch) {
            $query->whereNull('BranchId');
        }

        $query->whereNotIn('ORStatus', [35, 80, 85, 100]);

        return $query->first();
    }

    public function getQuantityReturnedOrdersAndDamage($condition)
    {
        $fromDate = $condition['FromDate'] ?? Carbon::now()->subDays(7)->toDateString();
        $toDate = $condition['ToDate'] ?? Carbon::now()->toDateString();
        $branchId = $condition['BranchId'] ?? 0;
        $configOtherBranch = config('constants.branch.otherBranch');

        // Get ORIdRef from IR
        $inboundRepo = new InBoundOrderRepository();

        $query = $this->_model->newQuery();

        $query->select('ORId', 'ORStatus', 'PaymentAmount', 'TotalRevenue');

        if (isset($fromDate) && !empty($fromDate)) {
            $query->where('CreatedDate', '>=', $fromDate);
        }
        if (isset($toDate) && !empty($toDate)) {
            $query->where('CreatedDate', '<=', $toDate);
        }
        if (isset($branchId) && !empty($branchId) && is_numeric($branchId) && $branchId > 0 && $branchId !== $configOtherBranch) {
            $query->where('BranchId', $branchId);
        }
        if (isset($branchId) && !empty($branchId) && $branchId == $configOtherBranch) {
            $query->whereNull('BranchId');
        }

        $query->whereNotIn('ORStatus', [35, 100]);

        $result = $query->get();
        $data = [
            'TotalReturned' => 0,
            'AllDamage' => 0,
            'PercentAmount' => 0
        ];
        if (count($result) > 0) {
            $totalReturned = 0;
            $allDamage = 0;
            $totalAmount = 0;
            foreach ($result as $item) {
                $totalAmount += (int) ($item->TotalRevenue ?? 0);
                if (isset($item->ORStatus) && ($item->ORStatus == 80 || $item->ORStatus == 85)) {
                    $totalReturned += 1;
                    $allDamage += (int) ($item->PaymentAmount ?? 0);
                }
            }
            $data['TotalReturned'] = $totalReturned;
            $data['AllDamage'] = $allDamage;
            $data['PercentAmount'] = round(($allDamage / $totalAmount) * 100, 1);
        }
        return $data;
    }

    public function getTopProductsByOR($condition)
    {
        $fromDate = $condition['FromDate'] ?? Carbon::now()->subDays(7)->toDateString();
        $toDate = $condition['ToDate'] ?? Carbon::now()->toDateString();
        $branchId = $condition['BranchId'] ?? 0;
        $configOtherBranch = config('constants.branch.otherBranch');
        $configTopProduct = config('constants.dashboard.topProductsLimit') ?? 10;

        $query = $this->_model->newQuery();
        $query->select(DB::raw('ord.ProductId, ord.UnitId, p.ProductName, u.Name AS UnitName, SUM(COALESCE(ord.OrderQty, 0)) AS TotalProduct'));
        $query->join('sale.ORDetail as ord', 'ord.ORId', 'OR.ORId')
              ->join('sale.Product as p', 'p.ProductId', 'ord.ProductId')
              ->join('sale.Unit as u', 'u.UnitId', 'ord.UnitId');

        if (isset($fromDate) && !empty($fromDate)) {
            $query->where('OR.CreatedDate', '>=', $fromDate);
        }
        if (isset($toDate) && !empty($toDate)) {
            $query->where('OR.CreatedDate', '<=', $toDate);
        }
        if (isset($branchId) && !empty($branchId) && is_numeric($branchId) && $branchId > 0 && $branchId !== $configOtherBranch) {
            $query->where('OR.BranchId', $branchId);
        }
        if (isset($branchId) && !empty($branchId) && $branchId == $configOtherBranch) {
            $query->whereNull('OR.BranchId');
        }

        $query->whereNotIn('ORStatus', [35, 80, 85, 100]);
        $query->where('OR.ORType', 1);
        $query->groupBy('ord.ProductId', 'ord.UnitId');
        $query->orderByDesc('TotalProduct');
        $query->limit($configTopProduct);

        return $query->get();
    }

    public function getEstimatedProfitByDays($condition)
    {
        $fromDate = $condition['FromDate'] ?? Carbon::now()->subDays(7)->toDateTimeString();
        $toDate = $condition['ToDate'] ?? Carbon::now()->toDateTimeString();
        $branchId = $condition['BranchId'] ?? 0;
        $configOtherBranch = config('constants.branch.otherBranch');

        $query = $this->_model->newQuery();
        $query->select('ORId', 'TotalRevenue', 'ProfitAvgCost', 'CreatedDate');
        if (isset($fromDate) && !empty($fromDate)) {
            $query->where('CreatedDate', '>=', $fromDate);
        }
        if (isset($toDate) && !empty($toDate)) {
            $query->where('CreatedDate', '<=', $toDate);
        }
        if (isset($branchId) && !empty($branchId) && is_numeric($branchId) && $branchId > 0 && $branchId !== $configOtherBranch) {
            $query->where('BranchId', $branchId);
        }
        if (isset($branchId) && !empty($branchId) && $branchId == $configOtherBranch) {
            $query->whereNull('BranchId');
        }
        $query->whereNotIn('ORStatus', [35, 80, 85, 100]);
        $query->where('ORType', 1);

        return $query->get();
    }

    public function getEstimatedProfit($condition)
    {
        $fromDate = $condition['FromDate'] ?? Carbon::now()->subDays(7)->toDateTimeString();
        $toDate = $condition['ToDate'] ?? Carbon::now()->toDateTimeString();

        $rows = $this->getEstimatedProfitByDays($condition);

        $byDay = [];
        $start = Carbon::parse($fromDate);
        $end = Carbon::parse($toDate);
        while ($start <= $end) {
            $key = $start->format('d/m/Y');
            $byDay[$key] = [
                'Day' => $key,
                'TotalRevenue' => 0,
                'TotalAmount' => 0,
            ];
            $start->addDay();
        }

        foreach ($rows as $r) {
            $key  = Carbon::parse($r->CreatedDate)->format('d/m/Y');
            $rev  = (float)$r->TotalRevenue;

            // Cộng doanh thu
            $byDay[$key]['TotalRevenue'] += $rev;
            // Cộng LỢI NHUẬN
            $byDay[$key]['TotalAmount']  += ($r->ProfitAvgCost ?? 0);
        }

        return array_values($byDay);
    }

    public function getProductPriceByOR($oRIds)
    {
        if (!is_array($oRIds) || empty($oRIds)) {
            $oRIds = [$oRIds];
        }
        $query = $this->_model->select(DB::raw('OR.ORId, SUM(ord.OrderQty * p.CostPrice) AS TotalCost'))
            ->join('sale.ORDetail as ord', 'ord.ORId', 'OR.ORId')
            ->join('sale.ProductUnit as p', function ($join) {
                $join->on('p.ProductId', '=', 'ord.ProductId')
                    ->on('p.UnitId', '=', 'ord.UnitId');
            })
            ->where('OR.ORType', 1)
            ->whereNotIn('OR.ORStatus', [35, 80, 85, 100]);
        if (!empty($oRIds)) {
            $query->whereIn('OR.ORId', $oRIds);
        }
        $query->groupBy('OR.ORId');
        $result = $query->get();
        $data = [];
        foreach ($result as $value) {
            $data[$value->ORId] = $value->TotalCost;
        }
        return $data;
    }
}