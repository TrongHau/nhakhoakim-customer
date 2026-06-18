<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerDocument extends Model
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
    protected $table = 'CustomerDocuments';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'CustomerDocumentId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'CustomerDocumentId',
        'CustomerId',
        'CustomerName',
        'Birthday',
        'Gender',
        'BirthPlace',
        'PermanentAddress',
        'DocumentType',
        'DocumentNumber',
        'IssuedDate',
        'ExpiryDate',
        'Priority',
        'Status',
        'IssuingAuthorityId',
        'IssuingType',
        'Note',
        'Class',
        'CreatedDate',
        'CreatedBy',
        'UpdatedDate',
        'UpdatedBy'
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

    public function customerDocumentImages()
    {
        return $this->hasMany(CustomerDocumentImage::class, 'CustomerDocumentId', 'CustomerDocumentId');
    }

    public function issuingAuthority()
    {
        return $this->belongsTo(PartnerCompany::class, 'IssuingAuthorityId', 'PartnerCompanyId');
    }
}
