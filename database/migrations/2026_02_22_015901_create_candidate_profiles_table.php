
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
        Schema::create('candidate_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('summary')->nullable();

            // Preferences
            $table->boolean('remote')->default(true);
            $table->boolean('hybrid')->default(true);
            $table->boolean('onsite')->default(false);
            $table->string('availability')->default('immediate');
            $table->boolean('willing_to_relocate')->default(false);

            // Links
            $table->string('github')->nullable();
            $table->string('linkedin')->nullable();

            $table->string('seniority')->default('pleno');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_profiles');
    }
};
