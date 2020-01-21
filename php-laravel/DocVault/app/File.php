<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class File extends Model
{

    protected $table = 'storedfiles';

    protected $fillable = [
        'docvaultkey',
        'mimetype',
        'filename',
        'filesize',
        'userid',
        'tags',
        'checksum',
        'deleted',
        'microfile_id',
        'to_autotag'
    ];

    protected $hidden = [

    ];

}
