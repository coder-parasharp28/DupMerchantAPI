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
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained('merchants');
            $table->foreignUuid('location_id')->constrained('locations');
            $table->foreignUuid('customer_id')->constrained('customers');
            $table->string('payment_intent_id')->nullable();
            $table->enum('payment_type', ['cash', 'card_present', 'manual', 'online', 'other']);
            $table->string('card_type')->nullable();
            $table->string('card_last_four')->nullable();
            $table->decimal('total_amount', 20, 6)->default(0);
            $table->decimal('tax_amount', 20, 6)->default(0);
            $table->decimal('stripe_fee', 20, 6)->default(0);
            $table->decimal('stripe_real_fee', 20, 6)->default(0);
            $table->decimal('platform_fee', 20, 6)->default(0);
            $table->decimal('tip_amount', 20, 6)->default(0);
            $table->decimal('net_amount', 20, 6)->default(0);
            $table->enum('status', ['initiated', 'pending', 'completed', 'cancelled'])->default('initiated');
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
        Schema::dropIfExists('transactions');
    }
};
