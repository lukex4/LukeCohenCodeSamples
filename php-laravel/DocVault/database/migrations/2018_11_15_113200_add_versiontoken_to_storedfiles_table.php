<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
*
* Add 'deferred' string column to storedfiles table
*
*/

class AddVersiontokenToStoredfilesTable extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {
        Schema::table('storedfiles', function (Blueprint $table) {
            $table->integer('version_no');
            $table->string('next_token');
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
            $table->dropColumn('version_no');
            $table->dropColumn('next_token');
        });
    }
}

?>
