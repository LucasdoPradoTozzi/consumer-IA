<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class JobApplicationController extends Controller
{
    /**
     * Display a listing of job applications
     */
    public function index(Request $request)
    {
        $query = JobApplication::query()->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by relevance
        if ($request->filled('relevant')) {
            $isRelevant = $request->relevant === 'yes';
            $query->whereHas('scorings', function ($q) use ($isRelevant) {
                $q->where('is_relevant', $isRelevant);
            });
        }

        // Filter by score
        if ($request->filled('min_score')) {
            $query->whereHas('scorings', function ($q) use ($request) {
                $q->where('score', '>=', $request->min_score);
            });
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereRaw("job_data->>'title' ILIKE ?", ["%{$search}%"])
                    ->orWhereRaw("job_data->>'company' ILIKE ?", ["%{$search}%"])
                    ->orWhereRaw("job_data->>'candidate_name' ILIKE ?", ["%{$search}%"])
                    ->orWhereRaw("job_data->>'candidate_email' ILIKE ?", ["%{$search}%"]);
            });
        }

        $applications = $query->paginate(20)->withQueryString();

        // Stats for dashboard
        $stats = [
            'total' => JobApplication::count(),
            'pending' => JobApplication::where('status', 'pending')->count(),
            'processing' => JobApplication::where('status', 'processing')->count(),
            'completed' => JobApplication::where('status', 'completed')->count(),
            'rejected' => JobApplication::where('status', 'rejected')->count(),
            'failed' => JobApplication::where('status', 'failed')->count(),
            'avg_score' => \App\Models\JobScoring::avg('scoring_score'),
        ];

        return view('job-applications.index', compact('applications', 'stats'));
    }

    /**
     * Display the specified job application
     */
    public function show(JobApplication $jobApplication)
    {
        return view('job-applications.show', compact('jobApplication'));
    }

    /**
     * Reprocess a job application
     */
    public function reprocess(JobApplication $jobApplication)
    {
        // Reset status to pending
        $jobApplication->update([
            'status' => JobApplication::STATUS_PENDING,
        ]);

        // TODO: Republish to RabbitMQ queue
        // For now, just update status

        return back()->with('success', 'Aplicação marcada para reprocessamento.');
    }

    /**
     * Mark as completed manually
     */
    public function markCompleted(JobApplication $jobApplication)
    {
        $jobApplication->update([
            'status' => JobApplication::STATUS_COMPLETED,
        ]);

        return back()->with('success', 'Aplicação marcada como concluída.');
    }

    /**
     * Download PDF
     */
    public function downloadPdf(JobApplication $jobApplication, string $type)
    {
        $version = $jobApplication->versions()->latest()->first();

        if (!$version) {
            abort(404, 'Versão não encontrada');
        }

        $path = match ($type) {
            'cover-letter' => $version->cover_letter_path,
            'resume' => $version->resume_path,
            default => null,
        };

        if (!$path || !file_exists(storage_path('app/' . $path))) {
            abort(404, 'PDF não encontrado');
        }

        $filename = match ($type) {
            'cover-letter' => "cover_letter_{$jobApplication->job_id}.pdf",
            'resume' => "resume_{$jobApplication->job_id}.pdf",
        };

        return Response::download(storage_path('app/' . $path), $filename);
    }

    /**
     * Delete job application
     */
    public function destroy(JobApplication $jobApplication)
    {
        // Delete associated files
        foreach ($jobApplication->versions as $version) {
            if ($version->cover_letter_path && file_exists(storage_path('app/' . $version->cover_letter_path))) {
                unlink(storage_path('app/' . $version->cover_letter_path));
            }
            if ($version->resume_path && file_exists(storage_path('app/' . $version->resume_path))) {
                unlink(storage_path('app/' . $version->resume_path));
            }
        }

        $jobApplication->delete();

        return redirect()->route('job-applications.index')
            ->with('success', 'Aplicação removida com sucesso.');
    }
}
