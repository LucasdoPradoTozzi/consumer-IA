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
    */

    'queue' => env('RABBITMQ_QUEUE', 'jobs'),

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
