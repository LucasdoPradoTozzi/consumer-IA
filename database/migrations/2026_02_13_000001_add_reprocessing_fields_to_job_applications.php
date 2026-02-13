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
        Schema::table('job_applications', function (Blueprint $table) {
            $table->text('reprocessing_message')->nullable()->after('error_trace');
            $table->timestamp('reprocessed_at')->nullable()->after('failed_at');
            $table->timestamp('generated_at')->nullable()->after('scored_at');
            $table->timestamp('pdf_generated_at')->nullable()->after('generated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn([
                'reprocessing_message',
                'reprocessed_at',
                'generated_at',
                'pdf_generated_at',
            ]);
        });
    }
};
