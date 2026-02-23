<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateSkill extends Model
{
    protected $fillable = ['candidate_profile_id', 'skill_id', 'experience_years'];

    public function profile()
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_profile_id');
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }
}
