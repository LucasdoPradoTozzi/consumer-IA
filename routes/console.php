<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/**
 * Pipeline batch sequencial:
 *
 * 1. Analyze (Extração OCR + Scoring) → app:analyze-pending-applications
 * 2. Geração (cover letter + currículo PDF) → app:generate-pending-applications
 * 3. Email (envio da candidatura) → app:send-pending-application-emails
 *
 * A fila RabbitMQ (worker:consume) cuida apenas do intake inicial (deduplicação + salvamento).
 * Os commands batch acima processam as etapas seguintes via scheduler.
 *
 * withoutOverlapping() garante que nunca há concorrência entre execuções.
 */

Schedule::command('app:analyze-pending-applications')
    ->everyMinute()
    ->withoutOverlapping(15)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/analyze-pending.log'));

Schedule::command('app:generate-pending-applications')
    ->everyMinute()
    ->withoutOverlapping(15)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/generate-pending.log'));

Schedule::command('app:send-pending-application-emails')
    ->everyMinute()
    ->withoutOverlapping(15)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/send-pending-emails.log'));

Schedule::command('worker:consume')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/worker-schedule.log'));

