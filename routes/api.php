<?php

use App\Http\Controllers\Api\BillstackWebhookController;
use App\Http\Controllers\Api\NinVerificationController;
use App\Http\Controllers\Api\Nin\PremblyController;
use App\Http\Controllers\Api\Nin\ArewaSmartController;
use App\Http\Controllers\Api\Nin\ProviderThreeController;
use App\Http\Controllers\Api\Nin\ProviderFourController;
use App\Http\Controllers\Api\Nin\ProviderFiveController;
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
    Route::post('/nin/verify_1', [NinVerificationController::class, 'verifyProvider1'])
        ->name('api.nin.verify1');
    
    // NIN Verification - Provider 2 (ArewaSmart)
    Route::post('/nin/verify_2', [NinVerificationController::class, 'verifyProvider2'])
        ->name('api.nin.verify2');
    
    // NIN Demo Verification
    Route::post('/nin/demo', [NinVerificationController::class, 'verifyDemo'])
        ->name('api.nin.demo');
    
    // NIN Phone Verification
    Route::post('/nin/phone', [NinVerificationController::class, 'verifyPhone'])
        ->name('api.nin.phone');
    
    // IPE Submission - Provider 1 (Nguru)
    Route::post('/nin/ipe', [NinVerificationController::class, 'submitIpeProvider1'])
        ->name('api.nin.ipe1');
    
    // IPE Submission - Provider 2 (ArewaSmart)
    Route::post('/nin/ipe2', [NinVerificationController::class, 'submitIpeProvider2'])
        ->name('api.nin.ipe2');
    
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
        Route::post('/prembly/verify',    [PremblyController::class, 'verify'])->name('prembly');
        Route::post('/arewasmart/verify', [ArewaSmartController::class, 'verify'])->name('arewasmart');
        Route::post('/provider3/verify',  [ProviderThreeController::class, 'verify'])->name('provider3');
        Route::post('/provider4/verify',  [ProviderFourController::class, 'verify'])->name('provider4');
        Route::post('/provider5/verify',  [ProviderFiveController::class, 'verify'])->name('provider5');
    });
});

// Data purchases for API-role resellers. Authenticated by the app-managed
// apitoken string (no Sanctum PAT infra), gated to role = API.
Route::middleware('api.token')->prefix('v1')->group(function () {
    Route::post('/data', [\App\Http\Controllers\Api\DataController::class, 'store'])->name('api.data.store');
    Route::get('/data/{reference}', [\App\Http\Controllers\Api\DataController::class, 'show'])->name('api.data.show');
});

// Billstack reserved-account funding webhook (signed with x-wiaxy-signature).
Route::post('/webhooks/billstack', [BillstackWebhookController::class, 'handle'])
    ->name('api.webhooks.billstack');

// Public health check endpoint
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'message' => 'API is running']);
})->name('api.health');