<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('llm_provider_id')->constrained('llm_providers')->onDelete('cascade');
            $table->string('name');
            $table->string('capability'); // text, image, multimodal, open-weight
            $table->integer('ranking')->default(1);
            $table->boolean('is_active')->default(true);
            $table->integer('quota_per_minute')->nullable();
            $table->integer('quota_per_day')->nullable();
            $table->integer('tokens_per_minute')->nullable();
            $table->timestamps();

            $table->index(['capability', 'is_active', 'ranking']);
            $table->unique(['llm_provider_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_models');
    }
};
