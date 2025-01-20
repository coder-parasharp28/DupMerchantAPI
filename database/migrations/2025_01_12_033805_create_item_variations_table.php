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
        Schema::create('item_variations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->uuid('item_id')->constrained()->onDelete('cascade');
            $table->string('unit');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3);
            $table->string('gstin')->nullable();
            $table->string('SKU')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->timestamps();

            $table->index('item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_variations');
    }
};
