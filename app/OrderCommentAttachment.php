<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderCommentAttachment extends Model
{
    //Define table name
    protected $table = 'OrderCommentAttachment';

    //Define primary key
    protected  $primaryKey = 'AttachmentId';

    //Define filter column
    protected $fillable = [
        'AttachmentId',
        'OrderCommentId',
        'FileUrl',
        'FileType',
        'UploadedAt'
    ];
    public $timestamps = false;

}