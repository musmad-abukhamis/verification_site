<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DataPlanController;
use App\Http\Controllers\Admin\DataRoutingController;
use App\Http\Controllers\Admin\DataTransactionController;
use App\Http\Controllers\Admin\DataWalletController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VerificationLogController;
use App\Http\Controllers\Admin\ServicePriceController;
use App\Http\Controllers\Admin\NinValidationController;
use App\Http\Controllers\Admin\BvnModificationController;
use App\Http\Controllers\Admin\BvnPriceController;
use App\Http\Controllers\Admin\BvnSdkFormController;
use App\Http\Controllers\Admin\BvnRetrievalController;
use App\Http\Controllers\Admin\BvnSearchController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UnattributedPaymentController;
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
    Route::post('/wallet/funding-settings', [WalletController::class, 'updateFundingSettings'])->name('wallet.funding-settings.update');

    // Payments that arrived without a matching user (reconciliation queue)
    Route::get('/wallet/unattributed', [UnattributedPaymentController::class, 'index'])->name('wallet.unattributed.index');
    Route::post('/wallet/unattributed/{unattributedPayment}/resolve', [UnattributedPaymentController::class, 'resolve'])->name('wallet.unattributed.resolve');
    Route::post('/wallet/unattributed/{unattributedPayment}/ignore', [UnattributedPaymentController::class, 'ignore'])->name('wallet.unattributed.ignore');
    Route::post('/wallet/unattributed/{unattributedPayment}/reopen', [UnattributedPaymentController::class, 'reopen'])->name('wallet.unattributed.reopen');

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
    
    // ---- Data module (normalized vendor-routing) ----

    // Vendors CRUD
    Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
    Route::post('/vendors', [VendorController::class, 'store'])->name('vendors.store');
    Route::put('/vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');
    Route::patch('/vendors/{vendor}/toggle', [VendorController::class, 'toggle'])->name('vendors.toggle');
    Route::delete('/vendors/{vendor}', [VendorController::class, 'destroy'])->name('vendors.destroy');

    // Plans CRUD (+ per-vendor external plan id mappings, both status toggles)
    Route::get('/dataplan', [DataPlanController::class, 'index'])->name('dataplan.index');
    Route::get('/dataplan/create', [DataPlanController::class, 'create'])->name('dataplan.create');
    Route::post('/dataplan', [DataPlanController::class, 'store'])->name('dataplan.store');
    Route::get('/dataplan/{dataplan}/edit', [DataPlanController::class, 'edit'])->name('dataplan.edit');
    Route::put('/dataplan/{dataplan}', [DataPlanController::class, 'update'])->name('dataplan.update');
    Route::patch('/dataplan/{dataplan}/toggle-status', [DataPlanController::class, 'toggleStatus'])->name('dataplan.toggle-status');
    Route::patch('/dataplan/{dataplan}/toggle-plan-status', [DataPlanController::class, 'togglePlanStatus'])->name('dataplan.toggle-plan-status');
    Route::delete('/dataplan/{dataplan}', [DataPlanController::class, 'destroy'])->name('dataplan.destroy');

    // Routing matrix + network codes + settings + prefixes
    Route::get('/data/routing', [DataRoutingController::class, 'index'])->name('data.routing.index');
    Route::put('/data/routing', [DataRoutingController::class, 'updateRoutes'])->name('data.routing.update');
    Route::put('/data/network-codes', [DataRoutingController::class, 'updateNetworkCodes'])->name('data.network-codes.update');
    Route::put('/data/settings', [DataRoutingController::class, 'updateSettings'])->name('data.settings.update');
    Route::post('/data/prefixes', [DataRoutingController::class, 'addPrefix'])->name('data.prefixes.add');
    Route::delete('/data/prefixes', [DataRoutingController::class, 'removePrefix'])->name('data.prefixes.remove');

    // Data transactions (all users) + attempt drill-down
    Route::get('/data-transactions', [DataTransactionController::class, 'index'])->name('data-transactions.index');
    Route::get('/data-transactions/{dataTransaction}', [DataTransactionController::class, 'show'])->name('data-transactions.show');

    // Manual wallet credit/debit through the data-module ledger
    Route::get('/data-wallet', [DataWalletController::class, 'index'])->name('data-wallet.index');
    Route::post('/data-wallet/{user}/credit', [DataWalletController::class, 'credit'])->name('data-wallet.credit');
    Route::post('/data-wallet/{user}/debit', [DataWalletController::class, 'debit'])->name('data-wallet.debit');

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
    Route::get('/enrollment-records/status', [EnrollmentRecordController::class, 'status'])->name('enrollment-records.status');

    // Notification Management (global + user-specific announcements)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications', [NotificationController::class, 'store'])->name('notifications.store');
    Route::put('/notifications/{notification}', [NotificationController::class, 'update'])->name('notifications.update');
    Route::patch('/notifications/{notification}/toggle', [NotificationController::class, 'toggle'])->name('notifications.toggle');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
});