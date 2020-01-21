<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
*
* This table holds the relationships between Files and Paths (pseudo-folders)
*
*/

class CreateFilePathsTable extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {
        Schema::create('filepaths', function (Blueprint $table) {
            $table->increments('id');
            $table->string('fullpath');
            $table->string('dvkey');
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
        Schema::drop('filepaths');
    }
}

?>
