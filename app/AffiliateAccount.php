<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AffiliateAccount extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql_sale';
    
    //Define table name
    protected $table = 'AffiliateAccount';

    //Define primary key
    protected $primaryKey = 'AffiliateAccountId';

    //Define filter columns in table
    protected $fillable = [
        'AffiliateAccountId',
        'FullName',
        'AffCode',
        'State',
        'CreatedDate',
        'CreatedBy',
        'UpdatedDate',
        'UpdatedBy',
        'LevelId'
    ];

    //Disable create_at and update_at automactic add query
    public $timestamps = false;

}
