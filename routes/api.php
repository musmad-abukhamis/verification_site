<?php

use App\Http\Controllers\Api\NinVerificationController;
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
});

// Public health check endpoint
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'message' => 'API is running']);
})->name('api.health');