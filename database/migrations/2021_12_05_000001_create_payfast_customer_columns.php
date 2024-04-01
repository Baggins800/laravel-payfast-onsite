<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayfastCustomerColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(config('payfast.tables.users'), function (Blueprint $table) {
            $table->string('credit_card_token')->nullable()->index();
            $table->timestamp('trial_ends_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(config('payfast.tables.users'), function (Blueprint $table) {
            $table->dropColumn('credit_card_token');
            $table->dropColumn('trial_ends_at');
        });
    }
}
