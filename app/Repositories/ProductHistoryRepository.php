<?php

namespace App\Repositories;

use App\ProductHistory;
use App\Repositories\Abstracts\EloquentRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductHistoryRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return ProductHistory::class;
   }

   public function trackingImport($data)
   {
      DB::beginTransaction();
      try {
         $tracking = DB::table('sale.ProductHistory')->insert($data);
         if ($tracking) {
            DB::commit();
            return $tracking;
         }
         DB::rollBack();
      } catch (\Exception $ex) {
         DB::rollBack();
         Log::error("Error trackingImport", [$ex->getMessage()]);
      }
      return false;
   }
}
