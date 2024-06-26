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
        Schema::create('blood_bags', function (Blueprint $table) {
            $table->id('blood_bags_id');
            $table->foreignId('user_id');
            $table->string('serial_no');    
            $table->date('date_donated');   
            $table->date('expiration_date');  
            $table->string('venue');     
            $table->string('bled_by');  
            $table->smallInteger('isStored')->default(0);     
            $table->smallInteger('isUsed')->default(0);     
            $table->smallInteger('isExpired')->default(0);  
            $table->smallInteger('isDisposed')->default(0);  
            $table->smallInteger('status')->default(0);     
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blood_bag');
    }
};
