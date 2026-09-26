<?php

use App\Http\Controllers\BusinessPartnerController;
use App\Http\Controllers\CertificationController;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\FeatureSettingController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\JobAssignmentController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\JobMaterialUsageController;
use App\Http\Controllers\JobPhotoController;
use App\Http\Controllers\JobQualificationRecordController;
use App\Http\Controllers\JobStageController;
use App\Http\Controllers\JobStatusController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\MarkupDefaultController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MaterialTypeController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PlanSettingController;
use App\Http\Controllers\PlatformAdminController;
use App\Http\Controllers\ProcedureController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuickBooksConnectionController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuoteWorkflowController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ShopFeatureSubscriptionController;
use App\Http\Controllers\ShopSettingsController;
use App\Http\Controllers\ShopSuspendedController;
use App\Http\Controllers\ShopSwitchController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamMemberAccessController;
use App\Http\Controllers\ToolController;
use Illuminate\Support\Facades\Route;

Route::get('/', MarketingController::class)->name('marketing');

// No auth/guest middleware - the visitor may have no account yet, an
// account under a different email, or already be exactly who's invited.
// InvitationController sorts out which of those it is.
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->middleware('throttle:6,1')
    ->name('invitations.accept');

// Deliberately auth+verified only, never 'onboarded' - this is what
// EnsureShopIsOnboarded redirects into.
Route::middleware(['auth', 'verified'])->prefix('onboarding')->name('onboarding.')->group(function () {
    Route::get('/', [OnboardingController::class, 'show'])->name('show');
    Route::post('/shop', [OnboardingController::class, 'storeShop'])->name('shop');
    Route::post('/team', [OnboardingController::class, 'storeTeam'])->name('team');
    Route::post('/catalog', [OnboardingController::class, 'storeCatalog'])->name('catalog');
});

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified', 'active', 'onboarded'])->name('dashboard');

// Landing spot for a suspended shop's users - deliberately not behind
// 'active' itself, or a suspended shop could never reach it.
Route::get('/shop-suspended', [ShopSuspendedController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('shop.suspended');

// Internal-only, cross-tenant - see PlatformAdminController's class doc.
// Deliberately outside 'active'/'onboarded': platform staff aren't acting
// within a shop here, and may not even belong to one.
Route::middleware(['auth', 'verified', 'can:platform-admin'])->prefix('platform-admin')->name('platform-admin.')->group(function () {
    Route::get('/', [PlatformAdminController::class, 'index'])->name('index');
    Route::post('/shops/{shop}/suspend', [PlatformAdminController::class, 'suspend'])->name('shops.suspend');
    Route::post('/shops/{shop}/unsuspend', [PlatformAdminController::class, 'unsuspend'])->name('shops.unsuspend');
    Route::patch('/shops/{shop}/plan', [PlatformAdminController::class, 'updatePlan'])->name('shops.plan');
    Route::patch('/shops/{shop}/seat-limit', [PlatformAdminController::class, 'updateSeatLimit'])->name('shops.seat-limit');
    Route::patch('/shops/{shop}/trial/extend', [PlatformAdminController::class, 'extendTrial'])->name('shops.trial.extend');
    Route::post('/shops/{shop}/trial/expire', [PlatformAdminController::class, 'expireTrial'])->name('shops.trial.expire');
    Route::patch('/shops/{shop}/subscription/extend', [PlatformAdminController::class, 'extendSubscription'])->name('shops.subscription.extend');
    Route::get('/features', [FeatureSettingController::class, 'index'])->name('features.index');
    Route::patch('/features', [FeatureSettingController::class, 'update'])->name('features.update');
    Route::get('/plans', [PlanSettingController::class, 'index'])->name('plans.index');
    Route::patch('/plans', [PlanSettingController::class, 'update'])->name('plans.update');
});

Route::middleware(['auth', 'verified', 'active', 'onboarded'])->group(function () {
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

    Route::patch('jobs/{job}/assignee', [JobAssignmentController::class, 'update'])
        ->name('jobs.assignee.update')
        ->middleware(['feature:job-board', 'can:jobs.update']);

    Route::post('jobs/{job}/photos', [JobPhotoController::class, 'store'])
        ->name('jobs.photos.store')
        ->middleware(['feature:job-board', 'can:jobs.update']);
    Route::delete('jobs/{job}/photos/{media}', [JobPhotoController::class, 'destroy'])
        ->name('jobs.photos.destroy')
        ->middleware(['feature:job-board', 'can:jobs.update']);

    Route::post('jobs/{job}/material-usage', [JobMaterialUsageController::class, 'store'])
        ->name('jobs.material-usage.store')
        ->middleware(['feature:job-board', 'feature:material-tracking', 'can:jobs.update']);
    Route::delete('jobs/{job}/material-usage/{materialUsage}', [JobMaterialUsageController::class, 'destroy'])
        ->name('jobs.material-usage.destroy')
        ->middleware(['feature:job-board', 'feature:material-tracking', 'can:jobs.update']);

    Route::post('jobs/{job}/qualification-records', [JobQualificationRecordController::class, 'store'])
        ->name('jobs.qualification-records.store')
        ->middleware(['feature:job-board', 'feature:qualification-records', 'can:jobs.update']);
    Route::delete('jobs/{job}/qualification-records/{qualificationRecord}', [JobQualificationRecordController::class, 'destroy'])
        ->name('jobs.qualification-records.destroy')
        ->middleware(['feature:job-board', 'feature:qualification-records', 'can:jobs.update']);

    Route::patch('job-stages/reorder', [JobStageController::class, 'reorder'])
        ->name('job-stages.reorder')
        ->middleware('feature:job-board');
    Route::resource('job-stages', JobStageController::class)
        ->only(['index', 'store', 'update', 'destroy'])
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

    Route::resource('tools', ToolController::class)
        ->except('show')
        ->middleware('feature:quote-builder');

    Route::resource('material-types', MaterialTypeController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middleware('feature:quote-builder');

    Route::resource('procedures', ProcedureController::class)
        ->except('show')
        ->middleware('feature:qualification-records');

    Route::resource('certifications', CertificationController::class)
        ->except('show')
        ->middleware('feature:qualification-records');

    // CSV import - one controller shared by every importable resource (see
    // App\Support\CsvImport\ImporterRegistry). Owner-only for now.
    Route::middleware('can:manage-shop')->prefix('imports')->name('imports.')->group(function () {
        Route::get('/{resource}/template', [CsvImportController::class, 'template'])->name('template');
        Route::post('/{resource}', [CsvImportController::class, 'store'])->name('store');
        Route::get('/status/{import}', [CsvImportController::class, 'show'])->name('show');
    });

    // Everything about the shop itself (as opposed to its catalog) lives
    // under /shop - settings, who's on the team, and what each role can do.
    Route::prefix('shop')->group(function () {
        Route::get('/team', [TeamController::class, 'index'])->name('team.index')->middleware('can:team.view');
        Route::patch('/team/{member}', [TeamController::class, 'update'])->name('team.update')->middleware('can:team.update');
        Route::post('/team/invite', [TeamController::class, 'invite'])->name('team.invite')->middleware('can:manage-shop');

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

        Route::middleware('can:manage-shop')->group(function () {
            Route::get('/addons', [ShopFeatureSubscriptionController::class, 'index'])->name('shop.addons.index');
            Route::post('/addons/{feature}', [ShopFeatureSubscriptionController::class, 'store'])->name('shop.addons.store');
            Route::delete('/addons/{feature}', [ShopFeatureSubscriptionController::class, 'destroy'])->name('shop.addons.destroy');
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
