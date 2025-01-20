<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('type');
            $table->uuid('merchant_id')->constrained()->onDelete('cascade');
            $table->uuid('location_id')->constrained()->onDelete('cascade');
            $table->double('tax_rate')->default(0);
            $table->string('color')->nullable();
            $table->timestamps();

            $table->index('merchant_id');
            $table->index('location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
