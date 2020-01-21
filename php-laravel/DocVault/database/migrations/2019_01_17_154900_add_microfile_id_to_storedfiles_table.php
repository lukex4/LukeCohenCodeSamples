<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
*
* Add 'microfile_id' and 'bytesize' int columns to Files master table
*
*/

class AddMicrofileIdToStoredFilesTable extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {

        if (Schema::hasColumn('storedfiles', 'microfile_id')) {
            Schema::table('storedfiles', function (Blueprint $table) {
                $table->dropColumn('microfile_id');
            });
        }

        Schema::table('storedfiles', function (Blueprint $table) {
            $table->integer('microfile_id');
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
