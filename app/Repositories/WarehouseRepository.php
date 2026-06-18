<?php

namespace App\Repositories;

use App\Warehouse;
use App\Repositories\Abstracts\EloquentRepository;

class WarehouseRepository extends EloquentRepository
{
    protected function getModel()
    {
        return Warehouse::class;
    }

    public function getActiveList()
    {
        return Warehouse::query()->where('Status', 1)->get();
    }

    public function getCentralWarehouse()
    {
        return Warehouse::query()->where('WarehouseType', 1)->where('Status', 1)->first();
    }
}
