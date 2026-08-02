<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Ipe;
use App\Models\ServicePrice;
use App\Models\User;
use App\Models\Validation;
use App\Models\VerificationEndpoint;
use App\Models\VerificationProvider;
use App\Models\VerificationRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * NIN Validation and IPE Clearance are jobs, not lookups: the submission only
 * files the work, and the result arrives days (validation) or hours (IPE) later
 * when the user presses Check.
 *
 * The behaviours pinned here are the ones the previous implementation got wrong
 * — it marked validations `completed` at submission time and invented a
 * completed IPE clearance whenever the status API was unreachable.
 */
class AsyncNinJobTest extends TestCase
{
    use RefreshDatabase;

    private function user(float $balance = 10000): User
    {
        $user = User::factory()->create();
        $user->forceFill(['balance' => $balance])->save();

        foreach (['nin.validation' => 150, 'nin.ipe' => 200] as $service => $price) {
            ServicePrice::updateOrCreate(
                ['service' => $service, 'role' => ServicePrice::BASE],
                ['price' => $price, 'is_active' => true],
            );
        }
        ServicePrice::forgetCache();

        return $user;
    }

    /**
     * A provider implementing a submit service and its status counterpart.
     *
     * @param  array<int, array{service: string, method?: string, path: string}>  $endpoints
     */
    private function provider(string $slug, string $baseUrl, array $endpoints, int $priority = 10): VerificationProvider
    {
        $provider = VerificationProvider::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'base_url' => $baseUrl,
            'auth_type' => 'header_key',
            'auth_config' => ['header_name' => 'api-key'],
            'credentials' => ['token' => 'k-'.$slug],
            'is_active' => true,
            'priority' => $priority,
            'timeout_seconds' => 30,
        ]);

        foreach ($endpoints as $endpoint) {
            VerificationEndpoint::create([
                'provider_id' => $provider->getKey(),
                'service' => $endpoint['service'],
                'http_method' => $endpoint['method'] ?? 'POST',
                'path' => $endpoint['path'],
                'body_type' => 'json',
                'success_rule' => ['path' => 'success'],
                'is_active' => true,
            ]);

            VerificationRoute::create([
                'service' => $endpoint['service'],
                'provider_id' => $provider->getKey(),
                'position' => $priority,
            ]);
        }

        return $provider;
    }

    private function validationProvider(string $slug = 'robost', int $priority = 10): VerificationProvider
    {
        return $this->provider($slug, 'https://robosttech.com/api', [
            ['service' => 'nin.validation', 'path' => '/validation'],
            ['service' => 'nin.validation.status', 'path' => '/validation_status'],
        ], $priority);
    }

    private function ipeProvider(string $slug = 'robost', int $priority = 10): VerificationProvider
    {
        return $this->provider($slug, 'https://robosttech.com/api', [
            ['service' => 'nin.ipe', 'path' => '/clearance'],
            ['service' => 'nin.ipe.status', 'path' => '/clearance_status'],
        ], $priority);
    }

    // ---------------------------------------------------------------- submit

    public function test_a_submitted_validation_is_parked_as_processing_not_completed(): void
    {
        $user = $this->user();
        $this->validationProvider();

        Http::fake(['robosttech.com/api/validation' => Http::response([
            'message' => 'Validation Submission Successfull',
            'approved' => true,
            'category' => 'new',
            'success' => true,
            'nin' => '18855414402',
        ], 200)]);

        $this->actingAs($user)
            ->post(route('nin.validation.store'), ['nin' => '18855414402'])
            ->assertSessionHasNoErrors();

        $record = Validation::first();

        // The acceptance reply is enthusiastic — "approved: true" — and still
        // means only that the request was created.
        $this->assertSame('processing', $record->status);
        $this->assertSame(150.0, $record->price);
        $this->assertNotNull($record->providerId);
        $this->assertSame(9850.0, (float) $user->fresh()->balance);
    }

    public function test_a_submitted_clearance_is_parked_as_processing(): void
    {
        $user = $this->user();
        $this->ipeProvider();

        Http::fake(['robosttech.com/api/clearance' => Http::response([
            'success' => true,
            'tracking_id' => '0RQ6C5ASWFS36LS',
        ], 200)]);

        $this->actingAs($user)
            ->post(route('nin.ipe.store'), ['trkid' => '0RQ6C5ASWFS36LS'])
            ->assertSessionHasNoErrors();

        $record = Ipe::first();

        $this->assertSame('processing', $record->status);
        $this->assertSame(200.0, $record->price);
        $this->assertSame(9800.0, (float) $user->fresh()->balance);
    }

    public function test_a_rejected_submission_is_refunded_and_marked_failed(): void
    {
        $user = $this->user();
        $this->ipeProvider();

        Http::fake(['robosttech.com/api/clearance' => Http::response([
            'success' => false,
            'tracking_id' => '0SN5NFZSGDEGZ0N',
            'message' => 'Previous Clearance Failed',
        ], 200)]);

        $this->actingAs($user)
            ->post(route('nin.ipe.store'), ['trkid' => '0SN5NFZSGDEGZ0N']);

        $record = Ipe::first();

        $this->assertSame('failed', $record->status);
        $this->assertSame(10000.0, (float) $user->fresh()->balance);
        // Refunded at submission, so the admin button must not pay again.
        $this->assertFalse($record->isRefundable());
    }

    // ---------------------------------------------------------- status check

    public function test_a_pending_status_reply_leaves_the_record_open(): void
    {
        $user = $this->user();
        $provider = $this->validationProvider();
        $record = $this->openValidation($user, $provider);

        Http::fake(['robosttech.com/api/validation_status' => Http::response([
            'success' => true,
            'nin' => '18855414402',
            'status' => 'processing',
            'comment' => 'nin: 18855414402, status: New, validationErrorType: NO RECORD FUND',
        ], 200)]);

        $this->actingAs($user)
            ->post(route('nin.validation.check', $record->id))
            ->assertSessionHasNoErrors();

        $this->assertSame('processing', $record->fresh()->status);
        $this->assertStringContainsString('NO RECORD FUND', $record->fresh()->comment);
    }

    public function test_a_completed_status_reply_closes_the_record(): void
    {
        $user = $this->user();
        $provider = $this->ipeProvider();
        $record = $this->openIpe($user, $provider);

        Http::fake(['robosttech.com/api/clearance_status' => Http::response([
            'message' => 'Clearance Successfull',
            'cleared' => true,
            'success' => true,
            'status' => 'completed',
            'tracking_id' => '0RQ6C5ASWFS36LU',
            'reply' => '2GVZ0SI8KO000VK',
        ], 200)]);

        $this->actingAs($user)->post(route('nin.ipe.status', $record->id));

        $fresh = $record->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertStringContainsString('2GVZ0SI8KO000VK', $fresh->result);
    }

    public function test_an_unreachable_provider_never_completes_a_clearance(): void
    {
        $user = $this->user();
        $provider = $this->ipeProvider();
        $record = $this->openIpe($user, $provider);

        // The exact failure the old code turned into "Clearance completed".
        Http::fake(['robosttech.com/api/clearance_status' => Http::response('gateway down', 502)]);

        $this->actingAs($user)
            ->post(route('nin.ipe.status', $record->id))
            ->assertSessionHasErrors('message');

        $this->assertSame('processing', $record->fresh()->status);
    }

    public function test_a_status_check_does_not_fail_over_to_another_provider(): void
    {
        $user = $this->user();
        $primary = $this->ipeProvider('robost', priority: 10);
        $this->provider('arewa', 'https://api.arewasmart.com.ng/api/v1', [
            ['service' => 'nin.ipe', 'path' => '/nin/ipe'],
            ['service' => 'nin.ipe.status', 'method' => 'GET', 'path' => '/nin/ipe'],
        ], priority: 20);

        $record = $this->openIpe($user, $primary);

        Http::fake([
            'robosttech.com/*' => Http::response(['success' => false, 'message' => 'Previous Clearance Failed'], 200),
            'api.arewasmart.com.ng/*' => Http::response(['success' => true, 'status' => 'completed'], 200),
        ]);

        $this->actingAs($user)->post(route('nin.ipe.status', $record->id));

        // The job lives with Robost. Asking ArewaSmart about it would return a
        // stranger's answer — here, a completion that never happened.
        $this->assertSame('failed', $record->fresh()->status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'arewasmart'));
    }

    public function test_a_finished_record_is_not_re_polled(): void
    {
        $user = $this->user();
        $provider = $this->ipeProvider();
        $record = $this->openIpe($user, $provider);
        $record->update(['status' => 'completed']);

        Http::fake();

        $this->actingAs($user)->post(route('nin.ipe.status', $record->id));

        Http::assertNothingSent();
    }

    // ----------------------------------------------------------------- admin

    public function test_an_admin_can_set_the_status_by_hand(): void
    {
        $user = $this->user();
        $record = $this->openValidation($user, $this->validationProvider());
        $balance = (float) $user->fresh()->balance;

        $this->actingAs($this->admin())
            ->put(route('admin.nin-validations.update', $record->id), [
                'status' => 'completed',
                'result' => 'Settled with the provider by phone',
                'comment' => 'Confirmed manually',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $record->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertSame('Confirmed manually', $fresh->comment);
        // A manual status change is not a refund.
        $this->assertSame($balance, (float) $user->fresh()->balance);
        $this->assertNull($fresh->refundedAt);
    }

    public function test_an_admin_refund_credits_the_wallet_once(): void
    {
        $user = $this->user();
        $record = $this->openValidation($user, $this->validationProvider());
        $balance = (float) $user->fresh()->balance;

        $this->actingAs($this->admin())
            ->post(route('admin.nin-validations.refund', $record->id), [
                'amount' => 150,
                'reason' => 'Provider could not validate',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($balance + 150, (float) $user->fresh()->balance);
        $this->assertNotNull($record->fresh()->refundedAt);

        // Second attempt, days later, by another admin.
        $this->actingAs($this->admin())
            ->post(route('admin.nin-validations.refund', $record->id), ['amount' => 150])
            ->assertSessionHasErrors('message');

        $this->assertSame($balance + 150, (float) $user->fresh()->balance);
    }

    public function test_a_refund_cannot_exceed_what_was_charged(): void
    {
        $user = $this->user();
        $record = $this->openValidation($user, $this->validationProvider());

        $this->actingAs($this->admin())
            ->post(route('admin.nin-validations.refund', $record->id), ['amount' => 5000])
            ->assertSessionHasErrors('amount');

        $this->assertNull($record->fresh()->refundedAt);
    }

    // --------------------------------------------------------------- helpers

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => UserRole::ADMIN])->save();

        return $admin;
    }

    private function openValidation(User $user, VerificationProvider $provider): Validation
    {
        return Validation::create([
            'nin' => '18855414402',
            'status' => 'processing',
            'result' => 'Pending',
            'comment' => 'Submitted',
            'oldBal' => 10000,
            'newBal' => 9850,
            'price' => 150,
            'providerId' => $provider->getKey(),
            'userId' => $user->id,
        ]);
    }

    private function openIpe(User $user, VerificationProvider $provider): Ipe
    {
        return Ipe::create([
            'trkid' => '0RQ6C5ASWFS36LU',
            'status' => 'processing',
            'result' => 'Pending',
            'comment' => 'Submitted',
            'oldBal' => 10000,
            'newBal' => 9800,
            'price' => 200,
            'providerId' => $provider->getKey(),
            'userId' => $user->id,
        ]);
    }
}
