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
        Schema::create('accounting_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->constrained('transactions');
            $table->foreignUuid('merchant_id')->constrained('merchants');
            $table->foreignUuid('location_id')->constrained('locations');
            $table->foreignUuid('account_id')->constrained('accounts');
            $table->decimal('debit', 20, 6);
            $table->decimal('credit', 20, 6);
            $table->date('entry_date');
            $table->timestamps();

            $table->index('transaction_id');
            $table->index('merchant_id');
            $table->index('location_id');
            $table->index('account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_entries');
    }
};
