<?php

namespace App\Repositories;

use App\OCRTracking;
use App\Repositories\Abstracts\EloquentRepository;
use Illuminate\Support\Facades\Auth;

class OCRTrackingRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return OCRTracking::class;
   }

   public function createTracking($imageURL, $data) 
   {
      $dataInsert = [
         'ImageFile' => $imageURL,
         'Data' => $data,
         'CreatedBy' => Auth::user()['StaffId'] ?? 0,
         'CreatedDate' => date('Y-m-d H:i:s')
      ];
      return $this->_model->create($dataInsert);
   }
}
