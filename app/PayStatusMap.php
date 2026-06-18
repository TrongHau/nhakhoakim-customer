<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PayStatusMap extends Model
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
    protected $table = 'PayStatusMap';

    /**
     * Primary key
     */
    protected $primaryKey = 'StatusMapId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'StatusMapId',
        'ProviderId',
        'ProviderStatus',
        'InternalStatus',
        'IsFinal',
        'Note',
        'CreatedDate',
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
}
