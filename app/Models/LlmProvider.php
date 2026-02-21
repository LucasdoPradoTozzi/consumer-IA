<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LlmProvider extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'is_active',
        'priority',
        'api_key_env_var',
        'service_class',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function models(): HasMany
    {
        return $this->hasMany(LlmModel::class);
    }

    /**
     * Get the API key value from .env at runtime.
     * Returns null if no env var is configured.
     */
    public function getApiKey(): ?string
    {
        if (empty($this->api_key_env_var)) {
            return null;
        }

        return env($this->api_key_env_var);
    }

    /**
     * Resolve the service class instance via Laravel DI.
     */
    public function resolveService(): object
    {
        return app($this->service_class);
    }
}
