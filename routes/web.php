<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DataPlanController;
use App\Http\Controllers\Admin\DataPlanApiController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataPurchaseController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VtuController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\NIN\VerifyController as NinVerifyController;
use App\Http\Controllers\NIN\PhoneVerifyController as NinPhoneVerifyController;
use App\Http\Controllers\NIN\DemoVerifyController as NinDemoVerifyController;
use App\Http\Controllers\NIN\IpeController as NinIpeController;
use App\Http\Controllers\NIN\ValidationController as NinValidationController;
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

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // VTU Routes
    Route::get('/vtu/airtime', [VtuController::class, 'airtime'])->name('vtu.airtime');
    Route::post('/vtu/airtime', [VtuController::class, 'purchaseAirtime'])->name('vtu.airtime.purchase');
    Route::get('/vtu/data', [VtuController::class, 'data'])->name('vtu.data');
    Route::post('/vtu/data', [VtuController::class, 'purchaseData'])->name('vtu.data.purchase');
    Route::post('/vtu/verify-phone', [VtuController::class, 'verifyPhone'])->name('vtu.verify-phone');
    
    // Buy Data Routes
    Route::get('/buy-data', [DataPurchaseController::class, 'index'])->name('buy-data');
    Route::post('/buy-data', [DataPurchaseController::class, 'buyData'])->name('buy-data.purchase');
    
    // API Routes for Buy Data
    Route::get('/api/plans/{network}', [VtuController::class, 'getDataTypes']);
    Route::get('/api/plans/{network}/{type}', [VtuController::class, 'getDataPlans']);
    
    // Verification Routes
    Route::get('/verification/nin', [VerificationController::class, 'nin'])->name('verification.nin');
    Route::post('/verification/nin', [VerificationController::class, 'verifyNin'])->name('verification.nin.verify');
    Route::get('/verification/bvn', [VerificationController::class, 'bvn'])->name('verification.bvn');
    Route::post('/verification/bvn', [VerificationController::class, 'verifyBvn'])->name('verification.bvn.verify');
    Route::get('/verification/history', [VerificationController::class, 'history'])->name('verification.history');

    // Wallet Routes
    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::get('/wallet/fund', [WalletController::class, 'fund'])->name('wallet.fund');
    Route::get('/wallet/transactions', [WalletController::class, 'transactions'])->name('wallet.transactions');

    // NIN Verification Routes (by NIN number) — v1 Prembly, v2 ArewaSmart
    Route::get('/nin/verify', [NinVerifyController::class, 'index'])->name('nin.verify.index');
    Route::post('/nin/verify/v1', [NinVerifyController::class, 'verifyV1'])->name('nin.verify.v1');
    Route::post('/nin/verify/v2', [NinVerifyController::class, 'verifyV2'])->name('nin.verify.v2');

    // NIN Phone Verification Routes — v1 ArewaSmart
    Route::get('/nin/phone', [NinPhoneVerifyController::class, 'index'])->name('nin.phone.index');
    Route::post('/nin/phone/v1', [NinPhoneVerifyController::class, 'verifyV1'])->name('nin.phone.v1');
    Route::post('/nin/phone/v2', [NinPhoneVerifyController::class, 'verifyV2'])->name('nin.phone.v2');

    // NIN Demo Verification Routes — v1 ArewaSmart
    Route::get('/nin/demo', [NinDemoVerifyController::class, 'index'])->name('nin.demo.index');
    Route::post('/nin/demo/v1', [NinDemoVerifyController::class, 'verifyV1'])->name('nin.demo.v1');
    Route::post('/nin/demo/v2', [NinDemoVerifyController::class, 'verifyV2'])->name('nin.demo.v2');

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
});

// Admin routes moved to admin.php

Route::prefix('api/admin')->middleware(['auth', 'admin'])->group(function () {
    Route::get('dataplan', [DataPlanApiController::class, 'getAll']);
    Route::get('dataplan/{id}', [DataPlanApiController::class, 'getById']);
    
    // Vendor Selection API Routes
    Route::get('vendor-selection/{networkId}', [App\Http\Controllers\Admin\VendorSelectionController::class, 'getSelection']);
    Route::post('vendor-selection', [App\Http\Controllers\Admin\VendorSelectionController::class, 'saveSelection']);
});

require __DIR__.'/admin.php';
require __DIR__.'/auth.php';