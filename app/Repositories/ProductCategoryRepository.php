<?php

namespace App\Repositories;

use App\ProductCategory;
use App\Repositories\Abstracts\EloquentRepository;

class ProductCategoryRepository extends EloquentRepository
{
    protected function getModel()
    {
        return ProductCategory::class;
    }

    public function getActiveList()
    {
        return ProductCategory::query()
            ->where('Status', 1)
            ->orderBy('Priority')
            ->orderBy('Name')
            ->get(['ProductCategoryId','Code', 'Name', 'ParentId', 'Priority','Status']);
    }
}
