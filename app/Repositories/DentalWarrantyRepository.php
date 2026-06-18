<?php

namespace App\Repositories;

use App\DentalWarrantyRecord;
use App\LaboOrder;
use App\LaboOrderDetail;
use App\LaboOrderPhoto;
use Illuminate\Support\Facades\DB;
use App\Repositories\Abstracts\EloquentRepository;
use Illuminate\Support\Carbon;

class DentalWarrantyRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return DentalWarrantyRecord::class;
   }

   public function getWarrantyRecords($customerId, $condition = [])
   {
      if (!$customerId || empty($customerId)) {
         return [];
      }
      
      // Get warranty records by customer id với eager loading tối ưu
      $query = $this->_model->newQuery();
      $query->where('CustomerId', $customerId);
      $query->where(function($subQuery) {
         return $subQuery->whereNull('IsDeleted')
            ->orWhere('IsDeleted', 0);
      });

      if (isset($condition['SpecializationCode']) && !empty($condition['SpecializationCode'])) {
         $query->where('SpecializationCode', $condition['SpecializationCode']);
      }

      // Filter records có StartDate và EndDate
      $query->whereNotNull('StartDate');
      $query->whereNotNull('EndDate');

      // Order by start date and created date
      $query->orderByDesc('StartDate');
      $query->orderByDesc('CreatedDate');

      // Relationships - chỉ select các field cần thiết
      $query->with(['orderDetail:OrderDetailId,ServiceId,ServiceName,Quantity,Amount,TaxAmount,TaxPercent,AnatomyBodyPartItemName']);
      $query->with(['createdByStaff:StaffId,FullName,StaffCode']);

      // Chỉ select các field cần thiết từ DentalWarrantyRecords
      $query->select('DentalWarrantyRecordsId', 'CustomerId', 'OrderDetailId', 'SpecializationCode', 'WarrantyInfo', 'StartDate', 'EndDate', 'CreatedBy', 'CreatedDate');

      $results = $query->get();

      if ($results->isEmpty()) {
         return [];
      }

      // Lấy tất cả OrderDetailId
      $orderDetailIds = $results->pluck('OrderDetailId')->unique()->filter()->toArray();
      
      if (empty($orderDetailIds)) {
         return [];
      }

      // Query tất cả data cần thiết trong 1 lần với JOIN
      // Lấy LaboOrderDetail + LaboOrder + LaboOrderPhoto trong 1 query
      $laboData = DB::table('LaboOrderDetail as lod')
         ->join('LaboOrder as lo', 'lo.Id', '=', 'lod.LaboOrderId')
         ->join('Customer as c', 'c.CustomerId', '=', 'lo.CustomerId')
         ->leftJoin('LaboOrderPhoto as lop', 'lop.LaboOrderId', '=', 'lo.Id')
         ->leftJoin('ImagingAndXray as iax', 'iax.ImagingAndXrayId', '=', 'lop.ImagingAndXrayId')
         ->whereIn('lod.OrderDetailId', $orderDetailIds)
         ->select(
            'lod.OrderDetailId',
            'lod.LaboOrderId',
            'lod.LaboOrderDetailId',
            'lod.AnatomyBodyPartItemId',
            'lod.Qty',
            'lo.OrderCode',
            'lo.SendDate',
            'lo.ExpectReceiveDate',
            'lo.BranchId',
            'lo.DoctorId',
            'lo.CustomerId',
            'lo.LaboOrderStatusFinal',
            'lo.OrderStatusCode',
            'lo.Note',
            'lo.ProductCode',
            'lo.CreatedAt as LaboOrderCreatedAt',
            'lo.CreatedBy as LaboOrderCreatedBy',
            'lop.LaboOrderPhotoId',
            'lop.ImagingAndXrayId',
            'lop.WarrantyType',
            'lop.Url as PhotoUrl',
            'lop.CreatedAt as PhotoCreatedAt',
            'lop.CreatedBy as PhotoCreatedBy',
            'iax.Name',
            'iax.Ordering',
            'c.Photo',
            'c.PhotoResize',
            'c.Gender'
         )
         ->get();

      // Organize data by OrderDetailId
      $laboOrdersByOrderDetailId = [];
      $processedLaboOrders = []; // Track đã xử lý LaboOrder nào rồi
      
      // Define ImagingAndXray structure based on WarrantyType
      $imagingStructure = [
         'P' => [
            ['ImagingAndXrayId' => 2, 'Name' => 'Scan trong miệng', 'Ordering' => 10],
            ['ImagingAndXrayId' => 12, 'Name' => 'Thiết kế Labo', 'Ordering' => 75],
            ['ImagingAndXrayId' => 17, 'Name' => 'Hình ảnh sau điều trị', 'Ordering' => 100],
            ['ImagingAndXrayId' => 18, 'Name' => 'Hình ảnh trước điều trị', 'Ordering' => 105],
            ['ImagingAndXrayId' => 21, 'Name' => 'Hình ảnh khác', 'Ordering' => 110],
         ],
         'I' => [
            ['ImagingAndXrayId' => 2, 'Name' => 'Scan trong miệng', 'Ordering' => 10],
            ['ImagingAndXrayId' => 20, 'Name' => 'Setup Invisalign', 'Ordering' => 20],
            ['ImagingAndXrayId' => 12, 'Name' => 'Thiết kế Labo', 'Ordering' => 75],
            ['ImagingAndXrayId' => 13, 'Name' => 'Hình ảnh trong điều trị', 'Ordering' => 80],
            ['ImagingAndXrayId' => 15, 'Name' => 'Hình ảnh IPR', 'Ordering' => 90],
            ['ImagingAndXrayId' => 14, 'Name' => 'Hình ảnh đeo khay', 'Ordering' => 95],
            ['ImagingAndXrayId' => 21, 'Name' => 'Hình ảnh khác', 'Ordering' => 110],
         ]
      ];
      
      foreach ($laboData as $row) {
         $orderDetailId = $row->OrderDetailId;
         $laboOrderId = $row->LaboOrderId;
         $warrantyType = $row->WarrantyType;
         
         // Nếu chưa có LaboOrder này cho OrderDetailId
         if (!isset($processedLaboOrders[$orderDetailId][$laboOrderId])) {
            // Initialize Photos structure based on WarrantyType
            $photosStructure = [];
            if (isset($imagingStructure[$warrantyType])) {
               foreach ($imagingStructure[$warrantyType] as $imaging) {
                  $photosStructure[$imaging['ImagingAndXrayId']] = [
                     'ImagingAndXrayId' => $imaging['ImagingAndXrayId'],
                     'Name' => $imaging['Name'],
                     'Ordering' => $imaging['Ordering'],
                     'Items' => []
                  ];
               }
            }
            $avatar = '';
            if(!$row->Photo){
               if($row->Gender == 1){
                  $avatar = 'https://home.kimdental.vn/pos/fonts/image-boy.83c1e7878f46d0f48f3305f9e52ff942.svg';
               } else {
                  $avatar = 'https://home.kimdental.vn/pos/fonts/image-girl.eccd979da660f47caf67311caa3b0975.svg';
               }
            }
            $laboOrdersByOrderDetailId[$orderDetailId][$laboOrderId] = [
               'Id' => $laboOrderId,
               'OrderCode' => $row->OrderCode,
               'SendDate' => $row->SendDate,
               'ExpectReceiveDate' => $row->ExpectReceiveDate,
               'BranchId' => $row->BranchId,
               'DoctorId' => $row->DoctorId,
               'CustomerId' => $row->CustomerId,
               'LaboOrderStatusFinal' => $row->LaboOrderStatusFinal,
               'OrderStatusCode' => $row->OrderStatusCode,
               'Note' => $row->Note,
               'ProductCode' => $row->ProductCode,
               'CreatedAt' => $row->LaboOrderCreatedAt,
               'CreatedBy' => $row->LaboOrderCreatedBy,
               'WarrantyType' => $warrantyType,
               'Photos' => $photosStructure,
               'Avatar' => $row->Photo ? API_MEDIA .'/'. $row->Photo : $avatar,
               'AvatarResize' => $row->PhotoResize ? API_MEDIA .'/'. $row->PhotoResize : $avatar
            ];
            $processedLaboOrders[$orderDetailId][$laboOrderId] = true;
         }
         
         // Thêm photo vào group nếu có và thuộc allowed list
         if ($row->LaboOrderPhotoId && $row->ImagingAndXrayId) {
            $imagingId = $row->ImagingAndXrayId;
            
            // Check if this ImagingAndXrayId exists in the Photos structure
            if (isset($laboOrdersByOrderDetailId[$orderDetailId][$laboOrderId]['Photos'][$imagingId])) {
               // Thêm photo vào group
               $laboOrdersByOrderDetailId[$orderDetailId][$laboOrderId]['Photos'][$imagingId]['Items'][] = [
                  'LaboOrderPhotoId' => $row->LaboOrderPhotoId,
                  'Url' => $row->PhotoUrl,
                  'CreatedAt' => $row->PhotoCreatedAt,
                  'CreatedBy' => $row->PhotoCreatedBy,
               ];
            }
         }
      }

      // Convert nested arrays to simple arrays and sort Photos by Ordering
      foreach ($laboOrdersByOrderDetailId as $orderDetailId => &$laboOrders) {
         foreach ($laboOrders as $laboOrderId => &$laboOrder) {
            // Sort Photos by Ordering field in ascending order
            if (!empty($laboOrder['Photos'])) {
               $photos = array_values($laboOrder['Photos']);
               // Sort by Ordering ascending
               usort($photos, function($a, $b) {
                  // Handle null values - treat null as highest value
                  if ($a['Ordering'] === null && $b['Ordering'] === null) return 0;
                  if ($a['Ordering'] === null) return 1;
                  if ($b['Ordering'] === null) return -1;
                  return $a['Ordering'] <=> $b['Ordering'];
               });
               $laboOrder['Photos'] = $photos;
            }
         }
         unset($laboOrder); // Break reference
         $laboOrdersByOrderDetailId[$orderDetailId] = array_values($laboOrders);
      }
      unset($laboOrders); // Break reference

      // Group results by OrderDetailId
      $groupedResults = [];
      foreach ($results as $res) {
         $orderDetail = $res->orderDetail;
         
         // Skip nếu không có orderDetail
         if (!$orderDetail) {
            continue;
         }
         
         // Tính WarrantyDuration
         $warrantyDuration = 0;
         if ($res->StartDate && $res->EndDate) {
            $warrantyDuration = Carbon::parse($res->StartDate)->diffInYears(Carbon::parse($res->EndDate));
         }
         
         // OrderDetailId là duy nhất, dùng làm key
         $keyUnique = $res->OrderDetailId;
         
         if (!isset($groupedResults[$keyUnique])) {
            $groupedResults[$keyUnique] = [
               'OrderDetailId' => $res->OrderDetailId,
               'ServiceId' => $orderDetail->ServiceId ?? 0,
               'ServiceName' => $orderDetail->ServiceName ?? '',
               'Amount' => $orderDetail->Amount ?? 0,
               'SpecializationCode' => $res->SpecializationCode ?? '',
               'StartDate' => $res->StartDate,
               'EndDate' => $res->EndDate,
               'WarrantyDuration' => $warrantyDuration,
               'Quantity' => $orderDetail->Quantity ?? 0,
               'Warranties' => [],
               'LaboOrders' => $laboOrdersByOrderDetailId[$res->OrderDetailId] ?? [],
            ];
         }
         
         // Set WarrantyDuration cho từng warranty record
         $res->WarrantyDuration = $warrantyDuration;
         $groupedResults[$keyUnique]['Warranties'][] = $res;
      }

      return array_values($groupedResults);
   }
}
