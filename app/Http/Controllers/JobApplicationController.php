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
            $query->where('is_relevant', $request->relevant === 'yes');
        }

        // Filter by score
        if ($request->filled('min_score')) {
            $query->where('match_score', '>=', $request->min_score);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('job_title', 'like', "%{$search}%")
                    ->orWhere('job_company', 'like', "%{$search}%")
                    ->orWhere('candidate_name', 'like', "%{$search}%")
                    ->orWhere('candidate_email', 'like', "%{$search}%");
            });
        }

        $applications = $query->paginate(20)->withQueryString();

        // Stats for dashboard
        $stats = [
            'total' => JobApplication::count(),
            'pending' => JobApplication::pending()->count(),
            'processing' => JobApplication::processing()->count(),
            'completed' => JobApplication::completed()->count(),
            'rejected' => JobApplication::rejected()->count(),
            'failed' => JobApplication::failed()->count(),
            'avg_score' => JobApplication::whereNotNull('match_score')->avg('match_score'),
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
        if (!$jobApplication->canReprocess()) {
            return back()->with('error', 'Esta aplicação não pode ser reprocessada.');
        }

        // Reset status to pending
        $jobApplication->update([
            'status' => JobApplication::STATUS_PENDING,
            'error_message' => null,
            'error_trace' => null,
            'failed_at' => null,
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
            'completed_at' => now(),
        ]);

        return back()->with('success', 'Aplicação marcada como concluída.');
    }

    /**
     * Download PDF
     */
    public function downloadPdf(JobApplication $jobApplication, string $type)
    {
        $path = match ($type) {
            'cover-letter' => $jobApplication->cover_letter_pdf_path,
            'resume' => $jobApplication->resume_pdf_path,
            default => null,
        };

        if (!$path || !file_exists($path)) {
            abort(404, 'PDF não encontrado');
        }

        $filename = match ($type) {
            'cover-letter' => "cover_letter_{$jobApplication->job_id}.pdf",
            'resume' => "resume_{$jobApplication->job_id}.pdf",
        };

        return Response::download($path, $filename);
    }

    /**
     * Delete job application
     */
    public function destroy(JobApplication $jobApplication)
    {
        // Delete PDFs if exist
        if ($jobApplication->cover_letter_pdf_path && file_exists($jobApplication->cover_letter_pdf_path)) {
            unlink($jobApplication->cover_letter_pdf_path);
        }

        if ($jobApplication->resume_pdf_path && file_exists($jobApplication->resume_pdf_path)) {
            unlink($jobApplication->resume_pdf_path);
        }

        $jobApplication->delete();

        return redirect()->route('job-applications.index')
            ->with('success', 'Aplicação removida com sucesso.');
    }
}
