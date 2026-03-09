<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DataManagementController;
use App\Http\Controllers\Admin\NetworkController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\Admin\DataPlanController;
use App\Http\Controllers\Admin\VendorSelectionController;
use App\Http\Controllers\Admin\VerificationLogController;
use App\Http\Controllers\Admin\ServicePriceController;
use App\Http\Controllers\Admin\NinValidationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // User Management
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::patch('/users/{user}/toggle-admin', [UserController::class, 'toggleAdmin'])->name('users.toggle-admin');
    Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::post('/users/{user}/wallet/credit', [UserController::class, 'creditWallet'])->name('users.wallet.credit');
    Route::post('/users/{user}/wallet/debit', [UserController::class, 'debitWallet'])->name('users.wallet.debit');
    
    // Transaction Management
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    
    // Verification Logs
    Route::get('/verification-logs', [VerificationLogController::class, 'index'])->name('verification-logs.index');
    
    // Settings
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::patch('/settings', [SettingController::class, 'update'])->name('settings.update');
    
    // Data Management Routes
    Route::get('/data-management', [DataManagementController::class, 'index'])->name('data-management.index');
    Route::get('/data-management/plans', [DataManagementController::class, 'plans'])->name('data-management.plans');
    Route::get('/data-management/networks', [DataManagementController::class, 'networks'])->name('data-management.networks');
    Route::get('/data-management/transactions', [DataManagementController::class, 'transactions'])->name('data-management.transactions');
    
    // Vendor Management Routes
    Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
    Route::get('/vendors/create', [VendorController::class, 'create'])->name('vendors.create');
    Route::post('/vendors', [VendorController::class, 'store'])->name('vendors.store');
    
    // Vendor API Configuration - MUST be before /vendors/{vendor}
    Route::get('/vendors/api', [VendorController::class, 'apiConfig'])->name('vendors.api');
    Route::put('/vendors/api', [VendorController::class, 'updateApiConfig'])->name('vendors.api.update');
    
    // Active Vendors Configuration - MUST be before /vendors/{vendor}
    Route::get('/vendors/active', [VendorController::class, 'activeVendors'])->name('vendors.active');
    Route::post('/vendors/active', [VendorController::class, 'updateActiveVendors'])->name('vendors.active.update');
    
    // Dynamic vendor routes - MUST be after specific routes
    Route::get('/vendors/{vendor}', [VendorController::class, 'show'])->name('vendors.show');
    Route::get('/vendors/{vendor}/edit', [VendorController::class, 'edit'])->name('vendors.edit');
    Route::put('/vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');
    Route::delete('/vendors/{vendor}', [VendorController::class, 'destroy'])->name('vendors.destroy');
    
    // Data Plan Management Routes
    Route::resource('dataplan', DataPlanController::class);
    
    // Vendor Selection Routes
    Route::get('dvnsel', [VendorSelectionController::class, 'index'])->name('vendor.selection');
    
    // Network ID Configuration Routes
    Route::get('/networkid', [NetworkController::class, 'index'])->name('networkid.index');
    Route::get('/networkid/{network}/edit', [NetworkController::class, 'edit'])->name('networkid.edit');
    Route::post('/networks', [NetworkController::class, 'store'])->name('networks.store');
    
    // Service Prices & Slip Types Management
    Route::get('/service-prices', [ServicePriceController::class, 'index'])->name('service-prices.index');
    Route::put('/service-prices/{servicePrice}', [ServicePriceController::class, 'updateServicePrice'])->name('service-prices.update');
    Route::post('/slip-types', [ServicePriceController::class, 'storeSlipType'])->name('slip-types.store');
    Route::put('/slip-types/{slipType}', [ServicePriceController::class, 'updateSlipType'])->name('slip-types.update');
    Route::delete('/slip-types/{slipType}', [ServicePriceController::class, 'destroySlipType'])->name('slip-types.destroy');
    
    // NIN Validation Management
    Route::get('/nin-validations', [NinValidationController::class, 'index'])->name('nin-validations.index');
    Route::get('/nin-validations/{validation}', [NinValidationController::class, 'show'])->name('nin-validations.show');
    Route::get('/nin-validations-stats', [NinValidationController::class, 'stats'])->name('nin-validations.stats');
});