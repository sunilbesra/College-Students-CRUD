<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCsvJobsTable extends Migration
{
   public function up(): void
{
    Schema::create('csv_jobs', function (Blueprint $table) {
        $table->id();
        $table->string('file_name')->nullable();
        $table->string('row_identifier')->nullable(); // optional unique column
        $table->json('data'); // stores row data
        $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued');
        $table->text('error_message')->nullable();
        $table->timestamps();
    });
}


    public function down()
    {
        Schema::dropIfExists('csv_jobs');
    }
}