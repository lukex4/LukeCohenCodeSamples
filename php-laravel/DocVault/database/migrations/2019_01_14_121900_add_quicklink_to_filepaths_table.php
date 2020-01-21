<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
*
* Add 'is_quicklink' boolean column to filepaths table
*
*/

class AddQuicklinkToFilepathsTable extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {
        Schema::table('filepaths', function (Blueprint $table) {
            $table->boolean('is_quicklink');
        });
    }

    /**
    * Reverse the migrations.
    *
    * @return void
    */
    public function down()
    {
        Schema::table('filepaths', function (Blueprint $table) {
            $table->dropColumn('is_quicklink');
        });
    }
}

?>
