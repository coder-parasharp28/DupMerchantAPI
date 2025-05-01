<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEntitlementColumnsToLocationsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('entitlement_pie_payments_enabled')->default(true)->after('max_avg_order_value');
            $table->boolean('entitlement_pie_ads_enabled')->default(true)->after('entitlement_pie_payments_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('entitlement_pie_payments_enabled');
            $table->dropColumn('entitlement_pie_ads_enabled');
        });
    }
}
