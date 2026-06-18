<?php

namespace App\Repositories;

use App\SystemAccessTracking;
use App\Repositories\Abstracts\EloquentRepository;
use App\Staff;

class SystemAccessTrackingRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return SystemAccessTracking::class;
   }

   public function getTrackingCheckIP($conditions = [])
   {
      $keyword = $conditions['Keyword'] ?? '';
      $fromDate = $conditions['FromDate'] ?? '';
      $toDate = $conditions['ToDate'] ?? '';
      $lmstart = $conditions['lmstart'] ?? 0;
      $limit = $conditions['limit'] ?? 20;

      $testStaff = Staff::select('StaffId')->where('IsTest', 1)->get();
      $testStaffIds = [];
      if ($testStaff && !empty($testStaff)) {
         $testStaffIds = $testStaff->pluck('StaffId')->toArray();
      }

      $query = $this->_model->newQuery();

      $query->join('in.Staff as s', 's.StaffId', 'SystemAccessTracking.StaffId');
      
      $query->select('SystemAccessTracking.*');

      $query->where('SystemAccessTracking.RefCustomerId', 0);

      $query->where('SystemAccessTracking.FeatureAccess', 'CheckIP');

      if (!empty($testStaffIds) && count($testStaffIds) > 0) {
         $query->whereNotIn('SystemAccessTracking.StaffId', $testStaffIds);
      }

      if (isset($keyword) && !empty($keyword)) {
         $query->where(function ($query) use ($keyword) {
            $query->where('FullName', 'LIKE', "%$keyword%")
               ->orWhere('StaffCode', 'LIKE', "%$keyword%");
         });
      }

      if (isset($fromDate) && !empty($fromDate)) {
         $query->where('SystemAccessTracking.CreatedDate', '>=', $fromDate);
      }

      if (isset($toDate) && !empty($toDate)) {
         $query->where('SystemAccessTracking.CreatedDate', '<=', $toDate);
      }

      $query->orderByDesc('SystemAccessTracking.CreatedDate');

      $query->with(['staff' => function ($query) {
         $query->select('StaffId', 'StaffCode', 'FullName');
      }]);

      $query->with(['workProfilePosition' => function ($query) {
         $query->select('WorkProfilePositionId', 'Name');
      }]);

      $results = $query->paginate($limit,['*'],'page', round((int) $lmstart/ (int) $limit) + 1);

      return $results;
   }

   public function getTrackingAccessCustomer($conditions = [])
   {
      $keyword = $conditions['Keyword'] ?? '';
      $fromDate = $conditions['FromDate'] ?? '';
      $toDate = $conditions['ToDate'] ?? '';
      $lmstart = $conditions['lmstart'] ?? 0;
      $limit = $conditions['limit'] ?? 20;

      $query = $this->_model->newQuery();

      $query->join('in.Staff as s', 's.StaffId', 'SystemAccessTracking.StaffId');

      $query->join('Customer as c', 'c.CustomerId', 'SystemAccessTracking.RefCustomerId');
      
      $query->select('SystemAccessTracking.*');

      $query->where('SystemAccessTracking.RefCustomerId', '>', 0);

      $query->where('SystemAccessTracking.FeatureAccess', 'AccessCustomer');

      $testStaff = Staff::select('StaffId')->where('IsTest', 1)->get();
      $testStaffIds = [];
      if ($testStaff && !empty($testStaff)) {
         $testStaffIds = $testStaff->pluck('StaffId')->toArray();
      }

      if (!empty($testStaffIds) && count($testStaffIds) > 0) {
         $query->whereNotIn('SystemAccessTracking.StaffId', $testStaffIds);
      }
      
      if (isset($keyword) && !empty($keyword)) {
         $query->where(function ($query) use ($keyword) {
            $query->where('s.FullName', 'LIKE', "%$keyword%")
               ->orWhere('s.StaffCode', 'LIKE', "%$keyword%")
               ->orWhere('c.FullName', 'LIKE', "%$keyword%");
         });
      }

      if (isset($fromDate) && !empty($fromDate)) {
         $query->where('SystemAccessTracking.CreatedDate', '>=', $fromDate);
      }

      if (isset($toDate) && !empty($toDate)) {
         $query->where('SystemAccessTracking.CreatedDate', '<=', $toDate);
      }

      $query->orderByDesc('SystemAccessTracking.CreatedDate');

      $query->with(['staff' => function ($query) {
         $query->select('StaffId', 'StaffCode', 'FullName');
      }]);

      $query->with(['customer' => function ($query) {
         $query->select('CustomerId', 'CustomerCode', 'FullName', 'Address');
      }]);

      $query->with(['workProfilePosition' => function ($query) {
         $query->select('WorkProfilePositionId', 'Name');
      }]);

      $results = $query->paginate($limit,['*'],'page', round((int) $lmstart/ (int) $limit) + 1);

      return $results;
   }

   public function getTrackingAccessLoginOutside($conditions = [])
   {
      $keyword = $conditions['Keyword'] ?? '';
      $fromDate = $conditions['FromDate'] ?? '';
      $toDate = $conditions['ToDate'] ?? '';
      $lmstart = $conditions['lmstart'] ?? 0;
      $limit = $conditions['limit'] ?? 20;

      $testStaff = Staff::select('StaffId')->where('IsTest', 1)->get();
      $testStaffIds = [];
      if ($testStaff && !empty($testStaff)) {
         $testStaffIds = $testStaff->pluck('StaffId')->toArray();
      }

      $query = $this->_model->newQuery();

      $query->join('in.Staff as s', 's.StaffId', 'SystemAccessTracking.StaffId');
      
      $query->select('SystemAccessTracking.*');

      $query->where('SystemAccessTracking.FeatureAccess', 'AccessLoginOutside');

      if (!empty($testStaffIds) && count($testStaffIds) > 0) {
         $query->whereNotIn('SystemAccessTracking.StaffId', $testStaffIds);
      }

      if (isset($keyword) && !empty($keyword)) {
         $query->where(function ($query) use ($keyword) {
            $query->where('FullName', 'LIKE', "%$keyword%")
               ->orWhere('StaffCode', 'LIKE', "%$keyword%");
         });
      }

      if (isset($fromDate) && !empty($fromDate)) {
         $query->where('SystemAccessTracking.CreatedDate', '>=', $fromDate);
      }

      if (isset($toDate) && !empty($toDate)) {
         $query->where('SystemAccessTracking.CreatedDate', '<=', $toDate);
      }

      $query->orderByDesc('SystemAccessTracking.CreatedDate');

      $query->with(['staff' => function ($query) {
         $query->select('StaffId', 'StaffCode', 'FullName');
      }]);

      $query->with(['workProfilePosition' => function ($query) {
         $query->select('WorkProfilePositionId', 'Name');
      }]);


      $query->with(['workProfilePositionGroup' => function ($query) {
         $query->select('Id', 'Name', 'Code', 'Priority');
      }]);

      $results = $query->paginate($limit,['*'],'page', round((int) $lmstart/ (int) $limit) + 1);

      return $results;
   }

   public function getTrackingVerifyAccessCustomer($conditions = [])
   {
      $keyword = $conditions['Keyword'] ?? '';
      $fromDate = $conditions['FromDate'] ?? '';
      $toDate = $conditions['ToDate'] ?? '';
      $lmstart = $conditions['lmstart'] ?? 0;
      $limit = $conditions['limit'] ?? 20;

      $query = $this->_model->newQuery();

      $query->join('in.Staff as s', 's.StaffId', 'SystemAccessTracking.StaffId');

      $query->join('Customer as c', 'c.CustomerId', 'SystemAccessTracking.RefCustomerId');
      
      $query->select('SystemAccessTracking.*');

      $query->where('SystemAccessTracking.RefCustomerId', '>', 0);

      $query->where('SystemAccessTracking.FeatureAccess', 'VerifyAccessCustomer');

      $testStaff = Staff::select('StaffId')->where('IsTest', 1)->get();
      $testStaffIds = [];
      if ($testStaff && !empty($testStaff)) {
         $testStaffIds = $testStaff->pluck('StaffId')->toArray();
      }

      if (!empty($testStaffIds) && count($testStaffIds) > 0) {
         $query->whereNotIn('SystemAccessTracking.StaffId', $testStaffIds);
      }
      
      if (isset($keyword) && !empty($keyword)) {
         $query->where(function ($query) use ($keyword) {
            $query->where('s.FullName', 'LIKE', "%$keyword%")
               ->orWhere('s.StaffCode', 'LIKE', "%$keyword%")
               ->orWhere('c.FullName', 'LIKE', "%$keyword%");
         });
      }

      if (isset($fromDate) && !empty($fromDate)) {
         $query->where('SystemAccessTracking.CreatedDate', '>=', $fromDate);
      }

      if (isset($toDate) && !empty($toDate)) {
         $query->where('SystemAccessTracking.CreatedDate', '<=', $toDate);
      }

      $query->orderByDesc('SystemAccessTracking.CreatedDate');

      $query->with(['staff' => function ($query) {
         $query->select('StaffId', 'StaffCode', 'FullName');
      }]);

      $query->with(['customer' => function ($query) {
         $query->select('CustomerId', 'CustomerCode', 'FullName', 'Address');
      }]);

      $query->with(['workProfilePosition' => function ($query) {
         $query->select('WorkProfilePositionId', 'Name');
      }]);

      $results = $query->paginate($limit,['*'],'page', round((int) $lmstart/ (int) $limit) + 1);

      return $results;
   }
}
