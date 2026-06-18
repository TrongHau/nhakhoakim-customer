<?php

namespace App\Repositories;

use App\Repositories\Abstracts\EloquentRepository;
use App\CustomerLevel;


class CustomerLevelRepository extends EloquentRepository
{
    protected function getModel()
    {
        return CustomerLevel::class;
    }
}