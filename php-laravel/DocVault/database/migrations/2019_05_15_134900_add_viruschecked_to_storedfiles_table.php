<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
*
* Add 'viruschecked' to Files master table
*
*/

class AddVirusCheckedToStoredFilesTable extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {

        if (Schema::hasColumn('storedfiles', 'viruschecked')) {
            Schema::table('storedfiles', function (Blueprint $table) {
                $table->dropColumn('viruschecked');
            });
        }

        Schema::table('storedfiles', function (Blueprint $table) {
            $table->boolean('viruschecked')->default(0);
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
            $table->dropColumn('viruschecked');
        });
    }
}

?>
