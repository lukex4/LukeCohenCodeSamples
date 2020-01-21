<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Path extends Model
{

    protected $table = 'paths';

    protected $fillable = [
        'foldername',
        'fullpath',
        'userid',
        'description',
        'tags',
        'is_quicklink'
    ];

    protected $hidden = [

    ];

}
