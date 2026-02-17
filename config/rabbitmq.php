<?php

return [

    /*
    |--------------------------------------------------------------------------
    | RabbitMQ Connection Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for RabbitMQ connection. All values are loaded from .env
    | and should be accessed via config('rabbitmq.key') in services.
    |
    */

    'host' => env('RABBITMQ_HOST', 'localhost'),

    'port' => env('RABBITMQ_PORT', 5672),

    'user' => env('RABBITMQ_USER', 'guest'),

    'password' => env('RABBITMQ_PASSWORD', 'guest'),

    'vhost' => env('RABBITMQ_VHOST', '/'),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queues consumed by the RabbitMQ consumer (worker:consume):
    | - deduplication: Intake de novas vagas → salva JobApplication + JobExtraction
    | - mark-job-done: Marca vaga como já aplicada (atualiza status)
    | - reproccess-job: Reprocessa vaga com feedback adicional
    |
    | As etapas de extraction OCR, scoring, generation e email são processadas
    | por batch commands via cron/scheduler (não por filas):
    | - app:extract-pending-applications
    | - app:score-pending-extractions
    | - app:generate-pending-applications
    | - app:send-pending-application-emails
    |
    */

    'queues' => [
        'deduplication' => env('RABBITMQ_QUEUE_PROCESS', 'process-jobs'),
        'mark-job-done' => env('RABBITMQ_QUEUE_MARK_DONE', 'mark-job-done'),
        'reproccess-job' => env('RABBITMQ_QUEUE_REPROCESS', 'reproccess-job'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Consumer Configuration
    |--------------------------------------------------------------------------
    */

    'prefetch_count' => 1,

    'consumer_tag' => 'laravel_consumer',

    /*
    |--------------------------------------------------------------------------
    | Connection Options
    |--------------------------------------------------------------------------
    */

    'connection_timeout' => 3.0,

    'read_write_timeout' => 3.0,

    'heartbeat' => 60,

    'keepalive' => true,

];
