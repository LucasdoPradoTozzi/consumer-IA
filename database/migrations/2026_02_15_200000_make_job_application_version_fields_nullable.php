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
        Schema::table('job_application_versions', function (Blueprint $table) {
            $table->text('cover_letter')->nullable()->change();
            $table->string('email_subject')->nullable()->change();
            $table->text('email_body')->nullable()->change();
            $table->json('resume_data')->nullable()->change();
            $table->string('resume_path')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_application_versions', function (Blueprint $table) {
            $table->text('cover_letter')->nullable(false)->change();
            $table->string('email_subject')->nullable(false)->change();
            $table->text('email_body')->nullable(false)->change();
            $table->json('resume_data')->nullable(false)->change();
            $table->string('resume_path')->nullable(false)->change();
        });
    }
};
