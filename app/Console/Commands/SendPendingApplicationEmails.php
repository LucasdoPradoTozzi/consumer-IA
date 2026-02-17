<?php

namespace App\Console\Commands;

use App\Models\JobApplicationVersion;
use App\Services\Workers\EmailWorker;
use Illuminate\Console\Command;

class SendPendingApplicationEmails extends Command
{
    protected $signature = 'app:send-pending-application-emails {--limit=1}';
    protected $description = 'Send emails for completed JobApplicationVersions that have not been sent yet.';

    public function handle()
    {
        $lock = cache()->lock('send-pending-application-emails-lock', 600);
        if (!$lock->get()) {
            $this->warn('Another instance is already running. Aborting.');
            return 0;
        }

        try {
            \Log::info('[SendPendingApplicationEmails] Iniciando script');
            $limit = (int) $this->option('limit');
            $this->info("[SendPendingApplicationEmails] Searching for completed JobApplicationVersions without email_sent...");

            $pending = JobApplicationVersion::where('completed', true)
                ->where('email_sent', false)
                ->whereNotNull('cover_letter')
                ->whereNotNull('resume_data')
                ->whereNotNull('resume_path')
                ->limit($limit)
                ->get();

            \Log::info('[SendPendingApplicationEmails] Found count', ['count' => $pending->count()]);

            if ($pending->isEmpty()) {
                $this->info('[SendPendingApplicationEmails] Nenhum encontrado para rodar.');
                return 0;
            }

            $emailWorker = app(EmailWorker::class);

            foreach ($pending as $version) {
                $jobApplication = $version->jobApplication;
                if (!$jobApplication) {
                    $this->error("[SendPendingApplicationEmails] JobApplication not found for version ID: {$version->id}");
                    continue;
                }

                $this->info("[SendPendingApplicationEmails] Sending email for version ID: {$version->id}");
                \Log::info('[SendPendingApplicationEmails] Sending email', ['version_id' => $version->id]);

                try {
                    $emailWorker->processVersion($jobApplication, $version);
                    $this->info("[SendPendingApplicationEmails] Email sent for version ID: {$version->id}");
                    \Log::info('[SendPendingApplicationEmails] Email sent', ['version_id' => $version->id]);
                } catch (\Exception $e) {
                    $this->error("[SendPendingApplicationEmails] Failed to send email for version ID: {$version->id}: {$e->getMessage()}");
                    \Log::error('[SendPendingApplicationEmails] Failed to send email', [
                        'version_id' => $version->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            $lock->release();
        }

        \Log::info('[SendPendingApplicationEmails] Script finalizado');
        return 0;
    }
}

