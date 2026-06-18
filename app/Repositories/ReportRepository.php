<?php

namespace App\Repositories;

use App\Customer;
use App\Report;
use App\Treatment;
use App\Branch;
use App\OrderChanging;
use App\OrderMeasuringConsulting;
use App\OrderMeasuringConsultingDetail;
use App\OrderMeasuringConsultingTracking;
use App\OrderDetail;
use App\OrderDetailFinancialTrans;
use App\Repositories\Abstracts\EloquentRepository;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ReportRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return OrderMeasuringConsulting::class;
   }

   public function reportMKTCashCheckin()
   {
      $fromDate = date('Y-m-d 00:00:00');
      $endDate = date('Y-m-d 23:59:59');
      $result = [
         'TotalAmount' => 0,
         'TotalApp' => 0,
         'TotalNewVisitor' => 0,
         'TotalExpenditure' => 0,
         'TotalReceipt' => 0,
      ];
      try {
         $branches = DB::select(DB::raw("CALL pos.usp_Report_MKT_CashCheckIn('$fromDate','$endDate')"));
         if (!$branches || empty($branches)) {
            return $result;
         }
         foreach ($branches as $branch) {
            $result['TotalReceipt'] += $branch->TotalAmount ?? 0;
            $result['TotalExpenditure'] += $branch->TotalExpenditure ?? 0;
            $result['TotalAmount'] += $branch->TotalAmount ?? 0;
            $result['TotalApp'] += $branch->TotalApp ?? 0;
            $result['TotalNewVisitor'] += $branch->TotalNewVisitor ?? 0;
         }
         return $result;
      } catch (\Exception $e) {
         Log::error("reportMKTCashCheckin: ", [$e->getMessage()]);
         return $result;
      }
      return $result;
   }

   public function doctorTreatmentReport($request)
   {
      try {
         $staffId = $request['StaffId'] ?? 0;
         $category = $request['Category'] ?? '';
         $fromDate = $request['FromDate'] ?? '';
         $toDate = $request['ToDate'] ?? '';
         $lmstart = $request['lmstart'] ?? 0;
         $limit = $request['limit'] ?? 20;
         $fromDate = $fromDate . ' 00:00:01';
         $toDate = $toDate . ' 23:59:59';

         if ($staffId > 0) {
            $data = DB::select(DB::raw("CALL pos.usp_DoctorLevelCriteriaSummary('$fromDate','$toDate'," . $staffId . ",'$category'," . $lmstart . "," . $limit . ")"));
            return $data;
         } else {
            return [];
         }
      } catch (\Exception $e) {
         Log::error("doctorTreatmentReport: ", [$e->getMessage()]);
         return [];
      }
   }

   public function getDoctorLevelCriteria()
   {
      $query = DB::table('pos.ParamConfig');
      $query->select('ObjectValue', 'Priority');
      $query->where('ObjectCode', 'DoctorLevelCriteria');
      $query->orderBy('Priority');

      return $query->get();
   }

   public function getAdvisoryReport($request)
   {
      try {
         
         $fromDate = $request['FromDate'] ?? '';
         $toDate = $request['ToDate'] ?? '';
         $serviceId = $request['ServiceId'] ?? 0;
         $branchId = $request['BranchId'] ?? 0;
         $keyword = $request['Keyword'] ?? '';
         $lmstart = $request['lmstart'] ?? 0;
         $limit = $request['limit'] ?? 20;
         $fromDate = $fromDate.' 00:00:01';
         $toDate = $toDate.' 23:59:59';

         $query = DB::table('pos.OrderDetail as od')
            ->select('c.CustomerId','c.FullName','c.CustomerCode','b.BranchCode','b.BranchId','od.ServiceName','od.AnatomyBodyPartItemName','od.Amount','s.ServiceDomainType','oc.LatestUpdated')
            ->join('pos.OrderChanging as oc','oc.OrderChangingId','=','od.OrderChangingId')
            ->join('pos.Treatment as t','t.TreatmentId','=','od.TreatmentId')
            ->join('pos.Customer as c','c.CustomerId','=','t.PersonId')
            ->join('pos.Service as s','s.ServiceId','=','od.ServiceId')
            ->join('in.Branch as b','b.BranchId','=','oc.BranchId')
            ->where('oc.LatestUpdated', '>=', $fromDate)
            ->where('oc.LatestUpdated', '<=', $toDate);
         
         // Theo chi nhánh
         if((int)$branchId > 0){
            $query->where('oc.BranchId', $branchId);
         }
         // Theo dịch vụ
         if((int)$serviceId > 0){
            $query->where('od.ServiceId', $serviceId);
         }
         // Theo tên khách hàng, mã khách hàng
         if($keyword){
            $query->where('c.FullName', 'LIKE', '%' . $keyword . '%')->orWhere('c.CustomerCode', 'LIKE', '%' . $keyword . '%');
         }

         $query->orderByDesc('c.CustomerId', 'od.OrderDetailId');

         $results = $query->paginate($limit,['*'],'page', round((int) $lmstart/ (int) $limit) + 1);

         if (!$results || empty($results)) {
            return [];
         }
        
         foreach ($results as $result) {
            $result->TotalReceipt = self::getReceiptByCustomer($result->CustomerId, $result->BranchId, $result->LatestUpdated);
         }

        return $results;
        
      } catch (\Exception $e) {
         Log::error("getAdvisoryReport: ", [$e->getMessage()]);
         return [];
      }
      return [];
   }

   public function getReceiptByCustomer($customerId, $branchId, $latestUpdated)
   {
      if(isset($customerId) && !empty($customerId) && is_numeric($customerId) && strlen($customerId) > 0){

         $date = date('Y-m-d', strtotime($latestUpdated));
         $query = DB::table('Customer as ct');
         $query->select(DB::raw('SUM(rc.TotalAmount) as TotalReceipt'));
         $query->join('Deposit as dp','ct.CustomerId','=','dp.CustomerId');
         $query->join('Receipt as rc','dp.DepositId','=','rc.DepositId');
         $query->where('dp.State','=',1);
         $query->where('rc.State','=',1);
         $query->where('ct.CustomerId','=',$customerId);
         $query->where('rc.BranchId','=',$branchId);
         $query->where('rc.ReceipStatusDate','>=', $date." 00:00:01");
         $query->where('rc.ReceipStatusDate','<=', $date." 23:59:59");

         $value = $query->first();

         if($value){
            return $value->TotalReceipt > 0 ? $value->TotalReceipt : 0;
         }
         return 0;
      }
      return 0;
   }

   public function getAdvisoryReportCount($request)
   {
      try {
         
         $fromDate = $request['FromDate'] ?? '';
         $toDate = $request['ToDate'] ?? '';
         $serviceId = $request['ServiceId'] ?? 0;
         $branchId = $request['BranchId'] ?? 0;
         $keyword = $request['Keyword'] ?? '';
         $fromDate = $fromDate.' 00:00:01';
         $toDate = $toDate.' 23:59:59';

         $query = DB::table('pos.OrderDetail as od')
            ->select('c.CustomerId','c.FullName','c.CustomerCode','b.BranchCode','b.BranchId','od.ServiceName','od.AnatomyBodyPartItemName','od.Amount','s.ServiceDomainType','oc.LatestUpdated')
            ->join('pos.OrderChanging as oc','oc.OrderChangingId','=','od.OrderChangingId')
            ->join('pos.Treatment as t','t.TreatmentId','=','od.TreatmentId')
            ->join('pos.Customer as c','c.CustomerId','=','t.PersonId')
            ->join('pos.Service as s','s.ServiceId','=','od.ServiceId')
            ->join('in.Branch as b','b.BranchId','=','oc.BranchId')
            ->where('oc.LatestUpdated', '>=', $fromDate)
            ->where('oc.LatestUpdated', '<=', $toDate);
         
         // Theo chi nhánh
         if((int)$branchId > 0){
            $query->where('oc.BranchId', $branchId);
         }
         // Theo dịch vụ
         if((int)$serviceId > 0){
            $query->where('od.ServiceId', $serviceId);
         }
         // Theo tên khách hàng, mã khách hàng
         if($keyword){
            $query->where('c.FullName', 'LIKE', '%' . $keyword . '%')->orWhere('c.CustomerCode', 'LIKE', '%' . $keyword . '%');
         }

         $results = $query->get()->toArray();

         if (!$results || empty($results)) {
            return [];
         }
         $totalT = 0;
         $totalP = 0;
         $totalI = 0;
         $totalC = 0;
         
         foreach ($results as $result) {
            if($result->ServiceDomainType == "T"){
               $totalT++;
            }
            if($result->ServiceDomainType == "P"){
               $totalP++;
            }
            if($result->ServiceDomainType == "I"){
               $totalI++;
            }
            if($result->ServiceDomainType == "C"){
               $totalC++;
            }
         }
         $data = (object)[];
         $data->TotalT = $totalT;
         $data->TotalP = $totalP;
         $data->TotalI = $totalI;
         $data->TotalC = $totalC;

         return $data;
        
      } catch (\Exception $e) {
         Log::error("getAdvisoryReportCount: ", [$e->getMessage()]);
         return [];
      }
      return [];

   }
   public function getNetCashCollection($request)
   {
      try {
         $branchId = $request['BranchId'] ?? '';
         $fromDate = $request['FromDate'] ?? '';
         $toDate = $request['ToDate'] ?? '';
         $fromDate = strtotime($fromDate . ' 00:00:01');
         $toDate = strtotime($toDate . ' 23:59:59');
         if (count($branchId) > 0) {
            $branchId = implode(',', $branchId);
         }

         $data = DB::select(DB::raw("CALL pos.sp_Report_NetCashCollection_Branch(" . $fromDate . "," . $toDate . ",'$branchId')"));

         return $data;
      } catch (\Exception $e) {
         Log::error("getNetCashCollection: ", [$e->getMessage()]);
         return [];
      }
   }

   public function getOrderMeasuringConsulting($request)
   {
      try {
         $branchId = $request['BranchId'] ?? '';
         $fromDate = $request['FromDate'] ?? '';
         $toDate = $request['ToDate'] ?? '';
         $keyword = $request['Keyword'] ?? '';
         $serviceType = $request['ServiceType'] ?? '';
         $doctorStaffId = $request['DoctorStaffId'] ?? 0;
         $statusId = $request['StatusId'] ?? 0;
         $lmstart = $request['lmstart'] ?? 0;
         $limit = $request['limit'] ?? 20;

         $query = $this->_model->select(('*'));

         // Tìm kiếm theo ngày tư vấn
         if ($fromDate) {
            $query->where('ConsultingDate', '>=', $fromDate);
         }

         // Tìm kiếm theo ngày tư vấn
         if ($fromDate) {
            $query->where('ConsultingDate', '<=', $toDate);
         }

         // Tìm kiếm theo chi nhánh
         if ($branchId > 0) {
            $query->where('BranchId', $branchId);
         }

         // Tìm kiếm theo bác sĩ
         if ($doctorStaffId > 0) {
            $query->where(function ($query) use ($doctorStaffId) {
               $query->where('DoctorStaffId', $doctorStaffId);
               $query->orWhereHas('doctors', function ($subQuery) use ($doctorStaffId) {
                  return $subQuery->where('Doctor.StaffId', $doctorStaffId);
               });
            });
         }

         // Tìm kiếm theo nhóm dịch vụ
         if ($serviceType) {
            if ($serviceType == 'T') {
               $query->whereNotNull('GeneralityLevel');
            }
            if ($serviceType == 'P') {
               $query->whereNotNull('ProstheticLevel');
            }
            if ($serviceType == 'I') {
               $query->whereNotNull('ImplantLevel');
            }
            if ($serviceType == 'C') {
               $query->whereNotNull('OrthodonticLevel');
            }
         }

         // Tìm kiếm theo mã khách hàng hoặc tên khách hàng
         if ($keyword) {
            $query->whereHas('customer', function ($subQuery) use ($keyword) {
               return $subQuery->where('FullName', 'like', '%' . $keyword . '%')
                  ->orWhere('CustomerCode', 'like', '%' . $keyword . '%');
            });
         }

         // Tìm kiếm theo trạng thái tư vấn
         //StatusId = 1 : chưa chốt
         //StatusId = 10 : đã chốt
         if ($statusId && $statusId == 1) {
            // Chưa chốt
            $query->whereDoesntHave('offersByReport', function ($query) {
               $query->where('OrderMeasuringConsultingId', '>', 0);
            });
         } else if ($statusId && $statusId == 10) {
            // Đã chốt
            $query->whereHas('offersByReport', function ($query) {
               $query->where('OrderMeasuringConsultingId', '>', 0);
            });
         }

         $query->with(['branch' => function ($query) {
            $query->select('BranchId', 'BranchCode', 'Name');
         }]);

         $query->with(['customer' => function ($query) {
            $query->select('CustomerId', 'CustomerCode', 'FullName');
         }]);

         $query->with(['createdByStaff' => function ($query) {
            $query->select('StaffId', 'FullName', 'StaffCode');
         }]);

         $query->with(['updatedByStaff' => function ($query) {
            $query->select('StaffId', 'FullName', 'StaffCode');
         }]);

         $query->with(['confirmedByStaff' => function ($query) {
            $query->select('StaffId', 'FullName', 'StaffCode');
         }]);

         $query->with(['doctor' => function ($query) {
            $query->select('StaffId', 'FullName', 'StaffCode');
         }]);

         $query->with(['doctorSpecializationCode' => function ($query) {
            $query->select('DoctorId', 'StaffId', 'SpecializationCode');
         }]);
         $query->with(['doctors' => function ($query) {
            $query->select([
               'Doctor.DoctorId',
               'Doctor.StaffId',
               'Doctor.DoctorLevelId',
               'Doctor.DoctorLevelReachedDate',
               'Doctor.State',
               'Doctor.SpecializationCode',
               'Doctor.OrthodonticLevel',
               'Doctor.ImplantLevel',
               'Doctor.ProstheticLevel',
               'Doctor.GeneralityLevel',
               'Staff.FullName',
               'Staff.StaffCode'
            ]);
            $query->join('in.Staff', 'Staff.StaffId', '=', 'Doctor.StaffId');
         }]);

         $query->with(['offersByTreatment' => function ($query) {
            $query->with(['service' => function ($query) {
               $query->select([
                  'ServiceId',
                  'ServiceCode',
                  'Name',
                  'Description',
                  'GeneralityLevel',
                  'ProstheticLevel',
                  'ImplantLevel',
                  'OrthodonticLevel',
                  'BasePrice',
                  'SalePrice',
                  'ServiceDomainType',
                  'ServiceDomainLevel',
               ]);
            }]);
            $query->with(['anatomyBodyPartItem']);
            $query->with(['branch' => function ($query) {
               $query->select('BranchId', 'BranchCode', 'Name');
            }]);
         }]);

         $query->with(['offersByReport' => function ($query) {
            $query->with(['service' => function ($query) {
               $query->select([
                  'ServiceId',
                  'ServiceCode',
                  'Name',
                  'Description',
                  'GeneralityLevel',
                  'ProstheticLevel',
                  'ImplantLevel',
                  'OrthodonticLevel',
                  'BasePrice',
                  'SalePrice',
                  'ServiceDomainType',
                  'ServiceDomainLevel',
               ]);
            }]);
            $query->with(['anatomyBodyPartItem']);
            $query->with(['branch' => function ($query) {
               $query->select('BranchId', 'BranchCode', 'Name');
            }]);
         }]);
         $query->orderByDesc('OrderMeasuringConsulting.ConsultingDate');
         $result = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);
         return $result;
      } catch (\Exception $e) {
         Log::error("getOrderMeasuringConsulting: ", [$e->getMessage()]);
         return [];
      }
      return [];
   }

   public function countOrderMeasuringConsulting($request, $status)
   {
      try {
         $branchId = $request['BranchId'] ?? '';
         $fromDate = $request['FromDate'] ?? '';
         $toDate = $request['ToDate'] ?? '';

         $query = $this->_model->select(('*'));

         // Điếm theo ngày tư vấn
         if ($fromDate) {
            $query->where('ConsultingDate', '>=', $fromDate);
         }

         // Điếm theo ngày tư vấn
         if ($fromDate) {
            $query->where('ConsultingDate', '<=', $toDate);
         }

         // Điếm theo chi nhánh
         if ($branchId > 0) {
            $query->where('BranchId', $branchId);
         }

         // Điếm theo trạng thái
         if ($status == 10) {
            $query->where('Status', $status);
         }

         return count($query->get()) ?? 0;
      } catch (\Exception $e) {
         Log::error("countOrderMeasuringConsulting: ", [$e->getMessage()]);
         return [];
      }
   }

   public function countOIP($request)
   {
      $branchId = $request['BranchId'] ?? '';
      $fromDate = $request['FromDate'] ?? '';
      $toDate = $request['ToDate'] ?? '';

      $result = [
         'TotalOrthodonticByTreatment' => 0,
         'TotalOrthodonticByOffer' => 0,
         'TotalImplantByTreatment' => 0,
         'TotalImplantByOffer' => 0,
         'TotalProstheticByTreatment' => 0,
         'TotalProstheticByOffer' => 0,
      ];

      try {
         $query = $this->_model->select([
            'Id','CustomerId'
         ]);

         // Điếm theo ngày tư vấn
         if ($fromDate) {
            $query->where('ConsultingDate', '>=', $fromDate);
         }

         // Điếm theo ngày tư vấn
         if ($fromDate) {
            $query->where('ConsultingDate', '<=', $toDate);
         }

         // Điếm theo chi nhánh
         if ($branchId > 0) {
            $query->where('BranchId', $branchId);
         }

         $orders = $query->get();
         if (!$orders || empty($orders)) {
            return $result;
         }
         
         //Set customerIds and orderMeasuringConsultingIds
         $customerIds = $orders->pluck('CustomerId')->unique();
         if (!$customerIds || empty($customerIds)) {
            return $result;
         }

         $orderMeasuringConsultingIds = $orders->pluck('Id')->unique();
         if (!$orderMeasuringConsultingIds || empty($orderMeasuringConsultingIds)) {
            return $result;
         }
         
         //Create tmp table
         DB::statement("DROP TEMPORARY TABLE IF EXISTS tmp_customer_ids");
         DB::statement("CREATE TEMPORARY TABLE IF NOT EXISTS tmp_customer_ids (CustomerId INT(11) NOT NULL)");

         DB::statement("DROP TEMPORARY TABLE IF EXISTS tmp_order_measuring_ids");
         DB::statement("CREATE TEMPORARY TABLE IF NOT EXISTS tmp_order_measuring_ids (Id INT(11) NOT NULL)");


         $dataInsertCustomerIds = [];
         foreach ($customerIds as $customerId) {
               $dataInsertCustomerIds[] = ['CustomerId' => $customerId];
         }
         DB::table('tmp_customer_ids')->insert($dataInsertCustomerIds);

         $dataInsertOrderMeasuringIds = [];
         foreach ($orderMeasuringConsultingIds as $orderMeasuringConsultingId) {
               $dataInsertOrderMeasuringIds[] = ['Id' => $orderMeasuringConsultingId];
         }
         DB::table('tmp_order_measuring_ids')->insert($dataInsertOrderMeasuringIds);
         
         //Count Prosthetic, Implant, Orthodontic by Treatment
         $queryTreatment = DB::table('tmp_customer_ids')->join('Treatment', 'Treatment.PersonId', '=', 'tmp_customer_ids.CustomerId')
         ->join('TreatmentMedicalProcedureOffer', 'TreatmentMedicalProcedureOffer.TreatmentId', '=', 'Treatment.TreatmentId')
         ->join('Service', 'Service.ServiceId', '=', 'TreatmentMedicalProcedureOffer.ServiceId')
         ->selectRaw("SUM(CASE WHEN Service.WarrantyType = 'P' THEN 1 ELSE 0 END) as TotalProstheticByTreatment, SUM(CASE WHEN Service.WarrantyType = 'I' THEN 1 ELSE 0 END) as TotalImplantByTreatment, SUM(CASE WHEN Service.WarrantyType = 'O' THEN 1 ELSE 0 END) as TotalOrthodonticByTreatment")
         ->where('TreatmentMedicalProcedureOffer.AnatomyBodyPartItemId', '>', 0);
         
         
         $resultTreatment = $queryTreatment->first();

         if (!$resultTreatment || empty($resultTreatment)) {
             //Drop tmp table
            DB::statement("DROP TEMPORARY TABLE IF EXISTS tmp_customer_ids");
            DB::statement("DROP TEMPORARY TABLE IF EXISTS tmp_order_measuring_ids");
            return $result;
         }
         $result['TotalProstheticByTreatment'] = $resultTreatment->TotalProstheticByTreatment ?? 0;
         $result['TotalImplantByTreatment'] = $resultTreatment->TotalImplantByTreatment ?? 0;
         $result['TotalOrthodonticByTreatment'] = $resultTreatment->TotalOrthodonticByTreatment ?? 0;
         

         //Count Prosthetic, Implant, Orthodontic by Offer
         $queryOffer = OrderMeasuringConsultingDetail::join('tmp_order_measuring_ids', 'tmp_order_measuring_ids.Id', '=', 'OrderMeasuringConsultingDetail.OrderMeasuringConsultingId')
         ->join('TreatmentMedicalProcedureOffer', 'TreatmentMedicalProcedureOffer.Id', '=', 'OrderMeasuringConsultingDetail.TreatmentMedicalProcedureOfferId')
         ->join('Service', 'Service.ServiceId', '=', 'TreatmentMedicalProcedureOffer.ServiceId')
         ->selectRaw("SUM(CASE WHEN Service.WarrantyType = 'P' THEN 1 ELSE 0 END) as TotalProstheticByOffer, SUM(CASE WHEN Service.WarrantyType = 'I' THEN 1 ELSE 0 END) as TotalImplantByOffer, SUM(CASE WHEN Service.WarrantyType = 'O' THEN 1 ELSE 0 END) as TotalOrthodonticByOffer")
         ->where('TreatmentMedicalProcedureOffer.AnatomyBodyPartItemId', '>', 0);
         
         
         $resultOffer = $queryOffer->first();

         if (!$resultOffer || empty($resultOffer)) {
            //Drop tmp table
            DB::statement("DROP TEMPORARY TABLE IF EXISTS tmp_customer_ids");
            DB::statement("DROP TEMPORARY TABLE IF EXISTS tmp_order_measuring_ids");
            return $result;
         }
         $result['TotalProstheticByOffer'] = $resultOffer->TotalProstheticByOffer ?? 0;
         $result['TotalImplantByOffer'] = $resultOffer->TotalImplantByOffer ?? 0;
         $result['TotalOrthodonticByOffer'] = $resultOffer->TotalOrthodonticByOffer ?? 0;

         //Drop tmp table
         DB::statement("DROP TEMPORARY TABLE IF EXISTS tmp_customer_ids");
         DB::statement("DROP TEMPORARY TABLE IF EXISTS tmp_order_measuring_ids");

         //Return result
         return $result;
      } catch (\Exception $ex) {
         DB::statement("DROP TEMPORARY TABLE IF EXISTS tmp_customer_ids");
         DB::statement("DROP TEMPORARY TABLE IF EXISTS tmp_order_measuring_ids");
         Log::error("countOIP: ", [$ex->getMessage()]);
         return $result;
      }
      return $result;
   }

   public function updateOrderMeasuringConsulting($request)
   {
      $id = $request['Id'] ?? 0;
      $treatmentMedicalProcedureOfferIds = $request['TreatmentMedicalProcedureOfferIds'] ?? [];
      $note = $request['Note'] ?? null;

      if (!$id || $id <= 0) {
         return false;
      }
      $staffId = Auth::user()['StaffId'] ?? 0;

      $data = OrderMeasuringConsulting::find($id);
      if (!$data || empty($data)) {
         return false;
      }

      //Transaction
      DB::beginTransaction();
      try {
         //Tracking before change data
         $tracking = OrderMeasuringConsulting::where('Id', $id)->first();
         if ($tracking && !empty($tracking)) {
            $offerTracking = $tracking->offersByReport();
            if ($offerTracking && $offerTracking->count() > 0) {
               $treatmentMedicalProcedureOfferIdsTrackings = $offerTracking->pluck('TreatmentMedicalProcedureOffer.Id')->toArray();
               $dataTracking = [
                  'OrderMeasuringConsultingId' => $id,
                  'OldData' => json_encode($treatmentMedicalProcedureOfferIdsTrackings),
                  'Note' => $tracking->Note ?? '',
                  'CreatedDate' => Carbon::now()->toDateTimeString(),
                  'CreatedBy' => $staffId,
               ];
               OrderMeasuringConsultingTracking::create($dataTracking);
            }
         }
         $result = OrderMeasuringConsulting::where('Id', $id)
            ->update([
               'Status' => 10,
               'Note' => $note,
               'ConfirmedBy' => $staffId,
               'ConfirmedDate' => Carbon::now(),
               'UpdatedDate' => Carbon::now(),
               'UpdatedBy' => $staffId
            ]);
         if ($treatmentMedicalProcedureOfferIds && is_array($treatmentMedicalProcedureOfferIds) && count($treatmentMedicalProcedureOfferIds)) {
            $data->offersByReport()->sync($treatmentMedicalProcedureOfferIds);
         } else {
            $data->offersByReport()->detach();
         }
         DB::commit();
         return $result;
      } catch (\Exception $e) {
         DB::rollBack();
         Log::error("updateOrderMeasuringConsulting: ", [$e->getMessage()]);
         return false;
      }
      DB::rollBack();
      return false;
   }

   public function getOrderMeasuringConsultingServiceDetail($id)
   {
      $query = OrderMeasuringConsulting::where('OrderMeasuringConsulting.Id', $id);

      $query->with(['offersByTreatment' => function ($query) {
         $query->with(['service' => function ($query) {
            $query->select([
               'ServiceId',
               'ServiceCode',
               'Name',
               'Description',
               'GeneralityLevel',
               'ProstheticLevel',
               'ImplantLevel',
               'OrthodonticLevel',
               'BasePrice',
               'SalePrice',
               'ServiceDomainType',
               'ServiceDomainLevel',
            ]);
         }]);
         $query->with(['anatomyBodyPartItem']);
         $query->with(['branch' => function ($query) {
            $query->select('BranchId', 'BranchCode', 'Name');
         }]);
      }]);

      $query->with(['offersByReport' => function ($query) {
         $query->with(['service' => function ($query) {
            $query->select([
               'ServiceId',
               'ServiceCode',
               'Name',
               'Description',
               'GeneralityLevel',
               'ProstheticLevel',
               'ImplantLevel',
               'OrthodonticLevel',
               'BasePrice',
               'SalePrice',
               'ServiceDomainType',
               'ServiceDomainLevel',
            ]);
         }]);
         $query->with(['anatomyBodyPartItem']);
         $query->with(['branch' => function ($query) {
            $query->select('BranchId', 'BranchCode', 'Name');
         }]);
      }]);

      $query->with(['customer' => function ($query) {
         $query->select('CustomerId', 'CustomerCode', 'FullName');
      }]);
      $query->with(['branch' => function ($query) {
         $query->select('BranchId', 'BranchCode', 'Name');
      }]);
      $query->with(['doctor' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);
      $query->with(['doctorSpecializationCode' => function ($query) {
         $query->select('StaffId', 'SpecializationCode');
      }]);
      $query->with(['createdByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);
      $query->with(['updatedByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);
      $query->with(['confirmedByStaff' => function ($query) {
         $query->select('StaffId', 'FullName', 'StaffCode');
      }]);

      return $query->first();
   }

   public function getRankCustomer($request)
   {
      try {
         $fromDate = $request['FromDate'] ?? '';
         $toDate = $request['ToDate'] ?? '';
         $fromDate = $fromDate.' 00:00:01';
         $toDate = $toDate.' 23:59:59';
         $levelId = $request['LevelId'] ?? 0;
         $keyword = $request['Keyword'] ?? '';
         $lmstart = $request['lmstart'] ?? 0;
         $limit = $request['limit'] ?? 20;

         $data = DB::select(DB::raw("CALL pos.usp_rptCustomerLevelTracking('".$fromDate."','".$toDate."',".$levelId.",'".$keyword."',".$lmstart.",".$limit.")"));

         return $data;
         
      } catch (\Exception $e) {
         Log::error("getRankCustomer: ", [$e->getMessage()]);
         return [];
      }
   }
   
   public function countConsultingPerformance()
   {
      try {
         $startOfMonth = Carbon::now()->startOfMonth()->timestamp;
         $endOfMonth = Carbon::now()->endOfMonth()->timestamp;

         $data = (object) [];
         $dataThisMonth = (object) [];
         $dataSixMonth = (object) [];
         // Danh sách dịch vụ T, P, I, C, tư vấn trong tháng
         $oneMonth = OrderDetail::selectRaw('
               COUNT( s.ProstheticLevel ) AS TotalProstheticLevel,
               COUNT( s.OrthodonticLevel ) AS TotalOrthodonticLevel,
               COUNT( s.ImplantLevel ) AS TotalImplantLevel,
               SUM(CASE WHEN s.GeneralityLevel IS NOT NULL THEN OrderDetail.Quantity ELSE 0 END) AS TotalGeneralityLevel
            ')
            ->join('pos.OrderChanging as oc', 'oc.OrderChangingId', '=', 'OrderDetail.OrderChangingId')
            ->join('pos.Service as s', 's.ServiceId', '=', 'OrderDetail.ServiceId')
            ->whereBetween('oc.ChangedAt', [$startOfMonth, $endOfMonth])
            ->where('OrderDetail.Status', '<>', 0)
            ->first();
         // Danh sách dịch vụ T, P, I, C, đồng ý trong tháng
         $consultedOneMonth = OrderDetail::selectRaw('
               COUNT( s.ProstheticLevel ) AS TotalProstheticLevel,
               COUNT( s.OrthodonticLevel ) AS TotalOrthodonticLevel,
               COUNT( s.ImplantLevel ) AS TotalImplantLevel,
               SUM(CASE WHEN s.GeneralityLevel IS NOT NULL THEN OrderDetail.Quantity ELSE 0 END) AS TotalGeneralityLevel
            ')
            ->join('pos.Service as s', 's.ServiceId', '=', 'OrderDetail.ServiceId')
            ->whereBetween('OrderDetail.ConsultedDate', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->where('OrderDetail.Status', 2)
            ->first();

         $dataThisMonth->ProstheticLevel = number_format($consultedOneMonth->TotalProstheticLevel ?? 0, 0, '.', ',') . ' / ' . number_format($oneMonth->TotalProstheticLevel ?? 0, 0, '.', ',');
         $dataThisMonth->OrthodonticLevel = number_format($consultedOneMonth->TotalOrthodonticLevel ?? 0, 0, '.', ',') . ' / ' . number_format($oneMonth->TotalOrthodonticLevel ?? 0, 0, '.', ',');
         $dataThisMonth->ImplantLevel = number_format($consultedOneMonth->TotalImplantLevel ?? 0, 0, '.', ',') . ' / ' . number_format($oneMonth->TotalImplantLevel ?? 0, 0, '.', ',');
         $dataThisMonth->GeneralityLevel = number_format($consultedOneMonth->TotalGeneralityLevel ?? 0, 0, '.', ',') . ' / ' . number_format($oneMonth->TotalGeneralityLevel ?? 0, 0, '.', ',');


         $start = Carbon::now()->subMonths(6)->startOfMonth()->timestamp;
         $end = Carbon::now()->subMonth()->endOfMonth()->setTime(23, 59, 59)->timestamp;
         // Danh sách dịch vụ T, P, I, C, tư vấn trong 6 tháng trước
         $sixMonthAgo = OrderDetail::selectRaw('
               COUNT( s.ProstheticLevel ) AS TotalProstheticLevel,
               COUNT( s.OrthodonticLevel ) AS TotalOrthodonticLevel,
               COUNT( s.ImplantLevel ) AS TotalImplantLevel,
               SUM(CASE WHEN s.GeneralityLevel IS NOT NULL THEN OrderDetail.Quantity ELSE 0 END) AS TotalGeneralityLevel
            ')
            ->join('pos.OrderChanging as oc', 'oc.OrderChangingId', '=', 'OrderDetail.OrderChangingId')
            ->join('pos.Service as s', 's.ServiceId', '=', 'OrderDetail.ServiceId')
            ->whereBetween('oc.ChangedAt', [$start, $end])
            ->where('OrderDetail.Status', '<>', 0)
            ->first();
         // Danh sách dịch vụ T, P, I, C, đồng ý trong 6 tháng trước
         $consultedSixMonthAgo = OrderDetail::selectRaw('
               COUNT( s.ProstheticLevel ) AS TotalProstheticLevel,
               COUNT( s.OrthodonticLevel ) AS TotalOrthodonticLevel,
               COUNT( s.ImplantLevel ) AS TotalImplantLevel,
               SUM(CASE WHEN s.GeneralityLevel IS NOT NULL THEN OrderDetail.Quantity ELSE 0 END) AS TotalGeneralityLevel
            ')
            ->join('pos.Service as s', 's.ServiceId', '=', 'OrderDetail.ServiceId')
            ->whereBetween('OrderDetail.ConsultedDate', [Carbon::now()->subMonths(6)->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()->setTime(23, 59, 59)])
            ->where('OrderDetail.Status', 2)
            ->first();

         $dataSixMonth->ProstheticLevel = number_format($consultedSixMonthAgo->TotalProstheticLevel ?? 0, 0, '.', ',') . ' / ' . number_format($sixMonthAgo->TotalProstheticLevel ?? 0, 0, '.', ',');
         $dataSixMonth->OrthodonticLevel = number_format($consultedSixMonthAgo->TotalOrthodonticLevel ?? 0, 0, '.', ',') . ' / ' . number_format($sixMonthAgo->TotalOrthodonticLevel ?? 0, 0, '.', ',');
         $dataSixMonth->ImplantLevel = number_format($consultedSixMonthAgo->TotalImplantLevel ?? 0, 0, '.', ',') . ' / ' . number_format($sixMonthAgo->TotalImplantLevel ?? 0, 0, '.', ',');
         $dataSixMonth->GeneralityLevel = number_format($consultedSixMonthAgo->TotalGeneralityLevel ?? 0, 0, '.', ',') . ' / ' . number_format($sixMonthAgo->TotalGeneralityLevel ?? 0, 0, '.', ',');

         $data->ThisMonth = $dataThisMonth;
         $data->SixMonthAgo = $dataSixMonth;

         return [$data];
      } catch (\Exception $e) {
         Log::error("countConsultingPerformance: ", [$e->getMessage()]);
         return [];
      }
      return [];
   }

   public function getConsultingPerformance($request)
   {
      try {
         $branchId = $request['BranchId'] ?? 0;
         $startOfMonth = Carbon::now()->startOfMonth()->toDateTimeString();
         $endOfMonth   = Carbon::now()->endOfMonth()->toDateTimeString();
         $startSixMonthAgo = Carbon::now()->subMonths(6)->startOfMonth()->toDateTimeString();
         $endSixMonthAgo   = Carbon::now()->subMonth()->endOfMonth()->setTime(23, 59, 59)->toDateTimeString();

         $data = DB::select("CALL usp_Report_Consulting_Performance(?, ?, ?, ?, ?)", [
            $branchId,
            $startOfMonth,
            $endOfMonth,
            $startSixMonthAgo,
            $endSixMonthAgo
         ]);

         return $data;
      } catch (\Exception $e) {
         Log::error("getConsultingPerformance: ", [$e->getMessage()]);
         return [];
      }
      return [];
   }

   public function getConsultingPerformanceByBranch($request)
   {
      try {
         $branchId = $request['BranchId'] ?? 0;
         $startOfMonth = Carbon::now()->startOfMonth()->toDateTimeString();
         $endOfMonth   = Carbon::now()->endOfMonth()->toDateTimeString();
         $startSixMonthAgo = Carbon::now()->subMonths(6)->startOfMonth()->toDateTimeString();
         $endSixMonthAgo   = Carbon::now()->subMonth()->endOfMonth()->setTime(23, 59, 59)->toDateTimeString();

         $data = DB::select("CALL pos.usp_Report_Consulting_Performance_By_Branch(?, ?, ?, ?, ?)", [
            $branchId,
            $startOfMonth,
            $endOfMonth,
            $startSixMonthAgo,
            $endSixMonthAgo
         ]);
         
         return $data;
      } catch (\Exception $e) {
         Log::error("getConsultingPerformanceByBranch: ", [$e->getMessage()]);
         return [];
      }
      return [];
   }

   public function countCustomerCare($request)
   {
      $branchId = NULL;
      $arrayBranchId = $request['BranchId'] ?? '';
      $arrayBranch = [];
      if (!empty($arrayBranchId)) {
         $branchId = implode(',', $arrayBranchId);
      } else {
         $staffId = Auth::user()['StaffId'] ?? 0;
         $listStaff = DB::select("CALL pos.usp_Org_GetBranch_ByStaff(?)", [$staffId]);
         if($listStaff){
            foreach($listStaff as $key => $value){
               array_push($arrayBranch, $value->BranchId);
            }
            $branchId = implode(',', $arrayBranch);
         }
      }
      $day = $request['Day'] ?? Carbon::now()->toDateString();
      $data = DB::select("CALL pos.usp_Report_Customer_Care_Count(?, ?)", [
         $branchId,
         $day
      ]);
      return $data;
   }

   public function getReceiptsByStaffInMonth($request) {
      try {
         $staffId = $request['StaffId'] ?? NULL;
         $fromDate = $request['FromDate'] ?? NULL;
         $toDate = $request['ToDate'] ?? NULL;
         $keyword = $request['Keyword'] ?? '';
         $lmstart = $request['lmstart'] ?? 0;
         $limit = $request['limit'] ?? 20;

         if (!$fromDate || !$toDate || !$staffId) {
               return [];
         }

         DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
         $query = OrderDetailFinancialTrans::whereIn('OrderDetailFinancialTrans.ObjectType', ['Receipt', 'Expenditure', 'TransferAmountService'])
            ->join('pos.OrderDetail as od', 'od.OrderDetailId', 'OrderDetailFinancialTrans.OrderDetailId')
            ->join('pos.Customer as c', 'c.CustomerId', 'OrderDetailFinancialTrans.CustomerId')
            ->join('pos.Service as s', 's.ServiceId', 'od.ServiceId')
            ->where('OrderDetailFinancialTrans.CreatedDate', '>=', $fromDate)
            ->where('OrderDetailFinancialTrans.CreatedDate', '<=', $toDate)
            ->where('OrderDetailFinancialTrans.ConsultingStaffId', '=', $staffId)
            ->where('od.IsOverPaymentAmount', '<>', 1)
            ->when($keyword, function ($q) use ($keyword) {
               $keyword = trim($keyword);
               $q->where(function ($sub) use ($keyword) {
                     $sub->where('c.FullName', 'LIKE', "%{$keyword}%")
                        ->orWhere('c.CustomerCode', 'LIKE', "%{$keyword}%");
               });
            })
            ->groupBy(
               'OrderDetailFinancialTrans.ObjectType',
               'OrderDetailFinancialTrans.CustomerId',
               'od.OrderChangingId',
               'od.ServiceId',
               'OrderDetailFinancialTrans.CreatedDate'
            )
            ->havingRaw('SUM(OrderDetailFinancialTrans.ReceiptAmount + OrderDetailFinancialTrans.TransferAmount - OrderDetailFinancialTrans.ExpenditureAmount) != 0') // ✅ thêm HAVING
            ->selectRaw('
               OrderDetailFinancialTrans.CreatedDate,
               OrderDetailFinancialTrans.ObjectType,
               OrderDetailFinancialTrans.CustomerId,
               od.OrderChangingId,
               od.ServiceId,
               COALESCE(SUM(OrderDetailFinancialTrans.ReceiptAmount), 0)     AS TotalReceiptAmount,
               COALESCE(SUM(OrderDetailFinancialTrans.ExpenditureAmount), 0) AS TotalExpenditureAmount,
               COALESCE(SUM(OrderDetailFinancialTrans.TransferAmount), 0)    AS TotalTransferAmount,
               ANY_VALUE(c.FullName)                                         AS CustomerName,
               ANY_VALUE(c.CustomerCode)                                     AS CustomerCode,
               ANY_VALUE(s.Name)                                             AS ServiceName,
               ANY_VALUE(s.ServiceCode)                                      AS ServiceCode
            ')
            ->orderByDesc('CreatedDate');

         $results = $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);
         DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
         return $results;

      } catch (\Exception $e) {
         Log::error('ReportController@getReceiptsByStaffInMonth: '.$e->getMessage());
      }
   }
}
