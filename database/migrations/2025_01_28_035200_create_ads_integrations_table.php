<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdsIntegrationsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ads_integrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('merchant_id')->constrained('merchants')->onDelete('cascade');
            $table->uuid('location_id')->constrained('locations')->onDelete('cascade');
            $table->string('type');
            $table->string('access_token')->nullable();
            $table->integer('expires_in')->nullable();
            $table->string('refresh_token')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('mcc_id')->nullable();
            $table->string('status')->nullable();
            $table->string('gbp_linking_status')->default('pending');
            $table->string('gbp_admin_invitation_status')->default('pending');
            $table->string('ads_account_creation_status')->default('pending');
            $table->string('ads_account_conversion_status')->default('pending');
            $table->string('ads_account_billing_status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ads_integrations');
    }
}
