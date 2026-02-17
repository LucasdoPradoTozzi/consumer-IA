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
            $table->foreignId('scoring_id')->constrained('job_scorings')->onDelete('cascade');
            $table->integer('version_number');
            $table->text('cover_letter');
            $table->string('email_subject');
            $table->text('email_body');
            $table->json('resume_data');
            $table->string('resume_path');
            $table->boolean('email_sent')->default(false);
            $table->boolean('completed')->default(false);
            $table->timestamps();
            $table->unique(['scoring_id', 'version_number']);
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
