<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
*
* This is the Microfiles master table
*
*/

class CreateMicrofilesTable extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {

        if (Schema::hasTable('microfiles')) {
            Schema::drop('microfiles');
        }

        Schema::create('microfiles', function (Blueprint $table) {
            $table->increments('id');
            $table->mediumText('filebase');
        });
    }

    /**
    * Reverse the migrations.
    *
    * @return void
    */
    public function down()
    {
        Schema::drop('microfiles');
    }
}

?>
