<?php

namespace App\Repositories;

use App\LoyaltyPoint;
use App\Repositories\Abstracts\EloquentRepository;

class LoyaltyPointRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return LoyaltyPoint::class;
   }

   public function getPointByCustomer($conditions)
   {
      $customerId = $conditions['CustomerId'] ?? 0;

      $query = $this->_model->newQuery();
      $query->where('CustomerId', $customerId);
      $query->addSelect('Tier', 'AvailablePoints');

      return $query->first();
   }

   public function getPointDetailByCustomer($conditions)
   {
      $customerId = $conditions['CustomerId'] ?? 0;

      $query = $this->_model->newQuery();
      $query->where('CustomerId', $customerId);

      return $query->first();
   }
}
