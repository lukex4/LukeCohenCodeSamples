<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Microfile extends Model
{

    protected $table = 'microfiles';

    protected $fillable = [
        'filebase'
    ];

    protected $hidden = [

    ];

}
