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
        Schema::create('job_application_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained()->onDelete('cascade');
            $table->integer('version_number')->default(1);
            $table->text('cover_letter')->nullable();
            $table->string('email_subject')->nullable();
            $table->text('email_body')->nullable();
            $table->json('resume_data')->nullable();
            $table->json('resume_config')->nullable();
            $table->string('resume_path')->nullable();
            $table->boolean('email_sent')->default(false);
            $table->boolean('completed')->default(false);
            $table->timestamps();
            $table->unique(['job_application_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_application_versions');
    }
};
