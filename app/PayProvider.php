<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PayProvider extends Model
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
    protected $table = 'PayProvider';

    /**
     * Primary key
     */
    protected $primaryKey = 'ProviderId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ProviderId',
        'ProviderCode',
        'ProviderName',
        'State',
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

    public $timestamps = false;

    public function posTerminals()
    {
        return $this->hasMany(PayPosTerminal::class, 'PayProviderId', 'ProviderId')->where('PayPosTerminal.Status', 1);
    }
}
