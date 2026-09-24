<?php

use App\Http\Controllers\BusinessPartnerController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ServiceController;
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
});

require __DIR__.'/auth.php';
