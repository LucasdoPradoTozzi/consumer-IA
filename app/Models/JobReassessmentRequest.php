<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobReassessmentRequest extends Model
{
    protected $fillable = [
        'job_application_id',
        'request_message',
        'status',
        'result',
        'processed_at',
    ];

    protected $casts = [
        'result' => 'array',
        'processed_at' => 'datetime',
    ];

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }
}
