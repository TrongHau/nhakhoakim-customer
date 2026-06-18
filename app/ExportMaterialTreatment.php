<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ExportMaterialTreatment extends Model
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
    protected $table = 'ExportMaterialTreatment';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'ExportMaterialTreatmentId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ExportMaterialTreatmentId',
        'Code',
        'TreatmentId',
        'BranchId',
        'State',
        'AddAt',
        'AddBy',
        'EditAt',
        'EditBy'
    ];

    //Appends
    protected $appends = [
        'created_date',
        'updated_date'
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

    //Attribute
    public function getCreatedDateAttribute() {
        return $this->AddAt ? date('Y-m-d H:i:s', $this->AddAt) : null;
    }

    public function getUpdatedDateAttribute() {
        return $this->EditAt ? date('Y-m-d H:i:s', $this->EditAt) : null;
    }

    //Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'BranchId', 'BranchId');
    }

    public function createdByStaff()
    {
        return $this->belongsTo(Staff::class, 'AddBy', 'StaffId');
    }

    public function updatedByStaff()
    {
        return $this->belongsTo(Staff::class, 'EditBy', 'StaffId');
    }

    public function detail()
    {
        return $this->hasMany(ExportMaterialTreatmentDetail::class, 'ExportMaterialTreatmentId', 'ExportMaterialTreatmentId');
    }

    public function treatment()
    {
        return $this->belongsTo(Treatment::class, 'TreatmentId', 'TreatmentId');
    }
}
