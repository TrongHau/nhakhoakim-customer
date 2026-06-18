<?php

namespace App\Repositories;

use App\CountryDialInfo;
use App\Repositories\Abstracts\EloquentRepository;

class CountryDialInfoRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return CountryDialInfo::class;
   }

   public function getAll()
   {
		$query = $this->_model->newQuery();

      $query->where('State', 1);

      $query->orderBy('Priority');

      $result = $query->get();

      return $result;
   }
}
