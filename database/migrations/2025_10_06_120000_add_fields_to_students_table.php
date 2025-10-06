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
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'gender')) {
                $table->string('gender')->nullable();
            }

            if (!Schema::hasColumn('students', 'dob')) {
                $table->date('dob')->nullable();
            }

            if (!Schema::hasColumn('students', 'enrollment_status')) {
                $table->string('enrollment_status')->default('full_time');
            }

            if (!Schema::hasColumn('students', 'course')) {
                $table->string('course')->nullable();
            }

            if (!Schema::hasColumn('students', 'agreed_to_terms')) {
                $table->boolean('agreed_to_terms')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'agreed_to_terms')) {
                $table->dropColumn('agreed_to_terms');
            }

            if (Schema::hasColumn('students', 'course')) {
                $table->dropColumn('course');
            }

            if (Schema::hasColumn('students', 'enrollment_status')) {
                $table->dropColumn('enrollment_status');
            }

            if (Schema::hasColumn('students', 'dob')) {
                $table->dropColumn('dob');
            }

            if (Schema::hasColumn('students', 'gender')) {
                $table->dropColumn('gender');
            }
        });
    }
};
