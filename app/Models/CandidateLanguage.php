<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateLanguage extends Model
{
    protected $fillable = ['candidate_profile_id', 'language_id', 'language_level_id'];

    public function profile()
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_profile_id');
    }

    public function language()
    {
        return $this->belongsTo(Language::class);
    }

    public function level()
    {
        return $this->belongsTo(LanguageLevel::class, 'language_level_id');
    }
}
