<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql_in';
    
    //Table name
    protected $table = 'Branch';

    //Primary key
    protected $primaryKey = 'BranchId';

    //Filter column
    protected $fillable = [
        'BranchId',
        'BranchCode',
        'Name',
        'Address',
        'CompanyId',
        'CountryId',
        'ProvinceId',
        'DistrictId',
        'WardId',
        'BusinessLicenseCode',
        'BusinessLicenseName',
        'PublicPhoneNumber',
        'PrivatePhoneNumber',
        'PhoneExts',
        'OpenAt',
        'CloseAt',
        'State',
        'Ordering',
        'CreatedDate',
        'ExcludeReport',
        'ORCRefCode',
        'ORCExtraTime',
        'Old_Address',
        'GoogleMapIFrame',
        'ImageCDN',
        'WorkingTime',
        'LatestUpdated',
        'Priority',
        'GrandOpening',
        'IsFilterReport',
        'GoogleRatingURL'
    ];
    public $timestamps = false;
}
