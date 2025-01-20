<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('merchant_id')->constrained()->onDelete('cascade');;
            $table->uuid('location_id')->constrained()->onDelete('cascade');;
            $table->string('name', 255)->nullable();
            $table->string('email', 255);
            $table->string('phone', 20)->nullable();
            $table->string('phone_country_code', 5)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('company', 255)->nullable();
            $table->string('reference', 255)->nullable();
            $table->timestamps();

            // Adding indexes
            $table->index('name');
            $table->index('email');
            $table->index('phone');

            // Adding a composite unique index
            $table->unique(['merchant_id', 'location_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customers');
    }
};
