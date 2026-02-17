<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('job_application_versions', function (Blueprint $table) {
            $table->json('resume_config')->nullable()->after('resume_data');
        });
    }

    public function down(): void
    {
        Schema::table('job_application_versions', function (Blueprint $table) {
            $table->dropColumn('resume_config');
        });
    }
};
