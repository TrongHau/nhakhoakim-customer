<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ExportMaterialTreatmentDetail extends Model
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
    protected $table = 'ExportMaterialTreatmentDetail';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'ExportMaterialTreatmentDetailId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ExportMaterialTreatmentDetailId',
        'ExportMaterialTreatmentId',
        'MaterialId',
        'Quantity',
        'RealQuantity',
        'UnitId'
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

    public function material()
    {
        return $this->belongsTo(Material::class, 'MaterialId', 'MaterialId');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'UnitId', 'UnitId');
    }
}
