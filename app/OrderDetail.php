<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
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
    protected $table = 'OrderDetail';


    protected $primaryKey = 'OrderDetailId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'OrderId',
        'OrderDetailId',
        'TreatmentId',
        'ServiceId',
        'GeneralityLevel',
        'ProstheticLevel',
        'ImplantLevel',
        'OrthodonticLevel',
        'IndependentTreatmentUnitQuantity',
        'UnitQuantityLevel',
        'MedicalProcedureId',
        'ServiceName',
        'Status',
        'Note',
        'Quantity',
        'Amount',
        'ServicePrice',
        'TaxAmount',
        'TaxPercent',
        'TreatmentMedicalProcedureId',
        'AnatomyBodyPartItemICD10Code',
        'AnatomyBodyPartItemName',
        'AnatomyBodyPartItemCode',
        'AnatomyBodyPartItemId',
        'DiscountPercent',
        'DiscountAmount',
        'OrderChangingId',
        'ServiceCode',
        'TabID',
        'Migrate',
        'ProcessState',
        'AmountNotAllocated',
        'IsNew',
        'OldOrderChangingId',
        'FirstTreatmentTime',
        'InvoiceTrackingTime',
        'DeletedDate',
        'LatestUpdated',
        'ConsultingStaffId',
        'FirstReceiptId',
        'FirstReceiptTime',
        'IsPayInstallments'
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
}
