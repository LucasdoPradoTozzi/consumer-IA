<?php

use App\Http\Controllers\JobApplicationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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
