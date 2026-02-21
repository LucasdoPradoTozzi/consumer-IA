<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class LlmModel extends Model
{
    protected $fillable = [
        'llm_provider_id',
        'name',
        'capability',
        'ranking',
        'is_active',
        'quota_per_minute',
        'quota_per_day',
        'tokens_per_minute',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'ranking' => 'integer',
        'quota_per_minute' => 'integer',
        'quota_per_day' => 'integer',
        'tokens_per_minute' => 'integer',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'llm_provider_id');
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(LlmUsageLog::class);
    }

    /**
     * Count how many calls were made in the last 60 seconds.
     */
    public function getUsageLastMinute(): int
    {
        return $this->usageLogs()
            ->where('called_at', '>=', Carbon::now()->subMinute())
            ->count();
    }

    /**
     * Count how many calls were made since midnight today.
     */
    public function getUsageToday(): int
    {
        return $this->usageLogs()
            ->where('called_at', '>=', Carbon::today())
            ->count();
    }

    /**
     * Check if this model is within all its configured quotas.
     */
    public function isWithinQuota(): bool
    {
        // Check per-minute quota
        if ($this->quota_per_minute !== null) {
            if ($this->getUsageLastMinute() >= $this->quota_per_minute) {
                return false;
            }
        }

        // Check per-day quota
        if ($this->quota_per_day !== null) {
            if ($this->getUsageToday() >= $this->quota_per_day) {
                return false;
            }
        }

        return true;
    }
}
