<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobExtraction extends Model
{
    protected $fillable = [
        'job_application_id',
        'version_number',
        'extra_information',
        'extraction_data',
    ];

    protected $casts = [
        'extraction_data' => 'array',
    ];

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    public function scorings()
    {
        return $this->hasMany(JobScoring::class, 'extraction_version_id');
    }
}
