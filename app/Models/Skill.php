<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    protected $fillable = ['skill_type_id', 'name'];

    public function type()
    {
        return $this->belongsTo(SkillType::class, 'skill_type_id');
    }

    public function candidateSkills()
    {
        return $this->hasMany(CandidateSkill::class);
    }

    public function experiences()
    {
        return $this->belongsToMany(CandidateExperience::class, 'candidate_experience_skill', 'skill_id', 'candidate_experience_id');
    }
}
