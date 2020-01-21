<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
*
* Add 'highest_version' string column to storedfiles table
*
*/

class AddHighestversionToStoredfilesTable extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {
        Schema::table('storedfiles', function (Blueprint $table) {
            $table->integer('highest_version');
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
            $table->dropColumn('highest_version');
        });
    }
}

?>
