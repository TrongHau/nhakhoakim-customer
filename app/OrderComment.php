<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderComment extends Model
{
    //Define table name
    protected $table = 'OrderComment';

    //Define primary key
    protected  $primaryKey = 'OrderCommentId';

    //Define filter column
    protected $fillable = [
        'OrderCommentId',
        'ObjectType',
        'ObjectId',
        'ParentOrderCommentId',
        'Content',
        'CreatedBy',
        'CreatedDate',
        'Value'
    ];
    public $timestamps = false;

}