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
use App\Http\Controllers\Admin\BvnModificationController;
use App\Http\Controllers\Admin\BvnPriceController;
use App\Http\Controllers\Admin\BvnSdkFormController;
use App\Http\Controllers\Admin\BvnRetrievalController;
use App\Http\Controllers\Admin\BvnSearchController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\Admin\AgentIdController;
use App\Http\Controllers\Admin\IdCardController;
use App\Http\Controllers\Admin\EnrollmentRecordController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\SiteSettingController;
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
    Route::post('/users/{user}/wallet/reset', [UserController::class, 'resetWallet'])->name('users.wallet.reset');
    Route::patch('/users/{user}/password', [UserController::class, 'changePassword'])->name('users.password');
    Route::post('/users/{user}/reset-2fa', [UserController::class, 'resetTwoFactor'])->name('users.reset-2fa');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    
    // Wallet Management (central funding + history)
    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/fund', [WalletController::class, 'fund'])->name('wallet.fund');
    Route::get('/wallet/transactions', [WalletController::class, 'transactions'])->name('wallet.transactions');

    // Transaction Management
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    
    // Verification Logs
    Route::get('/verification-logs', [VerificationLogController::class, 'index'])->name('verification-logs.index');

    // Reports (ported from nimcweb admin "Reports"/"Transactions" groups)
    Route::get('/reports/nin-bvn-transactions', [ReportController::class, 'verifyTransactions'])->name('reports.verify-transactions');
    Route::get('/reports/data-stats', [ReportController::class, 'dataStats'])->name('reports.data-stats');
    Route::get('/reports/verify-stats', [ReportController::class, 'verifyStats'])->name('reports.verify-stats');

    // Settings (pricing / verification methods)
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::patch('/settings', [SettingController::class, 'update'])->name('settings.update');

    // Site Settings (site info + contact details → powers Help & Support)
    Route::get('/site-settings', [SiteSettingController::class, 'index'])->name('site-settings.index');
    Route::put('/site-settings', [SiteSettingController::class, 'update'])->name('site-settings.update');
    
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

    // BVN Service Prices
    Route::get('/bvn-prices', [BvnPriceController::class, 'index'])->name('bvn-prices.index');
    Route::put('/bvn-prices', [BvnPriceController::class, 'update'])->name('bvn-prices.update');

    // BVN Modification Management
    Route::get('/bvn-modifications', [BvnModificationController::class, 'index'])->name('bvn-modifications.index');
    Route::get('/bvn-modifications/{modification}', [BvnModificationController::class, 'show'])->name('bvn-modifications.show');
    Route::patch('/bvn-modifications/{modification}/status', [BvnModificationController::class, 'updateStatus'])->name('bvn-modifications.status');
    Route::delete('/bvn-modifications/{modification}', [BvnModificationController::class, 'destroy'])->name('bvn-modifications.destroy');

    // BVN SDK Onboarding Management
    Route::get('/bvn-sdk-forms', [BvnSdkFormController::class, 'index'])->name('bvn-sdk-forms.index');
    Route::get('/bvn-sdk-forms/{form}', [BvnSdkFormController::class, 'show'])->name('bvn-sdk-forms.show');
    Route::patch('/bvn-sdk-forms/{form}/status', [BvnSdkFormController::class, 'updateStatus'])->name('bvn-sdk-forms.status');
    Route::delete('/bvn-sdk-forms/{form}', [BvnSdkFormController::class, 'destroy'])->name('bvn-sdk-forms.destroy');

    // BVN Retrieval Management
    Route::get('/bvn-retrievals', [BvnRetrievalController::class, 'index'])->name('bvn-retrievals.index');
    Route::patch('/bvn-retrievals/{retrieval}', [BvnRetrievalController::class, 'update'])->name('bvn-retrievals.update');
    Route::delete('/bvn-retrievals/{retrieval}', [BvnRetrievalController::class, 'destroy'])->name('bvn-retrievals.destroy');

    // BVN Search Logs (read-only)
    Route::get('/bvn-searches', [BvnSearchController::class, 'index'])->name('bvn-searches.index');

    // Agent ID card generator (client-side PDF, no DB)
    Route::get('/agent-id', [AgentIdController::class, 'index'])->name('agent-id.index');

    // ID Card Application Management (review / status / delete)
    Route::get('/idcard', [IdCardController::class, 'index'])->name('idcard.index');
    Route::patch('/idcard/{idCard}/status', [IdCardController::class, 'updateStatus'])->name('idcard.status');
    Route::delete('/idcard/{idCard}', [IdCardController::class, 'destroy'])->name('idcard.destroy');

    // Enrollment Records (spreadsheet upload → upsert into Record)
    Route::get('/enrollment-records', [EnrollmentRecordController::class, 'index'])->name('enrollment-records.index');
    Route::post('/enrollment-records/upload', [EnrollmentRecordController::class, 'upload'])->name('enrollment-records.upload');

    // Notification Management (global + user-specific announcements)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications', [NotificationController::class, 'store'])->name('notifications.store');
    Route::put('/notifications/{notification}', [NotificationController::class, 'update'])->name('notifications.update');
    Route::patch('/notifications/{notification}/toggle', [NotificationController::class, 'toggle'])->name('notifications.toggle');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
});