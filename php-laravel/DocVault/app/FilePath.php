<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FilePath extends Model
{

    protected $table = 'filepaths';

    protected $fillable = [
        'fullpath',
        'dvkey'
    ];

    protected $hidden = [

    ];

}
