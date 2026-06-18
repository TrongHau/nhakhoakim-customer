<?php

namespace App\Repositories;

use App\ConsultationTraining;
use App\Repositories\Abstracts\EloquentRepository;

class ConsultationTrainingRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return ConsultationTraining::class;
   }
}
