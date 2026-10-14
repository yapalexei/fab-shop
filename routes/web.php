<?php

use App\Enums\Feature;
use App\Http\Controllers\BusinessPartnerController;
use App\Http\Controllers\CatalogAiDraftController;
use App\Http\Controllers\CertificationController;
use App\Http\Controllers\CertificationDocumentController;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntityImageController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\FeatureSettingController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\JobAssignmentController;
use App\Http\Controllers\JobAttachmentController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\JobDueDateController;
use App\Http\Controllers\JobMaterialUsageController;
use App\Http\Controllers\JobPhotoController;
use App\Http\Controllers\JobQualificationRecordController;
use App\Http\Controllers\JobStageController;
use App\Http\Controllers\JobStatusController;
use App\Http\Controllers\JobTimelineReportController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\MarkupDefaultController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MaterialTypeController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PlanSettingController;
use App\Http\Controllers\PlatformAdminController;
use App\Http\Controllers\PlatformSecurityController;
use App\Http\Controllers\ProcedureController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuickBooksConnectionController;
use App\Http\Controllers\QuoteAiDraftController;
use App\Http\Controllers\QuoteAttachmentController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuoteFollowUpController;
use App\Http\Controllers\QuoteWorkflowController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SampleDataController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ShopAiAgentController;
use App\Http\Controllers\ShopAiSettingsController;
use App\Http\Controllers\ShopDataController;
use App\Http\Controllers\ShopFeatureSubscriptionController;
use App\Http\Controllers\ShopPlanController;
use App\Http\Controllers\ShopSeatController;
use App\Http\Controllers\ShopSecurityController;
use App\Http\Controllers\ShopSettingsController;
use App\Http\Controllers\ShopSuspendedController;
use App\Http\Controllers\ShopSwitchController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamMemberAccessController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\TrialEndedNoticeController;
use App\Http\Controllers\TwoFactorSettingsController;
use App\Http\Controllers\UserAvatarController;
use Illuminate\Support\Facades\Route;

Route::get('/', MarketingController::class)->name('marketing')->sitemap();

// Generated from the route table - mark a public page ->sitemap() to list it,
// or ->noindex() to keep a public-but-private-ish URL out (see App\Support\Seo).
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');

// Public legal pages - linked from the marketing page, sign-in/registration,
// emails and the app footer.
Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms')->sitemap('legal.versions.terms');
Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy')->sitemap('legal.versions.privacy');
Route::get('/sms-terms', [LegalController::class, 'sms'])->name('legal.sms')->sitemap('legal.versions.sms');

// No auth/guest middleware - the visitor may have no account yet, an
// account under a different email, or already be exactly who's invited.
// InvitationController sorts out which of those it is.
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show')->noindex();
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
    // Security is an index; the two-factor default and the log are sub-pages.
    Route::get('/security', [PlatformSecurityController::class, 'index'])->name('security.index');
    Route::get('/security/two-factor', [PlatformSecurityController::class, 'policy'])->name('security.policy');
    Route::put('/security/two-factor', [PlatformSecurityController::class, 'updatePolicy'])->name('security.two-factor');
    Route::get('/security/log', [PlatformSecurityController::class, 'log'])->name('security.log');
    Route::get('/plans', [PlanSettingController::class, 'index'])->name('plans.index');
    Route::post('/plans', [PlanSettingController::class, 'store'])->name('plans.store');
    Route::patch('/plans/{plan}', [PlanSettingController::class, 'update'])->name('plans.update');
    Route::post('/plans/{plan}/retire', [PlanSettingController::class, 'retire'])->name('plans.retire');
});

Route::middleware(['auth', 'verified', 'active', 'onboarded'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/two-factor', [TwoFactorSettingsController::class, 'store'])->name('two-factor.enable');
    Route::post('/profile/two-factor/confirm', [TwoFactorSettingsController::class, 'confirm'])->name('two-factor.confirm');
    Route::post('/profile/two-factor/recovery-codes', [TwoFactorSettingsController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery-codes');
    Route::delete('/profile/two-factor', [TwoFactorSettingsController::class, 'destroy'])->name('two-factor.disable');
    Route::post('/profile/avatar', [UserAvatarController::class, 'store'])->name('profile.avatar.store');
    Route::delete('/profile/avatar', [UserAvatarController::class, 'destroy'])->name('profile.avatar.destroy');
    Route::get('/users/{user}/avatar', [UserAvatarController::class, 'show'])->name('users.avatar');

    Route::post('/trial-ended-notice/dismiss', TrialEndedNoticeController::class)->name('trial-ended-notice.dismiss');
    Route::post('/current-shop/{shop}', [ShopSwitchController::class, 'update'])->name('current-shop.update');

    Route::get('customers/{customer}/logo', [EntityImageController::class, 'customerLogo'])->name('customers.logo');
    Route::get('business-partners/{business_partner}/logo', [EntityImageController::class, 'businessPartnerLogo'])->name('business-partners.logo');

    Route::get('materials/{material}/image', [EntityImageController::class, 'materialImage'])->name('materials.image');

    Route::post('customers/{customer}/ai-review', [CustomerController::class, 'markAiReviewed'])
        ->name('customers.ai-review')
        ->middleware(['feature:customers', 'can:customers.update']);

    Route::resource('customers', CustomerController::class)
        ->except('show')
        ->middleware('feature:customers');

    // Strict feature check on purpose (a POST, so hasFeature() not the
    // read-only canViewFeature()): AI stops the moment the shop isn't
    // entitled. Declared before the resource so it can't be read as a quote.
    Route::post('quotes/ai-draft', QuoteAiDraftController::class)
        ->name('quotes.ai-draft')
        ->middleware(['feature:quote-builder', 'feature:ai-assist', 'can:quotes.create']);

    // Fill a new tool or material from pasted supplier-page text. Strict AI
    // check like the quote drafter; the create permission is checked in the
    // request, since it depends on which kind.
    Route::post('catalog/{kind}/ai-draft', CatalogAiDraftController::class)
        ->whereIn('kind', ['tools', 'materials', 'supplier-prices'])
        ->name('catalog.ai-draft')
        ->middleware(['feature:quote-builder', 'feature:ai-assist']);

    Route::post('quotes/{quote}/ai-follow-up', QuoteFollowUpController::class)
        ->name('quotes.ai-follow-up')
        ->middleware(['feature:quote-builder', 'feature:ai-assist', 'can:quotes.view']);

    Route::resource('quotes', QuoteController::class)
        ->except('show')
        ->middleware('feature:quote-builder');

    Route::middleware('feature:quote-builder')->prefix('quotes/{quote}')->name('quotes.')->group(function () {
        Route::middleware('can:quotes.update')->group(function () {
            Route::post('send', [QuoteWorkflowController::class, 'send'])->name('send');
            Route::post('skip-send', [QuoteWorkflowController::class, 'skipSend'])->name('skip-send');
            Route::post('resend', [QuoteWorkflowController::class, 'resend'])->name('resend');
            Route::post('ai-review', [QuoteWorkflowController::class, 'markAiReviewed'])->name('ai-review');
            Route::post('mark-won', [QuoteWorkflowController::class, 'markWon'])->name('mark-won');
            Route::post('mark-lost', [QuoteWorkflowController::class, 'markLost'])->name('mark-lost');
            Route::post('archive', [QuoteWorkflowController::class, 'archive'])->name('archive');
            Route::post('reopen', [QuoteWorkflowController::class, 'reopen'])->name('reopen');
        });
        Route::post('attachments', [QuoteAttachmentController::class, 'store'])->name('attachments.store')->middleware('can:quotes.update');
        Route::delete('attachments/{media}', [QuoteAttachmentController::class, 'destroy'])->name('attachments.destroy')->middleware('can:quotes.update');
        Route::get('attachments/{media}', [QuoteAttachmentController::class, 'show'])->name('attachments.show')->middleware('can:quotes.view');
        Route::get('pdf', [QuoteWorkflowController::class, 'downloadPdf'])->name('pdf')->middleware('can:quotes.view');
        Route::get('preview', [QuoteWorkflowController::class, 'previewPdf'])->name('preview')->middleware('can:quotes.view');
    });

    Route::post('quotes/{quote}/duplicate', [QuoteWorkflowController::class, 'duplicate'])
        ->name('quotes.duplicate')
        ->middleware(['feature:quote-builder', 'can:quotes.create']);

    Route::post('quotes/{quote}/convert', [QuoteWorkflowController::class, 'convert'])
        ->name('quotes.convert')
        ->middleware(['feature:quote-builder', 'feature:job-board', 'can:jobs.create']);

    Route::resource('jobs', JobController::class)
        ->only(['index', 'show'])
        ->middleware(['feature:job-board', 'can:jobs.view']);

    Route::get('reports/job-timeline', JobTimelineReportController::class)
        ->name('reports.job-timeline')
        ->middleware(['feature:job-board', 'can:jobs.view']);

    // Needs jobs.update, or jobs.cancel for a cancellation - checked in the
    // controller since it depends on the target status.
    Route::patch('jobs/{job}/status', [JobStatusController::class, 'update'])
        ->name('jobs.status')
        ->middleware('feature:job-board');

    Route::patch('jobs/{job}/assignee', [JobAssignmentController::class, 'update'])
        ->name('jobs.assignee.update')
        ->middleware(['feature:job-board', 'can:jobs.update']);

    Route::patch('jobs/{job}/due-date', [JobDueDateController::class, 'update'])
        ->name('jobs.due-date.update')
        ->middleware(['feature:job-board', 'can:jobs.update']);

    Route::get('jobs/{job}/attachments/{media}', [JobAttachmentController::class, 'show'])
        ->name('jobs.attachments.show')
        ->middleware(['feature:job-board', 'can:jobs.view']);

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

    Route::post('business-partners/{business_partner}/make-customer', [BusinessPartnerController::class, 'makeCustomer'])
        ->middleware(['feature:customers', 'can:customers.create', 'can:business-partners.view'])
        ->name('business-partners.make-customer');

    Route::resource('business-partners', BusinessPartnerController::class)
        ->except('show')
        ->middleware('feature:quote-builder');

    Route::resource('equipment', EquipmentController::class)
        ->except('show')
        ->middleware('feature:quote-builder');

    Route::get('tools/{tool}/image', [ToolController::class, 'image'])->name('tools.image');
    Route::get('tools/{tool}/model', [ToolController::class, 'model'])->name('tools.model');
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

    Route::middleware('feature:qualification-records')->prefix('certifications/{certification}/documents')->name('certifications.documents.')->group(function () {
        Route::post('/', [CertificationDocumentController::class, 'store'])->name('store')->middleware('can:certifications.update');
        Route::get('{media}', [CertificationDocumentController::class, 'show'])->name('show')->middleware('can:certifications.view');
        Route::delete('{media}', [CertificationDocumentController::class, 'destroy'])->name('destroy')->middleware('can:certifications.update');
    });

    // "Load sample ..." dialog on the catalog pages. Permission is per
    // resource (create) and checked in the controller.
    Route::get('sample-data/{resource}', [SampleDataController::class, 'index'])
        ->name('sample-data.index')
        ->middleware('feature:quote-builder')
        ->whereIn('resource', ['material-types', 'materials', 'services', 'equipment']);
    Route::post('sample-data/{resource}', [SampleDataController::class, 'store'])
        ->name('sample-data.store')
        ->middleware('feature:quote-builder')
        ->whereIn('resource', ['material-types', 'materials', 'services', 'equipment']);

    // CSV import - one controller shared by every importable resource (see
    // App\Support\CsvImport\ImporterRegistry). Owner-only for now.
    Route::middleware('can:manage-shop')->prefix('imports')->name('imports.')->group(function () {
        Route::get('/{resource}/template', [CsvImportController::class, 'template'])->name('template');
        Route::post('/{resource}/preview', [CsvImportController::class, 'preview'])->name('preview');
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
            // Security is an index; roles & permissions and the audit log
            // are sub-pages of it.
            Route::get('/security', [ShopSecurityController::class, 'index'])->name('shop.security.index');
            Route::get('/security/log', [ShopSecurityController::class, 'log'])->name('shop.security.log');
            Route::get('/security/permissions', [RolePermissionController::class, 'edit'])->name('shop.permissions.edit');
            Route::put('/security/permissions', [RolePermissionController::class, 'update'])->name('shop.permissions.update');
            Route::get('/security/two-factor', [ShopSecurityController::class, 'twoFactor'])->name('shop.security.policy');
            Route::put('/security/two-factor', [ShopSecurityController::class, 'updatePolicy'])->name('shop.security.two-factor');
            Route::post('/data/export', [ShopDataController::class, 'export'])->name('shop.data.export');
            Route::get('/data/export/{export}', [ShopDataController::class, 'download'])->name('shop.data.download');
            Route::post('/data/deletion', [ShopDataController::class, 'requestDeletion'])->name('shop.data.deletion');
            Route::delete('/data/deletion', [ShopDataController::class, 'cancelDeletion'])->name('shop.data.deletion.cancel');
        });

        // The page is viewable after a trial ends (it explains that AI is
        // paused); saving needs the feature - see EnsureFeatureEnabled.
        Route::middleware(['can:manage-shop', 'feature:ai-assist'])->prefix('ai')->name('shop.ai.')->group(function () {
            Route::get('/', [ShopAiSettingsController::class, 'edit'])->name('edit');
            Route::put('/', [ShopAiSettingsController::class, 'update'])->name('update');
            Route::delete('/', [ShopAiSettingsController::class, 'destroy'])->name('destroy');

            // The MCP connection: one AI agent identity per shop, and the
            // tokens that connect an AI client to it.
            Route::middleware('feature:ai-agent')->group(function () {
                Route::post('/agent', [ShopAiAgentController::class, 'store'])->name('agent.store');
                Route::put('/agent', [ShopAiAgentController::class, 'update'])->name('agent.update');
                Route::delete('/agent', [ShopAiAgentController::class, 'destroy'])->name('agent.destroy');
                Route::post('/agent/tokens', [ShopAiAgentController::class, 'storeToken'])->name('agent.tokens.store');
                Route::delete('/agent/tokens/{token}', [ShopAiAgentController::class, 'destroyToken'])->name('agent.tokens.destroy');
            });
        });

        Route::middleware('can:shop-settings.update')->group(function () {
            Route::get('/settings', [ShopSettingsController::class, 'edit'])->name('shop.settings.edit');
            Route::patch('/settings', [ShopSettingsController::class, 'update'])->name('shop.settings.update');
        });

        Route::middleware('can:manage-shop')->group(function () {
            Route::get('/plan', [ShopPlanController::class, 'index'])->name('shop.plan.index');
            Route::post('/plan', [ShopPlanController::class, 'update'])->name('shop.plan.update');
            Route::delete('/plan/notice', [ShopPlanController::class, 'dismissNotice'])->name('shop.plan.notice.dismiss');
            Route::put('/seats', [ShopSeatController::class, 'update'])->name('shop.seats.update');
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

    // Switched off while QuickBooks is a future feature - see Feature::isShipped().
    if (Feature::QuickbooksSync->isShipped()) {
        Route::middleware('feature:quickbooks-sync')->prefix('quickbooks')->name('quickbooks.')->group(function () {
            Route::get('/', [QuickBooksConnectionController::class, 'show'])->name('show')->middleware('can:quickbooks.view');
            Route::get('/connect', [QuickBooksConnectionController::class, 'connect'])->name('connect')->middleware('can:quickbooks.update');
            Route::get('/callback', [QuickBooksConnectionController::class, 'callback'])->name('callback')->middleware('can:quickbooks.update');
            Route::delete('/', [QuickBooksConnectionController::class, 'destroy'])->name('destroy')->middleware('can:quickbooks.delete');
        });
    }
});

require __DIR__.'/auth.php';
