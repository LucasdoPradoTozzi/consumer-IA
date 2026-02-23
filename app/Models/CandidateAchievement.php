<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateAchievement extends Model
{
    protected $fillable = ['candidate_experience_id', 'achievement'];

    public function experience()
    {
        return $this->belongsTo(CandidateExperience::class, 'candidate_experience_id');
    }
}
