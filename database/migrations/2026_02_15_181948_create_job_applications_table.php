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
            $table->json('raw_message');
            $table->json('job_data'); // Original raw payload from the queue

            $table->string('status')->default('pending');

            // --- Extracted job information (from AnalyzeWorker step 1) ---
            $table->string('extracted_title')->nullable();
            $table->string('extracted_company')->nullable();
            $table->text('extracted_description')->nullable();
            $table->json('required_skills')->nullable();       // array of skill strings
            $table->string('extracted_location')->nullable();
            $table->string('extracted_salary')->nullable();
            $table->string('employment_type')->nullable();
            $table->string('language')->nullable();            // "portuguese" | "english"
            $table->json('company_data')->nullable();          // industry, size, hq, website, reputation_summary
            $table->json('extra_information')->nullable();     // catch-all for any other extracted data without a dedicated column

            // --- Scoring (from AnalyzeWorker step 2) ---
            $table->integer('match_score')->nullable();
            $table->json('scoring_data')->nullable();          // matched_skills, missing_skills, strengths, gaps, justification

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
