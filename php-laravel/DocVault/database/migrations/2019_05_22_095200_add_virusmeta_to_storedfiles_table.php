<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
*
* Add 'viruschecked' to Files master table
*
*/

class AddVirusMetaToStoredFilesTable extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {

        if (Schema::hasColumn('storedfiles', 'viruscheck_timestamp')) {
            Schema::table('storedfiles', function (Blueprint $table) {
                $table->dropColumn('viruscheck_timestamp');
            });
        }

        if (Schema::hasColumn('storedfiles', 'viruscheck_clean')) {
            Schema::table('storedfiles', function (Blueprint $table) {
                $table->dropColumn('viruscheck_clean');
            });
        }

        Schema::table('storedfiles', function (Blueprint $table) {
            $table->boolean('viruscheck_clean')->default(0);
            $table->timestamp('viruscheck_timestamp');
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
            $table->dropColumn('viruscheck_timestamp');
            $table->dropColumn('viruscheck_clean');
        });
    }
}

?>
