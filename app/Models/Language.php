<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = ['name'];

    public function candidateProfiles()
    {
        return $this->belongsToMany(CandidateProfile::class, 'candidate_languages')
            ->withPivot('language_level_id')
            ->withTimestamps();
    }
}
