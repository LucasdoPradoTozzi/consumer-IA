<?php

use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\CandidateProfileController;
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
    Route::get('/{jobApplication}/version/{version}', [\App\Http\Controllers\JobApplicationVersionController::class, 'show'])->name('version');
});

// Worker Logs & Monitoring
Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
Route::get('/api/logs/fetch', [LogController::class, 'fetch'])->name('logs.fetch');
Route::get('/api/logs/worker-status', [LogController::class, 'workerStatus'])->name('logs.worker-status');
Route::get('/api/logs/queue-messages', [LogController::class, 'queueMessages'])->name('logs.queue-messages');
Route::post('/api/logs/clear', [LogController::class, 'clear'])->name('logs.clear');

// Visualizar currículo personalizado
Route::get('/curriculum', function () {
    $candidate = config('curriculum.default_candidate');
    // Exemplo: pode carregar dados do banco ou JSON aqui
    return view('curriculum.base', compact('candidate'));
});

// Visualizar currículo em inglês
Route::get('/curriculum-en', function () {
    $candidate = config('curriculum_en.default_candidate');
    return view('curriculum.base', compact('candidate'));
});

// Candidate Profile Management
Route::prefix('candidate-profile')->name('candidate-profile.')->group(function () {
    Route::get('/', [CandidateProfileController::class, 'index'])->name('index');
    Route::get('/edit', [CandidateProfileController::class, 'edit'])->name('edit');
    Route::put('/', [CandidateProfileController::class, 'update'])->name('update');
});

// Skills Management
Route::prefix('skills')->name('skills.')->group(function () {
    Route::get('/', [\App\Http\Controllers\SkillController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\SkillController::class, 'store'])->name('store');
    Route::delete('/{skill}', [\App\Http\Controllers\SkillController::class, 'destroy'])->name('destroy');
    Route::post('/types', [\App\Http\Controllers\SkillController::class, 'storeType'])->name('types.store');
    Route::delete('/types/{type}', [\App\Http\Controllers\SkillController::class, 'destroyType'])->name('types.destroy');
});

// Language Management
Route::prefix('languages')->name('languages.')->group(function () {
    Route::get('/', [\App\Http\Controllers\LanguageController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\LanguageController::class, 'store'])->name('store');
    Route::delete('/{language}', [\App\Http\Controllers\LanguageController::class, 'destroy'])->name('destroy');
    Route::post('/levels', [\App\Http\Controllers\LanguageController::class, 'storeLevel'])->name('levels.store');
    Route::delete('/levels/{level}', [\App\Http\Controllers\LanguageController::class, 'destroyLevel'])->name('levels.destroy');
});
