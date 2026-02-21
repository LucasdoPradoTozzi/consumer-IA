<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmUsageLog extends Model
{
    protected $fillable = [
        'llm_model_id',
        'capability',
        'prompt_tokens',
        'response_tokens',
        'response_time_ms',
        'success',
        'error_message',
        'metadata',
        'called_at',
    ];

    protected $casts = [
        'success' => 'boolean',
        'prompt_tokens' => 'integer',
        'response_tokens' => 'integer',
        'response_time_ms' => 'integer',
        'metadata' => 'array',
        'called_at' => 'datetime',
    ];

    public function model(): BelongsTo
    {
        return $this->belongsTo(LlmModel::class, 'llm_model_id');
    }
}
