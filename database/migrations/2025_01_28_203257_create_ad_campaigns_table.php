<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdCampaignsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('merchant_id')->foreignId('merchants')->constrained('merchants');
            $table->uuid('location_id')->foreignId('locations')->constrained('locations');
            $table->uuid('business_profile_id')->foreignId('business_profiles')->constrained('business_profiles')->nullable();
            $table->string('external_id')->nullable();
            $table->string('name');
            $table->decimal('budget', 10, 2);
            $table->enum('status', ['draft', 'learning', 'active', 'paused', 'completed'])->default('draft');
            $table->string('processing_status')->default('pending');
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->string('stripe_checkout_session_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->enum('type', ['smart', 'pmax', 'search', 'display', 'video'])->default('search');
            $table->enum('goal', ['awareness', 'consideration', 'conversion'])->default('awareness');
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip_code')->nullable();
            $table->integer('radius')->default(2);
            $table->string('headline1')->nullable();
            $table->string('headline2')->nullable();
            $table->string('headline3')->nullable();
            $table->string('description1')->nullable();
            $table->string('description2')->nullable();
            $table->string('landing_page_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_campaigns');
    }
}
