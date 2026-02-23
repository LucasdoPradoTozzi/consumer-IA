<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $header) {
            $header->id();
            $header->string('name')->unique();
            $header->timestamps();
        });

        Schema::create('language_levels', function (Blueprint $header) {
            $header->id();
            $header->string('name')->unique();
            $header->timestamps();
        });

        Schema::table('candidate_languages', function (Blueprint $table) {
            $table->foreignId('language_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('language_level_id')->nullable()->constrained('language_levels')->onDelete('cascade');
        });

        // Seed default levels
        DB::table('language_levels')->insert([
            ['name' => 'Básico', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Intermediário', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Avançado', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Fluente', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Nativo', 'created_at' => now(), 'updated_at' => now()],
        ]);
        
        // Seed default languages
        DB::table('languages')->insert([
            ['name' => 'Português', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Inglês', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Espanhol', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('candidate_languages', function (Blueprint $table) {
            $table->dropColumn(['name', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidate_languages', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('level')->nullable();
            $table->dropForeign(['language_id']);
            $table->dropForeign(['language_level_id']);
            $table->dropColumn(['language_id', 'language_level_id']);
        });

        Schema::dropIfExists('language_levels');
        Schema::dropIfExists('languages');
    }
};
