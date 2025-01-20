<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMerchantMembersTable extends Migration
{
    public function up()
    {
        Schema::create('merchant_members', function (Blueprint $table) {
            $table->id();
            $table->uuid('merchant_id')->constrained()->onDelete('cascade');
            $table->uuid('user_id'); // Assuming user_id is a string, adjust as necessary
            $table->string('role'); // e.g., 'owner', 'manager'
            $table->boolean('is_activated')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('merchant_members');
    }
}
