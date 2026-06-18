<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $UnitId
 * @property string      $Code
 * @property string      $Name
 * @property string|null $NameUnsign
 * @property int|null    $IsBaseUnit
 * @property int         $Status
 * @property int|null    $Priority
 * @property int         $CreatedBy
 * @property \DateTime   $CreatedDate
 * @property int|null    $UpdatedBy
 * @property \DateTime|null $UpdatedDate
 */
class UnitInventory extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'Unit';

    protected $primaryKey = 'UnitId';

    public $timestamps = false;

    protected $fillable = [
        'Code',
        'Name',
        'NameUnsign',
        'IsBaseUnit',
        'Status',
        'Priority',
    ];

    protected $hidden = [];

    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class, 'UnitId', 'UnitId');
    }
}
