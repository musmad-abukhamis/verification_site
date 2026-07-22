<?php

use App\Http\Controllers\Api\BillstackWebhookController;
use App\Http\Controllers\Api\PayVesselWebhookController;
use App\Http\Controllers\Api\NinVerificationController;
use App\Http\Controllers\Api\Nin\RoutedController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// NIN Verification API Routes - Protected by Sanctum
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    
    // NIN Verification - Provider 1 (Prembly)
    Route::post('/nin/verify', [NinVerificationController::class, 'verify'])
        ->name('api.nin.verify.routed');

    // NIN Demo Verification
    Route::post('/nin/demo', [NinVerificationController::class, 'verifyDemo'])
        ->name('api.nin.demo');
    
    // NIN Phone Verification
    Route::post('/nin/phone', [NinVerificationController::class, 'verifyPhone'])
        ->name('api.nin.phone');
    
    // IPE Submission
    Route::post('/nin/ipe', [NinVerificationController::class, 'submitIpe'])
        ->name('api.nin.ipe.submit');

    // Get All IPE Submissions
    Route::get('/nin/ipe', [NinVerificationController::class, 'getAllIpeSubmissions'])
        ->name('api.nin.ipe.all');
    
    // Check IPE Status (ArewaSmart)
    Route::get('/nin/ipe/arewa/status', [NinVerificationController::class, 'checkIpeStatus'])
        ->name('api.nin.ipe.status');

    /*
    |------------------------------------------------------------------
    | Modular multi-provider NIN verification
    |------------------------------------------------------------------
    | One dedicated endpoint per provider. The verification method
    | (nin|phone|demographic) is supplied in the request body.
    | Add a new provider by adding one line here + its controller.
    */
    Route::prefix('nin/providers')->name('api.nin.providers.')->group(function () {
        // One endpoint. The per-provider routes (prembly, arewasmart,
        // provider3..5) are gone: choosing a provider is the routing chain's
        // job now, and letting a caller name one would bypass failover.
        Route::post('/auto/verify', [RoutedController::class, 'verify'])->name('auto');
    });
});

/*
|--------------------------------------------------------------------------
| Reseller API
|--------------------------------------------------------------------------
| For external sites integrating our services. Authenticated by the
| app-managed apitoken string (no Sanctum PAT infra), gated to role = API.
| Every endpoint charges the caller's wallet at their role's price.
|
| Documented at /developers.
*/
Route::middleware('api.token')->prefix('v1')->group(function () {
    Route::get('/balance', [\App\Http\Controllers\Api\Reseller\AccountController::class, 'balance'])->name('api.balance');
    Route::get('/services', [\App\Http\Controllers\Api\Reseller\AccountController::class, 'services'])->name('api.services');

    Route::get('/plans', [\App\Http\Controllers\Api\DataController::class, 'plans'])->name('api.data.plans');
    Route::post('/data', [\App\Http\Controllers\Api\DataController::class, 'store'])->name('api.data.store');
    Route::get('/data/{reference}', [\App\Http\Controllers\Api\DataController::class, 'show'])->name('api.data.show');

    Route::get('/nin/providers', [\App\Http\Controllers\Api\Reseller\NinController::class, 'providers'])->name('api.nin.providers');
    Route::post('/nin/verify', [\App\Http\Controllers\Api\Reseller\NinController::class, 'verify'])->name('api.nin.verify');

    Route::post('/bvn/verify', [\App\Http\Controllers\Api\Reseller\BvnController::class, 'verify'])->name('api.bvn.verify');
});

// PayVessel static virtual-account funding webhook.
// Signed HMAC-SHA512 over the raw body (Payvessel-Http-Signature) and
// restricted to PayVessel's source addresses.
Route::post('/webhooks/payvessel', [PayVesselWebhookController::class, 'handle'])
    ->name('api.webhooks.payvessel');

// Billstack reserved-account funding webhook (signed with x-wiaxy-signature).
// PayVessel has replaced Billstack for new accounts; this stays so any
// already-issued Billstack account is still credited.
Route::post('/webhooks/billstack', [BillstackWebhookController::class, 'handle'])
    ->name('api.webhooks.billstack');

// Public health check endpoint
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'message' => 'API is running']);
})->name('api.health');