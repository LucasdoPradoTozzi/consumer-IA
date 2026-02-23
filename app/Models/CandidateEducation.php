<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateEducation extends Model
{
    protected $table = 'candidate_education';

    protected $fillable = [
        'candidate_profile_id', 'institution', 'degree', 'period', 'status'
    ];

    public function profile()
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_profile_id');
    }
}
