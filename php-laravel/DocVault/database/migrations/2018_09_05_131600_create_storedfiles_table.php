<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
*
* This is the master table for all DocVault file references
*
*/

class CreateStoredFilesTable extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {
        Schema::create('storedfiles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('docvaultkey');
            $table->string('filename');
            $table->string('mimetype');
            $table->string('ingress_status');
            $table->string('tags');
            $table->integer('userid');
            $table->dateTime('added_timestamp');
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
        Schema::drop('storedfiles');
    }
}

?>
