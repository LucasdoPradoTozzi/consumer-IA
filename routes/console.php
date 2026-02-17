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
 * 1. Extração OCR (se houver imagem) → app:extract-pending-applications
 * 2. Scoring (classificação de compatibilidade) → app:score-pending-extractions
 * 3. Geração (cover letter + currículo PDF) → app:generate-pending-applications
 * 4. Email (envio da candidatura) → app:send-pending-application-emails
 *
 * A fila RabbitMQ (worker:consume) cuida apenas do intake inicial (deduplicação + salvamento).
 * Os commands batch acima processam as etapas seguintes via scheduler.
 *
 * withoutOverlapping() garante que nunca há concorrência entre execuções.
 */

// 1. Extração OCR (imagens → texto via Ollama)
Schedule::command('app:extract-pending-applications')
    ->everyMinute()
    ->withoutOverlapping(15)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/extract-pending.log'));

// 2. Scoring (classificação de compatibilidade via Ollama)
Schedule::command('app:score-pending-extractions')
    ->everyMinute()
    ->withoutOverlapping(15)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/score-pending.log'));

// 3. Geração (cover letter + currículo PDF via Ollama)
Schedule::command('app:generate-pending-applications')
    ->everyMinute()
    ->withoutOverlapping(15)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/generate-pending.log'));

// 4. Envio de email
Schedule::command('app:send-pending-application-emails')
    ->everyMinute()
    ->withoutOverlapping(15)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/send-pending-emails.log'));

// Worker RabbitMQ (intake: deduplicação + salvamento no banco)
Schedule::command('worker:consume')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/worker-schedule.log'));

