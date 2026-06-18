<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VnDistrict extends Model  
{

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'VnDistrict';
    protected $primaryKey = 'VnDistrictId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = ['VnDistrictId', 'VnProvinceId', 'DistrictCode', 'DistrictPostalCode', 'NameVi', 'NameEn', 'LabelVi', 'LabelEn', 'Ordering'];

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

}
