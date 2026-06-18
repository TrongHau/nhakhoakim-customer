<?php

namespace App\Repositories;

use App\CustomerPushToSocial;
use App\Repositories\Abstracts\EloquentRepository;

class CustomerPushToSocialRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return CustomerPushToSocial::class;
   }

   public function setCustomerPushed($customerId)
   {
      if (!$customerId || empty($customerId)) {
         return false;
      }
      return $this->_model->where('CustomerId', $customerId)->update(['IsPush' => 1]);
   }

   public function getCustomerByChannel($channel = null, $conditions = [])
   {
      if (!$channel || empty($channel)) {
         return [];
      }
      $query = $this->_model->newQuery();
      $query->where('ChannelPushSale', $channel);
      $query->where('IsPush', '=', 0);

      if (isset($conditions['CreatedDate']) && !empty($conditions['CreatedDate'])) {
         $query->where('CreatedDate', '>=', $conditions['CreatedDate'] . ' 00:00:00');
         $query->where('CreatedDate', '<=', $conditions['CreatedDate'] . ' 23:59:59');
      }
      return $query->get();
   }
}
