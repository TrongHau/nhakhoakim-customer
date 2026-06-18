<?php

namespace App\Repositories;

use App\PersonTitle;
use App\Repositories\Abstracts\EloquentRepository;

class PersonTitleRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return PersonTitle::class;
   }

   public function getPersonTitleList()
   {
      $query = $this->_model->newQuery();

      $query->where('IsActive', 1);

      return $query->get();
   }
}
