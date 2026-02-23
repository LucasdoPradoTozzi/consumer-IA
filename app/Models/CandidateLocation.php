<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateLocation extends Model
{
    protected $fillable = ['candidate_profile_id', 'location'];

    public function profile()
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_profile_id');
    }
}
