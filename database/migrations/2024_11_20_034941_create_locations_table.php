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
       Schema::create('locations', function (Blueprint $table) {
           $table->uuid('id')->primary();
           $table->uuid('merchant_id')->constrained()->onDelete('cascade');
           $table->string('name');
           $table->string('address_line_1');
           $table->string('address_line_2')->nullable();
           $table->string('city');
           $table->string('state');
           $table->string('country');
           $table->string('zipcode');
           $table->double('tax_rate')->default(0);
           $table->string('stripe_location_id')->nullable();
           $table->timestamps();

           $table->index('merchant_id');
       });
   }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
