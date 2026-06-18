<?php

namespace App\Repositories;

use App\Category;
use App\Repositories\Abstracts\EloquentRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return Category::class;
   }

   public function getCategoryParentChild()
   {
      $query = $this->_model->newQuery();
      $query->select(
         'CategoryId',
         'CategoryCode',
         'CategoryName as Name',
         'NameUnsign',
         'Level',
         'ParentId',
         'IsActive',
         'CreatedBy',
         'CreatedDate',
         'UpdatedBy',
         'UpdatedDate'
      );
      $query->where('IsActive', 1);
      $query->where('Level', 0);
      $query->where('ParentId', 0);
      $query->orderBy('CategoryCode', 'asc');
      
      $query->with(['childCategories' => function ($subQuery) {
         $subQuery->select(
            'CategoryId',
            'CategoryCode',
            'CategoryName as Name',
            'NameUnsign',
            'Level',
            'ParentId',
            'IsActive',
            'CreatedBy',
            'CreatedDate',
            'UpdatedBy',
            'UpdatedDate'
         );
         $subQuery->with(['childCategories' => function ($subQuery2) {
            $subQuery2->select(
               'CategoryId',
               'CategoryCode',
               'CategoryName as Name',
               'NameUnsign',
               'Level',
               'ParentId',
               'IsActive',
               'CreatedBy',
               'CreatedDate',
               'UpdatedBy',
               'UpdatedDate'
            );
         }]);
      }]);
      return $query->get();
   }

   public function checkCategory($name)
   {
      $nameUnsign = Str::slug($name);
      $query = $this->_model->newQuery();

      $result = $query->where('NameUnsign', $nameUnsign)->where('IsActive', 1)->first();
      if (isset($result) && !empty($result)) {
         return true;
      }

      return false;
   }

   public function getCategoryByLv($id)
   {
      $query = $this->_model->newQuery();

      $category = $query->where('CategoryId', $id)->first();

      if (!$category) {
         return [];
      }

      // $categoriesLevel2 = collect();die;

      if ($category->Level === 0) {
         $categoriesLevel1 = DB::table('sale.Category')
            ->where('ParentId', $id)
            ->where('Level', 1)
            ->get();
         $lv1Ids = $categoriesLevel1->pluck('CategoryId');

         $categoriesLevel2 = DB::table('sale.Category')->whereIn('ParentId', $lv1Ids)
            ->where('Level', 2)
            ->get();

      } elseif ($category->Level == 1) {
         $categoriesLevel2 = DB::table('sale.Category')->where('ParentId', $id)
            ->where('Level', 2)
            ->get();
      }

      return $categoriesLevel2 ?? [];
   }
}
