<?php

use App\Http\Controllers\BusinessPartnerController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\MarkupDefaultController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuoteWorkflowController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TeamController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('customers', CustomerController::class)
        ->except('show')
        ->middleware('feature:customers');

    Route::resource('quotes', QuoteController::class)
        ->except('show')
        ->middleware('feature:quote-builder');

    Route::middleware('feature:quote-builder')->prefix('quotes/{quote}')->name('quotes.')->group(function () {
        Route::post('send', [QuoteWorkflowController::class, 'send'])->name('send');
        Route::post('mark-won', [QuoteWorkflowController::class, 'markWon'])->name('mark-won');
        Route::post('mark-lost', [QuoteWorkflowController::class, 'markLost'])->name('mark-lost');
        Route::post('archive', [QuoteWorkflowController::class, 'archive'])->name('archive');
        Route::post('reopen', [QuoteWorkflowController::class, 'reopen'])->name('reopen');
        Route::get('pdf', [QuoteWorkflowController::class, 'downloadPdf'])->name('pdf');
    });

    Route::post('quotes/{quote}/convert', [QuoteWorkflowController::class, 'convert'])
        ->name('quotes.convert')
        ->middleware(['feature:quote-builder', 'feature:job-board']);

    Route::resource('jobs', JobController::class)
        ->only(['index', 'show'])
        ->middleware('feature:job-board');

    Route::resource('materials', MaterialController::class)
        ->except('show')
        ->middleware('feature:quote-builder');

    Route::resource('services', ServiceController::class)
        ->except('show')
        ->middleware('feature:quote-builder');

    Route::resource('business-partners', BusinessPartnerController::class)
        ->except('show')
        ->middleware('feature:quote-builder');

    Route::resource('equipment', EquipmentController::class)
        ->except('show')
        ->middleware('feature:quote-builder');

    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::patch('/team/{member}', [TeamController::class, 'update'])->name('team.update');

    Route::get('/markup-defaults', [MarkupDefaultController::class, 'index'])
        ->name('markup-defaults.index')
        ->middleware('feature:quote-builder');
    Route::patch('/markup-defaults', [MarkupDefaultController::class, 'update'])
        ->name('markup-defaults.update')
        ->middleware('feature:quote-builder');
});

require __DIR__.'/auth.php';
