<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TreatmentMedicalProcedureOffer extends Model
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
    protected $table = 'TreatmentMedicalProcedureOffer';


    protected $primaryKey = 'Id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Id',
        'TreatmentId',
        'MedicalProcedureId',
        'AnatomyBodyPartItemId',
        'ServiceId',
        'BasePrice',
        'SalePrice',
        'Amount',
        'TaxAmount',
        'TaxPercent',
        'Ordering',
        'TotalDaysToComplete',
        'ResponsibleId',
        'BranchId',
        'OfferNumber',
        'Quantity',
        'DiscountType',
        'DiscountPercent',
        'DiscountAmount',
        'PromotionId',
        'PromotionVoucherId',
        'AddedAt',
        'IsDeleted'
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

    public function service()
    {
        return $this->belongsTo(Service::class, 'ServiceId', 'ServiceId');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'BranchId', 'BranchId');
    }

    public function anatomyBodyPartItem()
    {
        return $this->belongsTo(AnatomyBodyPartItem::class, 'AnatomyBodyPartItemId', 'AnatomyBodyPartItemId');
    }
}
