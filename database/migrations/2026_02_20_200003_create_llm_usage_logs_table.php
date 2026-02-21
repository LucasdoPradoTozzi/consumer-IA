<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('llm_model_id')->constrained('llm_models')->onDelete('cascade');
            $table->string('capability');
            $table->integer('prompt_tokens')->nullable();
            $table->integer('response_tokens')->nullable();
            $table->integer('response_time_ms')->default(0);
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('called_at')->useCurrent();
            $table->timestamps();

            $table->index('called_at');
            $table->index(['llm_model_id', 'called_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_usage_logs');
    }
};
