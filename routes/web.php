<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataPricingController;
use App\Http\Controllers\DataPurchaseController;
use App\Http\Controllers\DataTransactionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\NIN\VerifyController as NinVerifyController;
use App\Http\Controllers\NIN\IpeController as NinIpeController;
use App\Http\Controllers\NIN\ValidationController as NinValidationController;
use App\Http\Controllers\NIN\SlipDownloadController;
use App\Http\Controllers\BvnModificationController;
use App\Http\Controllers\BvnSdkFormController;
use App\Http\Controllers\BvnRetrievalController;
use App\Http\Controllers\BvnSearchController;
use App\Http\Controllers\IdCardController;
use App\Http\Controllers\BvnRecordController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ApiAccessController;
use App\Http\Controllers\HelpController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'appName' => config('app.name'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Email verification is available (User implements MustVerifyEmail) but not
// enforced — registration logs users straight in. Drop the `verified` gate so
// the dashboard stays open; add it back here to require verified emails.
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Buy Data (normalized vendor-routing module with queued fulfilment)
    Route::get('/buy-data', [DataPurchaseController::class, 'index'])->name('buy-data');
    Route::post('/buy-data', [DataPurchaseController::class, 'store'])->name('buy-data.store');
    Route::get('/buy-data/{reference}/status', [DataPurchaseController::class, 'status'])->name('buy-data.status');
    Route::get('/data-transactions', [DataTransactionController::class, 'index'])->name('data-transactions.index');

    // Verification Routes
    Route::get('/verification/nin', [VerificationController::class, 'nin'])->name('verification.nin');
    Route::post('/verification/nin', [VerificationController::class, 'verifyNin'])->name('verification.nin.verify');
    Route::get('/verification/bvn', [VerificationController::class, 'bvn'])->name('verification.bvn');
    Route::post('/verification/bvn', [VerificationController::class, 'verifyBvn'])->name('verification.bvn.verify');
    Route::get('/verification/history', [VerificationController::class, 'history'])->name('verification.history');

    // Wallet Routes
    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::get('/wallet/fund', [WalletController::class, 'fund'])->name('wallet.fund');
    Route::post('/wallet/virtual-account', [WalletController::class, 'createVirtualAccount'])->name('wallet.virtual-account.create');
    Route::get('/wallet/transactions', [WalletController::class, 'transactions'])->name('wallet.transactions');

    // NIN Verification Routes (by NIN number) — v1 Prembly, v2 ArewaSmart
    Route::get('/nin/verify', [NinVerifyController::class, 'index'])->name('nin.verify.index');
    Route::post('/nin/verify/v1', [NinVerifyController::class, 'verifyV1'])->name('nin.verify.v1');
    Route::post('/nin/verify/v2', [NinVerifyController::class, 'verifyV2'])->name('nin.verify.v2');

    // NIN Slip Download Routes — separate billing from verification
    Route::get('/nin/slip/types', [SlipDownloadController::class, 'types'])->name('nin.slip.types');
    Route::post('/nin/slip/download', [SlipDownloadController::class, 'download'])->name('nin.slip.download');

    // Phone & Demographic verification are consolidated into the single dynamic
    // NIN Verification page (provider + method selector), which verifies through
    // the modular /api/v1/nin/providers/* JSON endpoints. Their standalone
    // controllers and pages have been deleted; these redirects are kept only so
    // existing links and bookmarks still land somewhere useful.
    Route::get('/nin/phone', fn () => redirect()->route('nin.verify.index'))->name('nin.phone.index');
    Route::get('/nin/demo', fn () => redirect()->route('nin.verify.index'))->name('nin.demo.index');

    // NIN IPE Routes — v1 Nguru, v2 ArewaSmart
    Route::get('/nin/ipe', [NinIpeController::class, 'index'])->name('nin.ipe.index');
    Route::post('/nin/ipe/v1', [NinIpeController::class, 'submitV1'])->name('nin.ipe.v1');
    Route::post('/nin/ipe/v2', [NinIpeController::class, 'submitV2'])->name('nin.ipe.v2');
    Route::post('/nin/ipe/{clearance}/status', [NinIpeController::class, 'checkStatus'])->name('nin.ipe.status');

    // NIN Validation Routes — v1 Prembly, v2 ArewaSmart
    Route::get('/nin/validation', [NinValidationController::class, 'index'])->name('nin.validation.index');
    Route::post('/nin/validation/v1', [NinValidationController::class, 'storeV1'])->name('nin.validation.v1');
    Route::post('/nin/validation/v2', [NinValidationController::class, 'storeV2'])->name('nin.validation.v2');
    Route::post('/nin/validation/{validation}/check', [NinValidationController::class, 'checkStatus'])->name('nin.validation.check');

    // Legacy aliases (keep old routes working)
    Route::get('/validation', [NinValidationController::class, 'index'])->name('validation.index');
    Route::post('/validation', [NinValidationController::class, 'storeV1'])->name('validation.store');
    Route::post('/validation/{validation}/check', [NinValidationController::class, 'checkStatus'])->name('validation.check');
    Route::get('/nin-ipe-clearance', [NinIpeController::class, 'index'])->name('nin-ipe-clearance.index');
    Route::post('/nin-ipe-clearance', [NinIpeController::class, 'submitV1'])->name('nin-ipe-clearance.store');
    Route::post('/nin-ipe-clearance/{clearance}/check', [NinIpeController::class, 'checkStatus'])->name('nin-ipe-clearance.check');

    // BVN Modification Routes
    Route::get('/bvn-modification', [BvnModificationController::class, 'index'])->name('bvn-modification.index');
    Route::post('/bvn-modification', [BvnModificationController::class, 'store'])->name('bvn-modification.store');
    Route::get('/bvn-modification/requests', [BvnModificationController::class, 'requests'])->name('bvn-modification.requests');
    Route::get('/bvn-modification/requests/{modification}', [BvnModificationController::class, 'show'])->name('bvn-modification.show');
    Route::get('/bvn-modification/requests/{modification}/slip', [BvnModificationController::class, 'slip'])->name('bvn-modification.slip');

    // BVN SDK Onboarding Routes
    Route::get('/bvn-sdk-form', [BvnSdkFormController::class, 'index'])->name('bvn-sdk-form.index');
    Route::post('/bvn-sdk-form', [BvnSdkFormController::class, 'store'])->name('bvn-sdk-form.store');
    Route::get('/bvn-sdk-form/submissions', [BvnSdkFormController::class, 'submissions'])->name('bvn-sdk-form.submissions');
    Route::get('/bvn-sdk-form/submissions/{form}', [BvnSdkFormController::class, 'show'])->name('bvn-sdk-form.show');

    // BVN Retrieval Routes
    Route::get('/bvn-retrieval', [BvnRetrievalController::class, 'index'])->name('bvn-retrieval.index');
    Route::post('/bvn-retrieval', [BvnRetrievalController::class, 'store'])->name('bvn-retrieval.store');

    // BVN Search (verify + slip) Routes — v1 & v2 providers
    Route::get('/bvn-search', [BvnSearchController::class, 'index'])->name('bvn-search.index');
    Route::post('/bvn-search/v1', [BvnSearchController::class, 'searchV1'])->name('bvn-search.v1');
    Route::post('/bvn-search/v2', [BvnSearchController::class, 'searchV2'])->name('bvn-search.v2');

    // ID Card Application Routes (form + own requests on one page)
    Route::get('/idcard', [IdCardController::class, 'index'])->name('idcard.index');
    Route::post('/idcard', [IdCardController::class, 'store'])->name('idcard.store');
    Route::get('/idcard/{idCard}/image', [IdCardController::class, 'image'])->name('idcard.image');

    // BVN Enrolment Records search (Ticket ID / Agent ID)
    Route::get('/bvn-records', [BvnRecordController::class, 'index'])->name('bvn-records.index');

    // Notifications (read / dismiss — list is shared via Inertia props)
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/{notification}/dismiss', [NotificationController::class, 'dismiss'])->name('notifications.dismiss');

    // Help & Support (contact info + message form)
    Route::get('/help', [HelpController::class, 'index'])->name('help.index');
    Route::post('/help', [HelpController::class, 'submit'])->name('help.submit');

    // API Access — where a reseller collects the token they integrate with.
    Route::get('/api-access', [ApiAccessController::class, 'index'])->name('api-access.index');
    Route::post('/api-access/token', [ApiAccessController::class, 'regenerate'])->name('api-access.regenerate');

    // Report Routes (ported from nimcweb "Transactions"/"Reports" sidebar groups)
    Route::get('/reports/data-transactions', [ReportController::class, 'dataTransactions'])->name('reports.data-transactions');
    Route::get('/reports/nin-bvn-transactions', [ReportController::class, 'verifyTransactions'])->name('reports.verify-transactions');
    Route::get('/reports/data-stats', [ReportController::class, 'dataStats'])->name('reports.data-stats');
    Route::get('/reports/nin-bvn-stats', [ReportController::class, 'verifyStats'])->name('reports.verify-stats');
});

// Admin routes moved to admin.php.
// The old JSON data-plan / vendor-selection admin endpoints were removed with
// the denormalized vendor schema; the normalized admin surface is rebuilt in
// Phase 2 (see plan).

require __DIR__.'/admin.php';
require __DIR__.'/auth.php';

// Public API documentation for external integrators. Deliberately outside the
// auth group: the people wiring up an integration are often reading it before
// they have an account, and it contains no account-specific data.
Route::get('/developers', fn () => Inertia::render('Developers/Index', [
    'endpoint' => url('/api/v1'),
]))->name('developers');

// Public data price list. Outside the auth group so it can be shared and
// linked to, but priced for the viewer when they are signed in -- an agent
// sees agent rates, a visitor sees retail.
Route::get('/data-pricing', [DataPricingController::class, 'index'])->name('data-pricing');
