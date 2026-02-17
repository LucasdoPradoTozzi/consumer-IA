<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\JobApplicationVersion;
use App\Models\Job;
use App\Models\JobVersion;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function index()
    {
        $jobs = Job::with('versions')->orderByDesc('created_at')->get();
        return view('jobs.index', compact('jobs'));
    }

    public function show($jobId)
    {
        $job = Job::with('versions')->findOrFail($jobId);
        return view('jobs.show', compact('job'));
    }

    public function showVersion($jobId, $versionId)
    {
        $job = Job::findOrFail($jobId);
        $version = JobVersion::with('applications.versions')->findOrFail($versionId);
        return view('jobs.version', compact('job', 'version'));
    }
}
