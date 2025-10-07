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
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->enum('operation', ['create', 'update', 'delete'])->index();
            $table->string('student_id')->nullable()->index();
            $table->json('data'); // Store student data as JSON
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued')->index();
            $table->text('error_message')->nullable();
            $table->enum('source', ['form', 'api', 'csv'])->default('form')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index(); // For future user authentication
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index(['status', 'operation']);
            $table->index(['source', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
