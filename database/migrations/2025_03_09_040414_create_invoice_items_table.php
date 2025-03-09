<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceItemsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->uuid('merchant_id')->constrained('merchants')->onDelete('cascade');
            $table->uuid('location_id')->constrained('locations')->onDelete('cascade');
            $table->uuid('item_id')->constrained('items')->onDelete('cascade');
            $table->uuid('item_variation_id')->constrained('item_variations')->onDelete('cascade');
            $table->string('item_name')->nullable();
            $table->string('item_variation_name')->nullable();
            $table->decimal('item_price', 10, 2)->default(0);
            $table->integer('quantity')->default(0);
            $table->decimal('item_tax_rate', 5, 2)->default(0);
            $table->decimal('item_tax_amount', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
}
