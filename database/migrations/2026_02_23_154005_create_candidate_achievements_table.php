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
        Schema::create('candidate_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_experience_id')->constrained('candidate_experiences')->cascadeOnDelete();
            $table->text('achievement');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_achievements');
    }
};
