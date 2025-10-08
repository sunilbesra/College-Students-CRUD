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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // Type of notification (success, error, warning, info)
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional data for the notification
            $table->string('icon')->nullable(); // Icon class for display
            $table->string('color')->default('blue'); // Color theme
            $table->boolean('is_read')->default(false)->index();
            $table->boolean('is_important')->default(false)->index();
            $table->string('action_url')->nullable(); // Optional action link
            $table->string('action_text')->nullable(); // Text for action button
            $table->string('related_type')->nullable(); // Related model type (FormSubmission, etc.)
            $table->string('related_id')->nullable(); // Related model ID
            $table->timestamp('expires_at')->nullable(); // Auto-expire notifications
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['created_at', 'is_read']);
            $table->index(['type', 'is_read']);
            $table->index(['related_type', 'related_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
