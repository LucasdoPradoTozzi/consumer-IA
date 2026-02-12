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
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->unique();
            $table->string('type')->default('job_application');

            // Status: pending, processing, classified, scored, rejected, completed, failed
            $table->string('status')->default('pending')->index();

            // Job data
            $table->string('job_title')->nullable();
            $table->string('job_company')->nullable();
            $table->text('job_description')->nullable();
            $table->json('job_skills')->nullable();
            $table->json('job_data')->nullable(); // Full job data

            // Candidate data
            $table->string('candidate_name')->nullable();
            $table->string('candidate_email')->nullable();
            $table->json('candidate_data')->nullable(); // Full candidate data

            // Processing results
            $table->boolean('is_relevant')->nullable();
            $table->text('classification_reason')->nullable();
            $table->integer('match_score')->nullable();
            $table->text('score_justification')->nullable();

            // Generated content
            $table->text('cover_letter')->nullable();
            $table->text('adjusted_resume')->nullable();
            $table->json('resume_changes')->nullable();

            // File paths
            $table->string('cover_letter_pdf_path')->nullable();
            $table->string('resume_pdf_path')->nullable();

            // Email
            $table->boolean('email_sent')->default(false);
            $table->timestamp('email_sent_at')->nullable();

            // Error handling
            $table->text('error_message')->nullable();
            $table->text('error_trace')->nullable();

            // Processing timestamps
            $table->timestamp('started_at')->nullable();
            $table->timestamp('classified_at')->nullable();
            $table->timestamp('scored_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            // Metadata
            $table->json('metadata')->nullable();
            $table->integer('processing_time_seconds')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
