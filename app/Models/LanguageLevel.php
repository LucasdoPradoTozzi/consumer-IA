<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LanguageLevel extends Model
{
    protected $fillable = ['name'];

    public function candidateLanguages()
    {
        return $this->hasMany(CandidateLanguage::class);
    }
}
