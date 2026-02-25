<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    protected $fillable = [
        'raw_message',
        'job_data',
        'status',
        // Extracted fields
        'extracted_title',
        'extracted_company',
        'extracted_description',
        'required_skills',
        'extracted_location',
        'extracted_salary',
        'employment_type',
        'language',
        'company_data',
        'extra_information',
        // Scoring fields
        'match_score',
        'scoring_data',
    ];

    protected $casts = [
        'raw_message'      => 'array',
        'job_data'         => 'array',
        'required_skills'  => 'array',
        'company_data'     => 'array',
        'extra_information'=> 'array',
        'scoring_data'     => 'array',
    ];

    // Status constants
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_CLASSIFIED = 'classified';
    public const STATUS_SCORED     = 'scored';
    public const STATUS_GENERATED  = 'generated';
    public const STATUS_PDF_READY  = 'pdf_ready';
    public const STATUS_REJECTED   = 'rejected';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeHighScore($query, $threshold = 70)
    {
        return $query->where('match_score', '>=', $threshold);
    }

    /**
     * Accessors
     */
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING    => 'secondary',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_CLASSIFIED => 'primary',
            self::STATUS_SCORED     => 'warning',
            self::STATUS_REJECTED   => 'dark',
            self::STATUS_COMPLETED  => 'success',
            self::STATUS_FAILED     => 'danger',
            default                 => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING    => 'Pendente',
            self::STATUS_PROCESSING => 'Processando',
            self::STATUS_CLASSIFIED => 'Classificado',
            self::STATUS_SCORED     => 'Pontuado',
            self::STATUS_REJECTED   => 'Rejeitado',
            self::STATUS_COMPLETED  => 'Concluído',
            self::STATUS_FAILED     => 'Falhou',
            default                 => ucfirst($this->status),
        };
    }

    public function getScoreBadgeAttribute(): string
    {
        if (!$this->match_score) {
            return 'secondary';
        }

        return match (true) {
            $this->match_score >= 80 => 'success',
            $this->match_score >= 60 => 'warning',
            default                  => 'danger',
        };
    }

    // Accessors for original job_data fields (raw payload from queue)
    public function getJobTitleAttribute()
    {
        return $this->extracted_title ?? $this->job_data['title'] ?? null;
    }

    public function getJobCompanyAttribute()
    {
        return $this->extracted_company ?? $this->job_data['company'] ?? null;
    }

    public function getJobDescriptionAttribute()
    {
        return $this->extracted_description ?? $this->job_data['description'] ?? null;
    }

    public function getJobSkillsAttribute()
    {
        return $this->required_skills ?? $this->job_data['skills'] ?? [];
    }

    public function getCandidateNameAttribute()
    {
        return $this->job_data['candidate_name'] ?? null;
    }

    public function getCandidateEmailAttribute()
    {
        return $this->job_data['candidate_email'] ?? null;
    }

    public function getCandidateDataAttribute()
    {
        return $this->job_data['candidate_data'] ?? [];
    }

    /**
     * Helpers
     */
    public function canReprocess(): bool
    {
        return in_array($this->status, [
            self::STATUS_REJECTED,
            self::STATUS_FAILED,
            self::STATUS_PENDING,
        ]);
    }

    public function isAnalyzed(): bool
    {
        return $this->match_score !== null;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function hasPdfs(): bool
    {
        $version = $this->versions()->latest()->first();
        return $version && !empty($version->resume_path);
    }

    public function versions()
    {
        return $this->hasMany(JobApplicationVersion::class);
    }

    public function deduplication()
    {
        return $this->hasOne(JobDeduplication::class);
    }
}
