<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMerchantsTable extends Migration
{
    public function up()
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->uuid('id')->primary(); 
            $table->string('name');
            $table->string('type');
            $table->string('category')->nullable();
            $table->string('mcc_id')->nullable();
            $table->string('brand_color')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('icon_url')->nullable();
            $table->string('ein')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('zipcode')->nullable();
            $table->string('business_email')->nullable();
            $table->string('verification_id')->nullable();
            $table->string('verification_document_id')->nullable();
            $table->string('verification_used')->nullable();
            $table->string('verification_status')->default('NOT_STARTED');
            $table->string('verification_document_status')->default('NOT_STARTED');
            $table->timestamp('verification_date')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('merchants');
    }
}
