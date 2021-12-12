<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddScopeNikolagDeductibleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nikolag_deductibles', function (Blueprint $table) {
            $table->string('scope', '60')->nullable();
        });

        // Make it non nullable due to SQLite issue
        // (https://laracasts.com/discuss/channels/general-discussion/migrations-sqlite-general-error-1-cannot-add-a-not-null-column-with-default-value-null)
        Schema::table('nikolag_deductibles', function (Blueprint $table) {
            $table->string('scope', '60')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
