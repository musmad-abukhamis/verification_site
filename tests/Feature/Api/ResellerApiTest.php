<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\ServicePrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConfiguresVerificationProviders;
use Tests\TestCase;

/**
 * The reseller API: external sites calling us with an apitoken, billed to their
 * wallet at their role's rate.
 */
class ResellerApiTest extends TestCase
{
    use ConfiguresVerificationProviders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Verification has no hardcoded providers any more, so a routed chain
        // is a precondition for any lookup reaching a provider at all.
        $this->routeProviderFor(['nin.verify', 'nin.phone', 'nin.demographic', 'nin.ipe', 'bvn.verify']);
    }

    private const TOKEN = 'sk_live_reseller_token';

    private function reseller(float $balance = 5000): User
    {
        return User::factory()->create([
            'role' => UserRole::API,
            'apitoken' => self::TOKEN,
            'balance' => $balance,
        ]);
    }

    private function price(string $service, float $price, string $role = ServicePrice::BASE): void
    {
        ServicePrice::forgetCache();
        ServicePrice::updateOrCreate(
            ['service' => $service, 'role' => $role],
            ['price' => $price, 'is_active' => true],
        );
        ServicePrice::forgetCache();
    }

    private function apiCall(string $method, string $uri, array $body = [])
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer '.self::TOKEN,
            'Accept' => 'application/json',
        ])->json($method, $uri, $body);
    }

    protected function tearDown(): void
    {
        ServicePrice::forgetCache();

        parent::tearDown();
    }

    public function test_a_request_without_a_token_is_rejected(): void
    {
        $this->reseller();

        $this->json('GET', '/api/v1/balance')->assertStatus(401);
    }

    public function test_a_non_api_role_cannot_use_the_token(): void
    {
        User::factory()->create(['role' => UserRole::USER, 'apitoken' => self::TOKEN]);

        $this->apiCall('GET', '/api/v1/balance')->assertStatus(401);
    }

    public function test_it_returns_the_wallet_balance(): void
    {
        $this->reseller(2750);

        $this->apiCall('GET', '/api/v1/balance')
            ->assertOk()
            ->assertJsonPath('data.balance', 2750)
            ->assertJsonPath('data.currency', 'NGN');
    }

    /**
     * The price list must be the caller's own rate, not the base rate -- that is
     * the whole point of giving resellers a role.
     */
    public function test_the_service_list_shows_the_callers_own_rates(): void
    {
        $this->reseller();
        $this->price('nin.verify', 100);
        $this->price('nin.verify', 40, UserRole::API->value);

        $response = $this->apiCall('GET', '/api/v1/services')->assertOk();

        $nin = collect($response->json('data.services'))->firstWhere('service', 'nin.verify');

        $this->assertSame(40, $nin['price']);
        $this->assertTrue($nin['available']);
        $this->assertSame('API', $response->json('data.role'));
    }

    public function test_a_switched_off_service_is_listed_as_unavailable(): void
    {
        $this->reseller();
        ServicePrice::where('service', 'nin.verify')->update(['is_active' => false]);
        ServicePrice::forgetCache();

        $response = $this->apiCall('GET', '/api/v1/services')->assertOk();
        $nin = collect($response->json('data.services'))->firstWhere('service', 'nin.verify');

        $this->assertFalse($nin['available']);
        $this->assertNull($nin['price']);
    }

    public function test_a_nin_verification_charges_the_api_rate(): void
    {
        $user = $this->reseller(1000);
        $this->price('nin.verify', 100);
        $this->price('nin.verify', 40, UserRole::API->value);

        Http::fake(['*' => Http::response(['nin' => '12345678901', 'firstname' => 'JOHN'])]);

        $this->apiCall('POST', '/api/v1/nin/verify', ['nin' => '12345678901'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(960.0, (float) $user->fresh()->balance);
    }

    /**
     * The documented payload is the full enrolment record, normalized and
     * cleaned. If this shrinks, every integrator's parser silently loses fields.
     */
    public function test_a_nin_verification_returns_the_full_normalized_record(): void
    {
        $this->reseller(1000);
        $this->price('nin.verify', 40);

        Http::fake(['*' => Http::response([
            'nin' => '12345678901',
            'firstname' => 'JOHN',
            'middlename' => 'ADE',
            'surname' => 'DOE',
            'gender' => 'm',
            'birthdate' => '21-05-1990',
            'telephoneno' => '+2348012345678',
            'email' => 'john.doe@example.com',
            'address' => '12 BROAD STREET',
            'residencestate' => 'LAGOS',
            'self_origin_state' => 'KANO',
            'nokfirstname' => 'JANE',
            'photo' => 'data:image/jpeg;base64,AAAA',
        ])]);

        $response = $this->apiCall('POST', '/api/v1/nin/verify', ['nin' => '12345678901'])->assertOk();

        $response
            ->assertJsonPath('data.first_name', 'JOHN')
            ->assertJsonPath('data.middle_name', 'ADE')
            ->assertJsonPath('data.last_name', 'DOE')
            ->assertJsonPath('data.full_name', 'JOHN ADE DOE')
            ->assertJsonPath('data.email', 'john.doe@example.com')
            ->assertJsonPath('data.residence_address', '12 BROAD STREET')
            ->assertJsonPath('data.residence_state', 'LAGOS')
            ->assertJsonPath('data.state_of_origin', 'KANO')
            ->assertJsonPath('data.nok_first_name', 'JANE')
            // Cleaned on the way out, whatever spelling the provider used.
            ->assertJsonPath('data.gender', 'MALE')
            ->assertJsonPath('data.date_of_birth', '1990-05-21')
            ->assertJsonPath('data.phone', '08012345678')
            ->assertJsonPath('data.photo', 'AAAA')
            // The documented compatibility aliases.
            ->assertJsonPath('data.surname', 'DOE')
            ->assertJsonPath('data.firstname', 'JOHN')
            ->assertJsonPath('data.dob', '1990-05-21');

        $this->assertArrayHasKey('validation_id', $response->json('data'));
    }

    /**
     * The provider reports the enrolling bank as a CBN code; an integrator (and
     * a printed slip) needs the bank.
     */
    public function test_a_bvn_record_names_the_enrollment_bank(): void
    {
        $this->reseller(1000);
        $this->price('bvn.search.premium', 150);

        Http::fake(['*' => Http::response([
            'status' => 'success',
            'data' => [
                'bvn' => '22345678901',
                'lastName' => 'DOE',
                'enrollmentBank' => '011',
                'enrollmentBranch' => '058',
            ],
        ])]);

        $this->apiCall('POST', '/api/v1/bvn/verify', ['bvn' => '22345678901'])
            ->assertOk()
            ->assertJsonPath('data.enrollment_bank', 'First Bank of Nigeria Plc')
            // Same table by design: the branch field carries an institution code.
            ->assertJsonPath('data.enrollment_bank_branch', 'Guaranty Trust Bank Plc');
    }

    /**
     * A branch already sent as text is a name, not a code, so it survives.
     */
    public function test_a_named_enrollment_branch_is_left_alone(): void
    {
        $this->reseller(1000);
        $this->price('bvn.search.premium', 150);

        Http::fake(['*' => Http::response([
            'status' => 'success',
            'data' => [
                'bvn' => '22345678901',
                'lastName' => 'DOE',
                'enrollmentBranch' => 'IKEJA',
            ],
        ])]);

        $this->apiCall('POST', '/api/v1/bvn/verify', ['bvn' => '22345678901'])
            ->assertOk()
            ->assertJsonPath('data.enrollment_bank_branch', 'IKEJA');
    }

    public function test_an_unlisted_enrollment_bank_code_reads_as_an_agency(): void
    {
        $this->reseller(1000);
        $this->price('bvn.search.premium', 150);

        Http::fake(['*' => Http::response([
            'status' => 'success',
            'data' => [
                'bvn' => '22345678901',
                'lastName' => 'DOE',
                'enrollmentBank' => '873',
                'enrollmentBranch' => '904',
            ],
        ])]);

        $this->apiCall('POST', '/api/v1/bvn/verify', ['bvn' => '22345678901'])
            ->assertOk()
            ->assertJsonPath('data.enrollment_bank', 'Agency enrollment')
            ->assertJsonPath('data.enrollment_bank_branch', 'Agency enrollment');
    }

    /**
     * BVN is a fixed shape: an absent field is null, never a missing key. Docs
     * tell integrators to check for null, so that has to stay true.
     */
    public function test_a_bvn_record_always_carries_every_field(): void
    {
        $this->reseller(1000);
        $this->price('bvn.search.premium', 150);

        Http::fake(['*' => Http::response([
            'status' => 'success',
            'data' => ['bvn' => '22345678901', 'lastName' => 'DOE', 'firstName' => 'JOHN'],
        ])]);

        $data = $this->apiCall('POST', '/api/v1/bvn/verify', ['bvn' => '22345678901'])
            ->assertOk()
            ->json('data');

        foreach ([
            'bvn', 'surname', 'firstname', 'middlename', 'gender', 'dob', 'phone', 'phone2',
            'email', 'photo', 'marital_status', 'state_of_origin', 'lga_of_origin',
            'registration_date', 'enrollment_bank', 'enrollment_bank_branch',
            'residential_Address', 'nationality',
        ] as $field) {
            $this->assertArrayHasKey($field, $data, "BVN response dropped [{$field}]");
        }

        $this->assertNull($data['enrollment_bank']);
    }

    /**
     * The integrator sends the identifier they hold, not a method naming it.
     */
    public function test_the_lookup_follows_the_identifier_that_was_sent(): void
    {
        $this->reseller(1000);
        $this->price('nin.phone', 40);
        $this->price('nin.demographic', 60);

        Http::fake(['*' => Http::response(['nin' => '12345678901', 'firstname' => 'JOHN'])]);

        $this->apiCall('POST', '/api/v1/nin/verify', ['phone' => '08012345678'])
            ->assertOk()
            ->assertJsonPath('method', 'phone');

        $this->apiCall('POST', '/api/v1/nin/verify', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'M',
            'date_of_birth' => '1990-05-21',
        ])->assertOk()->assertJsonPath('method', 'demographic');
    }

    /**
     * A NIN is the stronger identifier, so it decides the lookup -- and with it
     * the price -- rather than whichever field happens to be read first.
     */
    public function test_a_nin_wins_when_a_phone_is_also_sent(): void
    {
        $user = $this->reseller(1000);
        $this->price('nin.verify', 40);
        $this->price('nin.phone', 90);

        Http::fake(['*' => Http::response(['nin' => '12345678901'])]);

        $this->apiCall('POST', '/api/v1/nin/verify', ['nin' => '12345678901', 'phone' => '08012345678'])
            ->assertOk()
            ->assertJsonPath('method', 'nin');

        $this->assertSame(960.0, (float) $user->fresh()->balance);
    }

    public function test_a_body_with_no_identifier_is_rejected_without_charging(): void
    {
        $user = $this->reseller(1000);
        $this->price('nin.verify', 40);

        Http::fake();

        $this->apiCall('POST', '/api/v1/nin/verify', [])->assertStatus(422);

        $this->assertSame(1000.0, (float) $user->fresh()->balance);
        Http::assertNothingSent();
    }

    public function test_an_invalid_nin_is_rejected_without_charging(): void
    {
        $user = $this->reseller(1000);
        $this->price('nin.verify', 40, UserRole::API->value);

        Http::fake();

        $this->apiCall('POST', '/api/v1/nin/verify', ['nin' => '123'])
            ->assertStatus(422);

        $this->assertSame(1000.0, (float) $user->fresh()->balance);
        Http::assertNothingSent();
    }

    public function test_an_empty_wallet_returns_402(): void
    {
        $this->reseller(5);
        $this->price('nin.verify', 40, UserRole::API->value);

        Http::fake();

        $this->apiCall('POST', '/api/v1/nin/verify', ['nin' => '12345678901'])
            ->assertStatus(402);

        Http::assertNothingSent();
    }

    public function test_a_bvn_lookup_charges_and_returns_details(): void
    {
        $user = $this->reseller(1000);
        $this->price('bvn.search.premium', 150);

        Http::fake(['*' => Http::response([
            'status' => 'success',
            'data' => ['bvn' => '22345678901', 'lastName' => 'DOE', 'firstName' => 'JOHN'],
        ])]);

        $this->apiCall('POST', '/api/v1/bvn/verify', ['bvn' => '22345678901', 'slip_type' => 'premium'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.surname', 'DOE');

        $this->assertSame(850.0, (float) $user->fresh()->balance);
    }

    /**
     * slip_type is optional: omitting it must give the full slip, and the
     * response has to say which one was billed.
     */
    public function test_a_bvn_lookup_without_a_slip_type_uses_the_premium_slip(): void
    {
        $user = $this->reseller(1000);
        $this->price('bvn.search.premium', 150);
        $this->price('bvn.search.regular', 50);

        Http::fake(['*' => Http::response([
            'status' => 'success',
            'data' => ['bvn' => '22345678901', 'lastName' => 'DOE'],
        ])]);

        $this->apiCall('POST', '/api/v1/bvn/verify', ['bvn' => '22345678901'])
            ->assertOk()
            ->assertJsonPath('slip_type', 'premium')
            ->assertJsonPath('amount', 150);

        $this->assertSame(850.0, (float) $user->fresh()->balance);
    }

    public function test_an_unknown_slip_type_is_still_rejected(): void
    {
        $this->reseller(1000);
        $this->price('bvn.search.premium', 150);

        Http::fake();

        $this->apiCall('POST', '/api/v1/bvn/verify', ['bvn' => '22345678901', 'slip_type' => 'platinum'])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    /**
     * A lookup the provider could not fulfil must not cost the reseller money.
     */
    public function test_a_failed_bvn_lookup_is_refunded(): void
    {
        $user = $this->reseller(1000);
        $this->price('bvn.search.premium', 150);

        Http::fake(['*' => Http::response(['status' => 'failed', 'message' => 'Record not found'])]);

        $this->apiCall('POST', '/api/v1/bvn/verify', ['bvn' => '22345678901', 'slip_type' => 'premium'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'verification_failed');

        $this->assertSame(1000.0, (float) $user->fresh()->balance);
    }

    /**
     * Validation is billed as its own service -- an operator who prices a cheap
     * yes/no check must not have it charged at the verification rate.
     */
    public function test_a_nin_validation_charges_the_validation_rate(): void
    {
        $user = $this->reseller(1000);
        $this->price('nin.verify', 100);
        $this->price('nin.validation', 25);

        Http::fake(['*' => Http::response(['nin' => '12345678901', 'firstname' => 'JOHN'])]);

        $this->apiCall('POST', '/api/v1/nin/validate', ['nin' => '12345678901'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('valid', true)
            ->assertJsonPath('data.nin', '12345678901');

        $this->assertSame(975.0, (float) $user->fresh()->balance);
    }

    public function test_a_failed_validation_is_refunded(): void
    {
        $user = $this->reseller(1000);
        $this->price('nin.validation', 25);

        Http::fake(['*' => Http::response(['status' => 'failed', 'message' => 'Record not found'], 404)]);

        $this->apiCall('POST', '/api/v1/nin/validate', ['nin' => '12345678901'])
            ->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('refunded', true);

        $this->assertSame(1000.0, (float) $user->fresh()->balance);
    }

    public function test_an_ipe_submission_charges_and_is_readable_back(): void
    {
        $user = $this->reseller(1000);
        $this->price('nin.ipe', 200);

        Http::fake(['*' => Http::response(['status' => 'success', 'message' => 'Submitted'])]);

        $response = $this->apiCall('POST', '/api/v1/nin/ipe', ['tracking_id' => 'ABC1234567890XY'])
            ->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'processing');

        $this->assertSame(800.0, (float) $user->fresh()->balance);

        // Readable by the id we returned and by the tracking id they sent.
        $this->apiCall('GET', '/api/v1/nin/ipe/'.$response->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.tracking_id', 'ABC1234567890XY');

        $this->apiCall('GET', '/api/v1/nin/ipe/ABC1234567890XY')
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');
    }

    /**
     * IPE is a submission: an ambiguous reply may mean it landed upstream, so it
     * must not be reported as a plain failure the integrator would retry.
     */
    public function test_an_unconfirmed_ipe_submission_is_refunded_but_kept_on_file(): void
    {
        $user = $this->reseller(1000);
        $this->price('nin.ipe', 200);

        Http::fake(['*' => Http::response('gateway timeout', 504)]);

        $this->apiCall('POST', '/api/v1/nin/ipe', ['trkid' => 'ABC1234567890XY'])
            ->assertStatus(202)
            ->assertJsonPath('status', 'unconfirmed')
            ->assertJsonPath('refunded', true)
            ->assertJsonPath('data.status', 'processing');

        $this->assertSame(1000.0, (float) $user->fresh()->balance);

        $this->apiCall('GET', '/api/v1/nin/ipe')
            ->assertOk()
            ->assertJsonPath('data.submissions.0.result', 'Unconfirmed');
    }

    public function test_a_reseller_cannot_read_another_users_ipe_submission(): void
    {
        $this->reseller(1000);

        $other = User::factory()->create(['role' => UserRole::USER]);
        \App\Models\Ipe::create([
            'trkid' => 'ZZZ1234567890XY',
            'status' => 'processing',
            'result' => 'Pending',
            'comment' => 'someone else',
            'oldBal' => 0,
            'newBal' => 0,
            'userId' => $other->id,
        ]);

        $this->apiCall('GET', '/api/v1/nin/ipe/ZZZ1234567890XY')->assertStatus(404);
    }

    public function test_an_ipe_tracking_id_must_be_the_right_length(): void
    {
        $user = $this->reseller(1000);
        $this->price('nin.ipe', 200);

        Http::fake();

        $this->apiCall('POST', '/api/v1/nin/ipe', ['tracking_id' => 'TOO-SHORT'])
            ->assertStatus(422);

        $this->assertSame(1000.0, (float) $user->fresh()->balance);
        Http::assertNothingSent();
    }

    public function test_it_lists_the_available_providers(): void
    {
        $this->reseller();

        $response = $this->apiCall('GET', '/api/v1/nin/providers')->assertOk();

        // One entry now — routing, not the caller, picks the upstream provider.
        $this->assertNotEmpty($response->json('data.providers'));
        $this->assertSame('auto', $response->json('data.providers.0.key'));
        $this->assertArrayHasKey('methods', $response->json('data.providers.0'));
    }

    public function test_the_docs_are_publicly_reachable(): void
    {
        $this->get('/developers')->assertOk();
    }

    public function test_an_api_user_can_regenerate_their_token(): void
    {
        $user = $this->reseller();

        $this->actingAs($user)->post('/api-access/token')->assertSessionHasNoErrors();

        $this->assertNotSame(self::TOKEN, $user->fresh()->apitoken);
        $this->assertNotEmpty($user->fresh()->apitoken);
    }

    public function test_a_non_api_user_cannot_generate_a_token(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);

        $this->actingAs($user)->post('/api-access/token')->assertSessionHasErrors('token');

        $this->assertEmpty($user->fresh()->apitoken);
    }

    /**
     * Granting the role has to hand over a usable credential, or the operator
     * has to chase a second step nobody documented.
     */
    public function test_granting_the_api_role_issues_a_token(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $user = User::factory()->create(['role' => UserRole::USER, 'apitoken' => null]);

        $this->actingAs($admin)
            ->patch("/admin/users/{$user->id}/role", ['role' => 'API'])
            ->assertSessionHasNoErrors();

        $this->assertNotEmpty($user->fresh()->apitoken);
    }
}
