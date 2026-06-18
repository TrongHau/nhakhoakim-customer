<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ContactRelationship extends Model
{

	public $timestamps = false;
	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'AIHCustomerRelationship';
	protected $primaryKey = 'Id';
	/**
	 * Attributes that should be mass-assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'Id',
		'SourceCustomerId',
		'SourceRelationCode',
		'DestinationCustomerId',
		'DestinationRelationCode',
		'CreatedDate',
		'UpdatedDate',
		'Priority'
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
	protected $dates = ['CreatedDate'];

	protected static function boot()
	{
		parent::boot();

		static::creating(function ($query) {
			//$query->CreatedBy = $query->CreatedBy ?? Auth::id();
			$query->CreatedDate = $query->freshTimestamp();
		});
	}
}
