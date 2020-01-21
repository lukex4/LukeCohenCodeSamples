<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
*
* Add 'is_quicklink' boolean column to paths table
*
*/

class AddQuicklinkToPathsTable extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {
        Schema::table('paths', function (Blueprint $table) {
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
        Schema::table('paths', function (Blueprint $table) {
            $table->dropColumn('is_quicklink');
        });
    }
}

?>
