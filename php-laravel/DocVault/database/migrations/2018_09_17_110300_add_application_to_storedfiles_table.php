<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
*
* Add 'inpath' string column to storedfiles table
*
*/

class AddApplicationToStoredfilesTable extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {
        Schema::table('storedfiles', function (Blueprint $table) {
            $table->string('application');
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
            $table->dropColumn('application');
        });
    }
}

?>
