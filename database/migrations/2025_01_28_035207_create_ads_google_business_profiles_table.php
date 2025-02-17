<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdsGoogleBusinessProfilesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ads_google_business_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('merchant_id')->constrained('merchants')->onDelete('cascade');
            $table->uuid('location_id')->constrained('locations')->onDelete('cascade');
            $table->uuid('ads_integration_id')->constrained('ads_integrations')->onDelete('cascade');
            $table->string('google_business_profile_id')->nullable();
            $table->string('name')->nullable();
            $table->json('google_business_profile_object')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ads_google_business_profiles');
    }
}
