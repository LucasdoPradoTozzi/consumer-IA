<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateCertification extends Model
{
    protected $fillable = ['candidate_profile_id', 'name'];

    public function profile()
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_profile_id');
    }
}
