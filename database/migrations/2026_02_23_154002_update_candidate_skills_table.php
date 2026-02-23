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
        Schema::table('candidate_skills', function (Blueprint $table) {
            $table->dropColumn(['category', 'name']);
            $table->foreignId('skill_id')->after('candidate_profile_id')->constrained('skills')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidate_skills', function (Blueprint $table) {
            $table->dropForeign(['skill_id']);
            $table->dropColumn('skill_id');
            $table->string('category')->after('candidate_profile_id');
            $table->string('name')->after('category');
        });
    }
};
