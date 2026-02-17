<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobScoring extends Model
{
    protected $fillable = [
        'job_application_id',
        'extraction_version_id',
        'scoring_score',
        'scoring_data',
    ];

    protected $casts = [
        'scoring_data' => 'array',
    ];

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    public function versions()
    {
        return $this->hasMany(JobApplicationVersion::class, 'scoring_id');
    }

    public function extraction(): BelongsTo
    {
        return $this->belongsTo(JobExtraction::class, 'extraction_version_id');
    }
}
