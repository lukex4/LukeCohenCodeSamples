<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
*
* This table is the internal DocVault event log
*
*/

class CreateEventLogTable extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {
        Schema::create('eventlog', function (Blueprint $table) {
            $table->increments('id');
            $table->string('event');
            $table->dateTime('event_timestamp');
            $table->timestamps();
        });
    }

    /**
    * Reverse the migrations.
    *
    * @return void
    */
    public function down()
    {
        Schema::drop('eventlog');
    }
}

?>
