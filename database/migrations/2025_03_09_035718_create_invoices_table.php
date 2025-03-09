<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('merchant_id')->constrained('merchants')->onDelete('cascade');
            $table->uuid('location_id')->constrained('locations')->onDelete('cascade');
            $table->uuid('customer_id')->constrained('customers')->onDelete('cascade');
            $table->date('invoice_date');
            $table->date('due_date');
            $table->text('payer_memo')->nullable();
            $table->text('internal_note')->nullable();
            $table->boolean('surcharging_enabled')->default(false);
            $table->decimal('surcharging_rate', 5, 2)->default(0);
            $table->string('status')->default('pending');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('surcharging_amount', 10, 2)->default(0);
            $table->string('transaction_id')->nullable();
            $table->string('template_id')->nullable();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
}
