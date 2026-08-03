<?php

namespace Tests\Feature;

use App\Models\ServicePrice;
use App\Models\User;
use App\Models\VerificationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\ConfiguresVerificationProviders;
use Tests\TestCase;

/**
 * The warning the verification pages show when failed verifications are billed.
 *
 * A user who is about to be charged for a "no such record" answer has to be
 * told before they submit, and a user who is not must not see a scary notice —
 * so both directions of the admin toggle are asserted, on every page that can
 * trigger the fee.
 */
class FailedChargeNoticeTest extends TestCase
{
    use ConfiguresVerificationProviders;
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['balance' => 10000])->save();

        foreach (['bvn.search.premium' => 100, 'nin.verify' => 100] as $service => $price) {
            ServicePrice::updateOrCreate(
                ['service' => $service, 'role' => ServicePrice::BASE],
                ['price' => $price, 'is_active' => true],
            );
        }

        ServicePrice::forgetCache();

        return $user;
    }

    /** Every route whose page renders the notice, and the toggle that drives it. */
    public static function pages(): array
    {
        return [
            'NIN verification' => ['/nin/verify', 'nin'],
            'BVN verification' => ['/bvn-verify', 'bvn'],
            'legacy NIN page' => ['/verification/nin', 'nin'],
            'legacy BVN page' => ['/verification/bvn', 'bvn'],
        ];
    }

    /**
     * @dataProvider pages
     */
    public function test_notice_is_sent_when_charging_is_on(string $url, string $type): void
    {
        VerificationSetting::put("failed_charge_{$type}_enabled", true);
        VerificationSetting::put("failed_charge_{$type}_amount", '75.50');

        $this->actingAs($this->user())
            ->get($url)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('failedChargeNotice.identity_type', $type)
                ->where('failedChargeNotice.amount', 75.5)
            );
    }

    /**
     * @dataProvider pages
     */
    public function test_notice_is_absent_when_charging_is_off(string $url, string $type): void
    {
        VerificationSetting::put("failed_charge_{$type}_enabled", false);
        VerificationSetting::put("failed_charge_{$type}_amount", '75.50');

        $this->actingAs($this->user())
            ->get($url)
            ->assertInertia(fn (AssertableInertia $page) => $page->where('failedChargeNotice', null));
    }

    /**
     * The toggle can be on with nothing configured behind it. A notice that
     * cannot name a figure is worse than none, so that counts as off.
     *
     * @dataProvider pages
     */
    public function test_notice_is_absent_when_the_amount_is_zero(string $url, string $type): void
    {
        VerificationSetting::put("failed_charge_{$type}_enabled", true);
        VerificationSetting::put("failed_charge_{$type}_amount", '0');

        $this->actingAs($this->user())
            ->get($url)
            ->assertInertia(fn (AssertableInertia $page) => $page->where('failedChargeNotice', null));
    }

    /** The NIN toggle must not leak onto the BVN page, or vice versa. */
    public function test_the_two_toggles_are_independent(): void
    {
        VerificationSetting::put('failed_charge_nin_enabled', true);
        VerificationSetting::put('failed_charge_nin_amount', '50');
        VerificationSetting::put('failed_charge_bvn_enabled', false);
        VerificationSetting::put('failed_charge_bvn_amount', '50');

        $user = $this->user();

        $this->actingAs($user)
            ->get('/nin/verify')
            // 50 not 50.0: JSON has no float/int distinction, so a whole-naira
            // fee comes back as an int. The page does Number() on it anyway.
            ->assertInertia(fn (AssertableInertia $page) => $page->where('failedChargeNotice.amount', 50));

        $this->actingAs($user)
            ->get('/bvn-verify')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('failedChargeNotice', null));
    }
}
