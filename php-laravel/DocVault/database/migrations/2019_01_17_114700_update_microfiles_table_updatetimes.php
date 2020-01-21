<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
*
* This is the Microfiles master table
*
*/

class UpdateMicrofilesTableUpdateTimes extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {
        Schema::table('microfiles', function (Blueprint $table) {
            $table->timestamps();
        });
    }
}

?>
