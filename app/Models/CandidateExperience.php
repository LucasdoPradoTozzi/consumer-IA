<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateExperience extends Model
{
    protected $fillable = [
        'candidate_profile_id', 'company', 'position', 'period',
        'duration_years', 'description'
    ];

    public function profile()
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_profile_id');
    }

    public function achievements()
    {
        return $this->hasMany(CandidateAchievement::class);
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'candidate_experience_skill', 'candidate_experience_id', 'skill_id');
    }
}
