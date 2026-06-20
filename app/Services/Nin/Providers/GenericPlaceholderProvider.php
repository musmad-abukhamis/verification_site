<?php

namespace App\Services\Nin\Providers;

use App\Services\Nin\VerificationResult;

/**
 * Template for not-yet-integrated providers (Provider 3, 4, 5).
 *
 * Each concrete placeholder only sets its key. When you have the real API
 * spec, copy this class to a dedicated provider (e.g. VerifyMeProvider),
 * implement the real endpoints/payload mapping like PremblyProvider does,
 * then register it in NinProviderManager.
 *
 * Until activated (config `active => true` + a base_url), these return a
 * clear "not configured" result and never charge the wallet.
 */
abstract class GenericPlaceholderProvider extends AbstractNinProvider
{
    public function verifyByNin(string $nin): VerificationResult
    {
        return $this->notImplemented('nin');
    }

    public function verifyByPhone(string $phone): VerificationResult
    {
        return $this->notImplemented('phone');
    }

    public function verifyByDemographic(array $demographic): VerificationResult
    {
        return $this->notImplemented('demographic');
    }

    protected function notImplemented(string $method): VerificationResult
    {
        // attempt() already guards isActive(); this covers the "active but
        // integration not written yet" case.
        return $this->attempt($method, function () use ($method) {
            return VerificationResult::failure(
                "Integration for {$this->label()} ({$method}) has not been implemented yet.",
                'not_implemented',
                501,
            );
        });
    }
}
