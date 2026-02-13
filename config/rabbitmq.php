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
    | Multiple queues for different processing tasks:
    | - process-jobs: Full job application processing (7-stage pipeline)
    | - mark-job-done: Mark job as already applied (just update status)
    | - reproccess-job: Reprocess job with additional message/feedback
    |
    */

    'queues' => [
        'process-jobs' => env('RABBITMQ_QUEUE_PROCESS', 'process-jobs'),
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
