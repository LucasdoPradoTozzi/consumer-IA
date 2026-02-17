<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobDeduplication extends Model
{
    protected $table = 'job_deduplication';

    protected $fillable = [
        'hash',
        'source',
        'original_link',
        'original_content',
        'job_application_id',
        'first_seen_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
    ];

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }
}
