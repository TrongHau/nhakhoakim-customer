<?php

namespace App\Repositories;

use App\CustomerDocument;
use App\CustomerDocumentImage;
use App\Libs\Helper;
use App\Repositories\Abstracts\EloquentRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerDocumentRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return CustomerDocument::class;
   }

   public function createDocument($data)
   {
      $staffId = Auth::user()['StaffId'] ?? 0;
      $createData = [
         'CustomerId' => $data['CustomerId'] ?? 0,
         'CustomerName' => $data['CustomerName'] ?? '',
         'Birthday' => $data['Birthday'] ?? null,
         'Gender' => $data['Gender'] ?? 0,
         'BirthPlace' => $data['BirthPlace'] ?? '',
         'PermanentAddress' => $data['PermanentAddress'] ?? '',
         'DocumentType' => $data['DocumentType'] ?? 0,
         'DocumentNumber' => $data['DocumentNumber'] ?? '',
         'IssuedDate' => $data['IssuedDate'] ?? date('Y-m-d'),
         'ExpiryDate' => $data['ExpiryDate'] ?? null,
         'Priority' => $data['Priority'] ?? 0,
         'Status' => $data['Status'] ?? 1,
         'IssuingAuthorityId' => $data['IssuingAuthorityId'] ?? 0,
         'IssuingType' => $data['IssuingType'] ?? null,
         'Note' => $data['Note'] ?? '',
         'Class' => $data['Class'] ?? 0,
         'CreatedBy' => $staffId,
         'UpdatedBy' => $staffId,
         'CreatedDate' => date('Y-m-d H:i:s'),
         'UpdatedDate' => date('Y-m-d H:i:s')
      ];
      $customerDocument = $this->_model->create($createData);

      if (!$customerDocument || empty($customerDocument)) {
         return $customerDocument;
      }
      $customerDocumentId = $customerDocument->CustomerDocumentId;

      if (!$customerDocumentId || empty($customerDocumentId)) {
         return $customerDocument;
      }
      $documentFiles = $data['DocumentFiles'] ?? [];

      if ($this->insertDocumentImage($customerDocument, $documentFiles)) {
         return $customerDocument;
      }
      return false;
      
   }

   public function editDocument($customerDocumentId, $data)
   {
      if (!$customerDocumentId || empty($customerDocumentId)) {
         return false;
      }
      $staffId = Auth::user()['StaffId'] ?? 0;
      $updateData = [];
      if (isset($data['CustomerName']) && !empty($data['CustomerName'])) {
         $updateData['CustomerName'] = $data['CustomerName'];
      }
      if (isset($data['Birthday']) && !empty($data['Birthday'])) {
         $updateData['Birthday'] = $data['Birthday'];
      }
      if (isset($data['Gender']) && !empty($data['Gender'])) {
         $updateData['Gender'] = $data['Gender'];
      }
      if (isset($data['BirthPlace']) && !empty($data['BirthPlace'])) {
         $updateData['BirthPlace'] = $data['BirthPlace'];
      }
      if (isset($data['PermanentAddress']) && !empty($data['PermanentAddress'])) {
         $updateData['PermanentAddress'] = $data['PermanentAddress'];
      }
      if (isset($data['DocumentType']) && !empty($data['DocumentType'])) {
         $updateData['DocumentType'] = $data['DocumentType'];
      }
      if (isset($data['DocumentNumber']) && !empty($data['DocumentNumber'])) {
         $updateData['DocumentNumber'] = $data['DocumentNumber'];
      }
      if (isset($data['IssuedDate']) && !empty($data['IssuedDate'])) {
         $updateData['IssuedDate'] = $data['IssuedDate'];
      }
      if (isset($data['ExpiryDate']) && !empty($data['ExpiryDate'])) {
         $updateData['ExpiryDate'] = $data['ExpiryDate'];
      }
      if (isset($data['Priority']) && !empty($data['Priority'])) {
         $updateData['Priority'] = $data['Priority'];
      }
      if (isset($data['Status']) && !empty($data['Status'])) {
         $updateData['Status'] = $data['Status'];
      }
      if (isset($data['IssuingAuthorityId']) && !empty($data['IssuingAuthorityId'])) {
         $updateData['IssuingAuthorityId'] = $data['IssuingAuthorityId'];
      }
      if (isset($data['IssuingType']) && !empty($data['IssuingType'])) {
         $updateData['IssuingType'] = $data['IssuingType'];
      }
      if (isset($data['Note']) && !empty($data['Note'])) {
         $updateData['Note'] = $data['Note'];
      }
      if (isset($data['Class']) && !empty($data['Class'])) {
         $updateData['Class'] = $data['Class'];
      }
      $updateData['UpdatedBy'] = $staffId;
      $updateData['UpdatedDate'] = date('Y-m-d H:i:s');
      $result = $this->_model->where('CustomerDocumentId', $customerDocumentId)->update($updateData);
      if (!$result) {
         return false;
      }
      $customerDocument = $this->_model->find($customerDocumentId);


      $documentFiles = $data['DocumentFiles'] ?? [];
      if ($this->insertDocumentImage($customerDocument, $documentFiles)) {
         return $customerDocument;
      }
      return false;
   }

   public function removeDocument($customerDocumentId)
   {
      if (!$customerDocumentId || empty($customerDocumentId)) {
         return false;
      }
      return $this->_model->where('CustomerDocumentId', $customerDocumentId)->update([
         'Status' => 0,
         'UpdatedBy' => Auth::user()['StaffId'] ?? 0,
         'UpdatedDate' => date('Y-m-d H:i:s')
      ]);
   }

   public function getDocumentByCustomer($customerId, $conditions = [])
   {
      $query = $this->_model->newQuery();

      $query->where('CustomerId', $customerId);

      $query->where('Status', 1);

      if (isset($conditions['Status']) && $conditions['Status'] == 'Active') {
         $query->where(function ($query) {
            $query->where('ExpiryDate', '>=', date('Y-m-d 00:00:00'))
            ->orWhereNull('ExpiryDate');
         });
      }

      $query->orderByDesc('CreatedDate');

      $query->with(['customerDocumentImages' => function ($query) {
         $query->select(['CustomerDocumentId', 'LinkCDN']);
      }]);
      $query->with(['issuingAuthority']);
      
      $customerDocuments = $query->get();

      if (!$customerDocuments || empty($customerDocuments)) {
         return $customerDocuments;
      }
      foreach ($customerDocuments as $customerDocument) {
         $documentFiles = [];
         if (isset($customerDocument->customerDocumentImages) && !empty($customerDocument->customerDocumentImages)) {
            foreach ($customerDocument->customerDocumentImages as $documentImage) {
               $documentFiles[] = $documentImage->LinkCDN ?? '';
            }
         }
         $customerDocument->DocumentFiles = array_values(array_filter($documentFiles));
      }

      return $customerDocuments;
      
   }

   public function uploadDocument($data)
   {
      $staffId = Auth::user()['StaffId'] ?? 0;
      $createData = [];
      if (isset($data['LinkCDN']) && !empty($data['LinkCDN'])) {
         foreach ($data['LinkCDN'] as $linkCDN) {
            $createData[] = [
               'CustomerDocumentId' => $data['CustomerDocumentId'] ?? 0,
               'CustomerId' => $data['CustomerId'] ?? 0,
               'LinkCDN' => $linkCDN,
               'PositionSide' => $data['PositionSide'] ?? 0,
               'CreatedBy' => $staffId,
               'CreatedDate' => date('Y-m-d H:i:s'),
            ];
         }
      }
      DB::beginTransaction();
      try {
         if (!empty($createData) && count($createData) > 0) {
            CustomerDocumentImage::insert($createData);
            DB::commit();
            return true;
         }
      } catch (\Exception $e) {
         DB::rollBack();
         return false;
      }
      DB::rollBack();
      return false;
      
   }

   public function insertDocumentImage($customerDocument, $documentFiles) 
   {
      if (!$customerDocument || empty($customerDocument)) {
         return $customerDocument;
      }
      //Remove all old images
      CustomerDocumentImage::where('CustomerDocumentId', ($customerDocument->CustomerDocumentId ?? 0))->delete();

      if (!$documentFiles || empty($documentFiles) || !is_array($documentFiles) || count($documentFiles) < 1) {
         return $customerDocument;
      }
      
      $linkCDNs = [];
      foreach ($documentFiles as $file) {
         if (!$file || empty($file)) {
            continue;
         }
         if (is_string($file)) {
            $linkCDNs[] = $file;
            continue;
         }
         $urlFile = Helper::uploadFileToServer($file, 'Document/'.($customerDocument->CustomerId ?? 0));

         if (!$urlFile || empty($urlFile)) {
            continue;
         }
         $linkCDNs[] = API_MEDIA .'/'. $urlFile;
      }

      if (empty($linkCDNs) || count($linkCDNs) < 1) {
         return $customerDocument;
      }
      $staffId = Auth::user()['StaffId'] ?? 0;
      $createDataImage = [];
      foreach ($linkCDNs as $linkCDN) {
         $createDataImage[] = [
            'CustomerDocumentId' => $customerDocument->CustomerDocumentId,
            'CustomerId' => $customerDocument->CustomerId ?? 0,
            'LinkCDN' => $linkCDN,
            'PositionSide' => 0,
            'CreatedBy' => $staffId,
            'CreatedDate' => date('Y-m-d H:i:s'),
         ];
      }
      try {
         CustomerDocumentImage::insert($createDataImage);
      } catch (\Exception $e) {
         Log::error("Error create document image: ". $e->getMessage());
         return false;
      }
      return $customerDocument;
   }
}
