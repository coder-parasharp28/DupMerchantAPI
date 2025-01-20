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
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->constrained('transactions');
            $table->foreignUuid('merchant_id')->constrained('merchants');
            $table->foreignUuid('location_id')->constrained('locations');
            $table->foreignUuid('item_id')->constrained('items');
            $table->foreignUuid('item_variation_id')->constrained('item_variations');
            $table->string('item_name');
            $table->string('item_variation_name');
            $table->decimal('item_price', 20, 6);
            $table->integer('quantity');
            $table->decimal('item_tax_rate', 5, 2)->nullable();
            $table->decimal('item_tax_amount', 20, 6)->default(0);
            $table->timestamps();

            $table->index('transaction_id');
            $table->index('merchant_id');
            $table->index('location_id');
            $table->index('item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};
