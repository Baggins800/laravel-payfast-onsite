<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('payfast.tables.customers'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('billable_id');
            $table->string('billable_type');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();

            $table->index(['billable_id', 'billable_type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(config('payfast.tables.customers'));
    }
}
