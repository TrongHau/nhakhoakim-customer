<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Category extends Model  
{

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql_sale';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'Category';

    /**
     * The primary key table used by the model.
     *
     * @var string
     */
    protected $primaryKey = 'CategoryId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'CategoryId',
        'CategoryCode',
        'CategoryName',
        'NameUnsign',
        'Level',
        'ParentId',
        'IsActive',
        'CreatedBy',
        'CreatedDate',
        'UpdatedBy',
        'UpdatedDate'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [];

    public $timestamps = false;

    public function childCategories()
    {
        return $this->hasMany(Category::class, 'ParentId', 'CategoryId')->where('IsActive', 1);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'ProductCategory', 'ProductId', 'ProductId');
    }
}
