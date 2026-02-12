<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    protected $fillable = [
        'job_id',
        'type',
        'status',
        'job_title',
        'job_company',
        'job_description',
        'job_skills',
        'job_data',
        'candidate_name',
        'candidate_email',
        'candidate_data',
        'is_relevant',
        'classification_reason',
        'match_score',
        'score_justification',
        'cover_letter',
        'adjusted_resume',
        'resume_changes',
        'cover_letter_pdf_path',
        'resume_pdf_path',
        'email_sent',
        'email_sent_at',
        'error_message',
        'error_trace',
        'started_at',
        'classified_at',
        'scored_at',
        'completed_at',
        'failed_at',
        'metadata',
        'processing_time_seconds',
    ];

    protected $casts = [
        'job_skills' => 'array',
        'job_data' => 'array',
        'candidate_data' => 'array',
        'resume_changes' => 'array',
        'metadata' => 'array',
        'is_relevant' => 'boolean',
        'email_sent' => 'boolean',
        'match_score' => 'integer',
        'processing_time_seconds' => 'integer',
        'email_sent_at' => 'datetime',
        'started_at' => 'datetime',
        'classified_at' => 'datetime',
        'scored_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_CLASSIFIED = 'classified';
    public const STATUS_SCORED = 'scored';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

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

    public function scopeRelevant($query)
    {
        return $query->where('is_relevant', true);
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
            self::STATUS_PENDING => 'secondary',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_CLASSIFIED => 'primary',
            self::STATUS_SCORED => 'warning',
            self::STATUS_REJECTED => 'dark',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_FAILED => 'danger',
            default => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pendente',
            self::STATUS_PROCESSING => 'Processando',
            self::STATUS_CLASSIFIED => 'Classificado',
            self::STATUS_SCORED => 'Pontuado',
            self::STATUS_REJECTED => 'Rejeitado',
            self::STATUS_COMPLETED => 'Concluído',
            self::STATUS_FAILED => 'Falhou',
            default => ucfirst($this->status),
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
            default => 'danger',
        };
    }

    /**
     * Helpers
     */
    public function canReprocess(): bool
    {
        return in_array($this->status, [
            self::STATUS_REJECTED,
            self::STATUS_FAILED,
        ]);
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
        return !empty($this->cover_letter_pdf_path) && !empty($this->resume_pdf_path);
    }
}
