<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
*
* Add 'microfile_id' and 'bytesize' int columns to Files master table
*
*/

class RemoveMicrofileFromStoredFilesTable extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {

        if (Schema::hasColumn('storedfiles', 'filebase')) {
            Schema::table('storedfiles', function (Blueprint $table) {
                $table->dropColumn('filebase');
            });
        }

    }

    /**
    * Reverse the migrations.
    *
    * @return void
    */
    public function down()
    {
        
    }
}

?>
