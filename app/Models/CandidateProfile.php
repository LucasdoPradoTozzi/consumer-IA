<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CandidateProfile extends Model
{
    /** @use HasFactory<\Database\Factories\CandidateProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'summary',
        'remote',
        'hybrid',
        'onsite',
        'availability',
        'willing_to_relocate',
        'github',
        'linkedin',
        'seniority',
    ];

    protected $casts = [
        'remote' => 'boolean',
        'hybrid' => 'boolean',
        'onsite' => 'boolean',
        'willing_to_relocate' => 'boolean',
    ];

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'candidate_skills')
            ->withPivot(['id', 'experience_years'])
            ->withTimestamps();
    }

    public function experiences()
    {
        return $this->hasMany(CandidateExperience::class);
    }

    public function educations()
    {
        return $this->hasMany(CandidateEducation::class, 'candidate_profile_id');
    }

    public function certifications()
    {
        return $this->hasMany(CandidateCertification::class);
    }

    public function languages()
    {
        return $this->belongsToMany(Language::class, 'candidate_languages')
            ->withPivot(['id', 'language_level_id'])
            ->withTimestamps();
    }

    public function locations()
    {
        return $this->hasMany(CandidateLocation::class);
    }

    public function contractTypes()
    {
        return $this->hasMany(CandidateContractType::class);
    }
}
