<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EventLog extends Model
{

    protected $table = 'eventlog';

    protected $fillable = [
        'event_description'
    ];

    protected $hidden = [

    ];

}
