<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
*
* This is the Paths (pseudo-folders) master table
*
*/

class UpdatePathsTagsTable extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {
        Schema::table('paths', function (Blueprint $table) {
            $table->string('tags');
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
            $table->dropColumn('tags');
        });
    }
}

?>
