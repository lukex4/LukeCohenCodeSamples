<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
*
* Add 'microfile_id' and 'bytesize' int columns to Files master table
*
*/

class AddMicrofileFlagToStoredFilesTable extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {
        Schema::table('storedfiles', function (Blueprint $table) {
            $table->boolean('microfile');
        });
    }

    /**
    * Reverse the migrations.
    *
    * @return void
    */
    public function down()
    {
        Schema::table('storedfiles', function (Blueprint $table) {
            $table->dropColumn('microfile');
        });
    }
}

?>
