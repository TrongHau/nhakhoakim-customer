<?php

namespace App\Repositories;

use App\UnitInventory;
use App\Repositories\Abstracts\EloquentRepository;

class UnitRepository extends EloquentRepository
{
    protected function getModel()
    {
        return UnitInventory::class;
    }

    public function getActiveList()
    {
        return UnitInventory::query()
            ->where('Status', 1)
            ->orderBy('Priority')
            ->orderBy('Name')
            ->get(['UnitId', 'Code', 'Name', 'IsBaseUnit']);
    }
}
