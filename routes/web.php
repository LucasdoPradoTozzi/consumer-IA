<?php

use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\LogController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/job-applications');
});

Route::get('/dashboard', function () {
    return redirect('/job-applications');
});

// Job Applications Dashboard
Route::prefix('job-applications')->name('job-applications.')->group(function () {
    Route::get('/', [JobApplicationController::class, 'index'])->name('index');
    Route::get('/{jobApplication}', [JobApplicationController::class, 'show'])->name('show');
    Route::post('/{jobApplication}/reprocess', [JobApplicationController::class, 'reprocess'])->name('reprocess');
    Route::post('/{jobApplication}/mark-completed', [JobApplicationController::class, 'markCompleted'])->name('mark-completed');
    Route::get('/{jobApplication}/download/{type}', [JobApplicationController::class, 'downloadPdf'])->name('download-pdf');
    Route::delete('/{jobApplication}', [JobApplicationController::class, 'destroy'])->name('destroy');
});

// Worker Logs & Monitoring
Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
Route::get('/api/logs/fetch', [LogController::class, 'fetch'])->name('logs.fetch');
Route::get('/api/logs/worker-status', [LogController::class, 'workerStatus'])->name('logs.worker-status');
Route::post('/api/logs/clear', [LogController::class, 'clear'])->name('logs.clear');
