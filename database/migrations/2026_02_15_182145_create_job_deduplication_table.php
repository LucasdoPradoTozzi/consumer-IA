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
        Schema::create('job_deduplication', function (Blueprint $table) {
            $table->id();
            $table->string('hash')->unique();
            $table->string('source')->default('link');
            $table->string('original_link')->nullable();
            $table->text('original_content')->nullable();
            $table->foreignId('job_application_id')->constrained()->onDelete('cascade');
            $table->timestamp('first_seen_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_deduplication');
    }
};
