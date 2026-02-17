<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplicationVersion extends Model
{
    protected $fillable = [
        'job_application_id',
        'scoring_id',
        'version_number',
        'cover_letter',
        'email_subject',
        'email_body',
        'resume_data',
        'resume_config',
        'resume_path',
        'email_sent',
        'completed',
    ];

    protected $casts = [
        'resume_data' => 'array',
        'resume_config' => 'array',
        'email_sent' => 'boolean',
        'completed' => 'boolean',
    ];

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    public function scoring(): BelongsTo
    {
        return $this->belongsTo(JobScoring::class, 'scoring_id');
    }
}
