<?php

namespace App\Repositories;

use App\Deposit;
use App\Libs\Factory;
use App\PayProvider;
use App\Repositories\Abstracts\EloquentRepository;
use Illuminate\Support\Facades\Log;

class DepositRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string {
      //Required model
      return Deposit::class;
   }

}
