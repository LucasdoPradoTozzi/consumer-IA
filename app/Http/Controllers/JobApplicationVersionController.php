<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\JobApplicationVersion;
use Illuminate\Http\Request;

class JobApplicationVersionController extends Controller
{
    public function show($applicationId, $versionId)
    {
        $application = JobApplication::findOrFail($applicationId);
        $version = JobApplicationVersion::findOrFail($versionId);
        return view('job-applications.version', compact('application', 'version'));
    }
}
