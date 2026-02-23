<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateContractType extends Model
{
    protected $fillable = ['candidate_profile_id', 'type'];

    public function profile()
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_profile_id');
    }
}
