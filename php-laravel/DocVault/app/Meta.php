<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Meta extends Model
{

    protected $table = 'meta';

    protected $fillable = [
        'dvkey',
        'metakey',
        'metadata'
    ];

    protected $hidden = [

    ];

}
