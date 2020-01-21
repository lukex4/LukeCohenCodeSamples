<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
*
* Add 'microfile_id' and 'bytesize' int columns to Files master table
*
*/

class AddToAutotagToStoredFilesTable extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {

        if (Schema::hasColumn('storedfiles', 'to_autotag')) {
            Schema::table('storedfiles', function (Blueprint $table) {
                $table->dropColumn('to_autotag');
            });
        }

        Schema::table('storedfiles', function (Blueprint $table) {
            $table->boolean('to_autotag')->default(0);
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
            $table->dropColumn('microfile_id');
        });
    }
}

?>
