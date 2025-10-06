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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('contact');
            $table->string('profile_image')->nullable();
            $table->string('address');
            $table->string('college');
            // New fields added for student profile
            $table->string('gender')->nullable();
            $table->date('dob')->nullable();
            $table->string('enrollment_status')->default('full_time');
            $table->string('course')->nullable();
            $table->boolean('agreed_to_terms')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
