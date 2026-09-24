<?php

use App\Http\Controllers\BusinessPartnerController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\JobPhotoController;
use App\Http\Controllers\JobStatusController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\MarkupDefaultController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MaterialTypeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuickBooksConnectionController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuoteWorkflowController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ShopSettingsController;
use App\Http\Controllers\ShopSwitchController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamMemberAccessController;
use App\Http\Controllers\ToolController;
use Illuminate\Support\Facades\Route;

Route::get('/', MarketingController::class)->name('marketing');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/current-shop/{shop}', [ShopSwitchController::class, 'update'])->name('current-shop.update');

    Route::resource('customers', CustomerController::class)
        ->except('show')
        ->middleware('feature:customers');

    Route::resource('quotes', QuoteController::class)
        ->except('show')
        ->middleware('feature:quote-builder');

    Route::middleware('feature:quote-builder')->prefix('quotes/{quote}')->name('quotes.')->group(function () {
        Route::middleware('can:quotes.update')->group(function () {
            Route::post('send', [QuoteWorkflowController::class, 'send'])->name('send');
            Route::post('mark-won', [QuoteWorkflowController::class, 'markWon'])->name('mark-won');
            Route::post('mark-lost', [QuoteWorkflowController::class, 'markLost'])->name('mark-lost');
            Route::post('archive', [QuoteWorkflowController::class, 'archive'])->name('archive');
            Route::post('reopen', [QuoteWorkflowController::class, 'reopen'])->name('reopen');
        });
        Route::get('pdf', [QuoteWorkflowController::class, 'downloadPdf'])->name('pdf')->middleware('can:quotes.view');
    });

    Route::post('quotes/{quote}/convert', [QuoteWorkflowController::class, 'convert'])
        ->name('quotes.convert')
        ->middleware(['feature:quote-builder', 'feature:job-board', 'can:jobs.create']);

    Route::resource('jobs', JobController::class)
        ->only(['index', 'show'])
        ->middleware(['feature:job-board', 'can:jobs.view']);

    // Needs jobs.update, or jobs.cancel for a cancellation - checked in the
    // controller since it depends on the target status.
    Route::patch('jobs/{job}/status', [JobStatusController::class, 'update'])
        ->name('jobs.status')
        ->middleware('feature:job-board');

    Route::post('jobs/{job}/photos', [JobPhotoController::class, 'store'])
        ->name('jobs.photos.store')
        ->middleware(['feature:job-board', 'can:jobs.update']);
    Route::delete('jobs/{job}/photos/{media}', [JobPhotoController::class, 'destroy'])
        ->name('jobs.photos.destroy')
        ->middleware(['feature:job-board', 'can:jobs.update']);

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

    Route::resource('tools', ToolController::class)
        ->except('show')
        ->middleware('feature:quote-builder');

    Route::resource('material-types', MaterialTypeController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middleware('feature:quote-builder');

    // Everything about the shop itself (as opposed to its catalog) lives
    // under /shop - settings, who's on the team, and what each role can do.
    Route::prefix('shop')->group(function () {
        Route::get('/team', [TeamController::class, 'index'])->name('team.index')->middleware('can:team.view');
        Route::patch('/team/{member}', [TeamController::class, 'update'])->name('team.update')->middleware('can:team.update');

        Route::middleware('can:team.manage-access')->group(function () {
            Route::get('/team/{member}/access', [TeamMemberAccessController::class, 'edit'])->name('team.access.edit');
            Route::put('/team/{member}/access', [TeamMemberAccessController::class, 'update'])->name('team.access.update');
        });

        Route::middleware('can:manage-shop')->group(function () {
            Route::get('/permissions', [RolePermissionController::class, 'edit'])->name('shop.permissions.edit');
            Route::put('/permissions', [RolePermissionController::class, 'update'])->name('shop.permissions.update');
        });

        Route::middleware('can:shop-settings.update')->group(function () {
            Route::get('/settings', [ShopSettingsController::class, 'edit'])->name('shop.settings.edit');
            Route::patch('/settings', [ShopSettingsController::class, 'update'])->name('shop.settings.update');
        });
    });

    Route::get('/markup-defaults', [MarkupDefaultController::class, 'index'])
        ->name('markup-defaults.index')
        ->middleware(['feature:quote-builder', 'can:markup.view']);
    Route::patch('/markup-defaults', [MarkupDefaultController::class, 'update'])
        ->name('markup-defaults.update')
        ->middleware(['feature:quote-builder', 'can:markup.update']);

    Route::middleware('feature:quickbooks-sync')->prefix('quickbooks')->name('quickbooks.')->group(function () {
        Route::get('/', [QuickBooksConnectionController::class, 'show'])->name('show')->middleware('can:quickbooks.view');
        Route::get('/connect', [QuickBooksConnectionController::class, 'connect'])->name('connect')->middleware('can:quickbooks.update');
        Route::get('/callback', [QuickBooksConnectionController::class, 'callback'])->name('callback')->middleware('can:quickbooks.update');
        Route::delete('/', [QuickBooksConnectionController::class, 'destroy'])->name('destroy')->middleware('can:quickbooks.delete');
    });
});

require __DIR__.'/auth.php';
