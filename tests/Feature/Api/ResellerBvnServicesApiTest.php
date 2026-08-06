<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\BvnModification;
use App\Models\BvnRetrieval;
use App\Models\BvnSdkForm;
use App\Models\Record;
use App\Models\ServicePrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The staff-fulfilled BVN services over the reseller API: modification,
 * retrieval, SDK onboarding, and the enrolment record search.
 *
 * None of these call a provider, so the things worth pinning down are the
 * money (charged once, refunded when nothing is filed, refused when the wallet
 * is short) and the scoping (a reseller reads only their own requests).
 */
class ResellerBvnServicesApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'sk_live_bvn_services_token';

    private function reseller(float $balance = 5000): User
    {
        return User::factory()->create([
            'role' => UserRole::API,
            'apitoken' => self::TOKEN,
            'balance' => $balance,
        ]);
    }

    private function price(string $service, ?float $price, bool $active = true): void
    {
        ServicePrice::forgetCache();
        ServicePrice::updateOrCreate(
            ['service' => $service, 'role' => ServicePrice::BASE],
            ['price' => $price, 'is_active' => $active],
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

    /** A one-pixel PNG, base64 encoded — a real image so finfo accepts it. */
    private function slip(): string
    {
        return base64_encode(base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
    }

    private function modificationBody(array $overrides = []): array
    {
        return array_merge([
            'service_type' => 'modify-name',
            'bvn' => '22345678901',
            'nin' => '12345678901',
            'nin_slip' => $this->slip(),
            'old_first_name' => 'John',
            'old_last_name' => 'Doe',
            'new_first_name' => 'Johnny',
            'new_last_name' => 'Doe',
        ], $overrides);
    }

    private function onboardingBody(array $overrides = []): array
    {
        return array_merge([
            'agent_location' => 'Kano Main Market',
            'agent_bvn' => '22345678901',
            'bank_name' => 'First Bank',
            'account_number' => '0123456789',
            'account_name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'agent@example.com',
            'phone_number' => '08012345678',
            'address' => '12 Broad Street',
            'state_of_residence' => 'Kano',
            'lga' => 'Nassarawa',
            'zone' => 'north-west',
            'date_of_birth' => '1990-05-21',
        ], $overrides);
    }

    // ---------------------------------------------------------------- modification

    public function test_a_modification_charges_the_rate_for_that_service_type(): void
    {
        $user = $this->reseller(1000);
        $this->price('bvn.mod.name', 300);

        $response = $this->apiCall('POST', '/api/v1/bvn/modification', $this->modificationBody())
            ->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('amount', 300)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.service_label', 'Name Modification');

        $this->assertSame(700.0, (float) $user->fresh()->balance);
        $this->assertSame($response->json('data.id'), $response->json('reference'));
    }

    /**
     * Each combination is its own product with its own price, so the service
     * type has to pick the key rather than a single "modification" fee.
     */
    public function test_each_service_type_is_priced_from_its_own_key(): void
    {
        $user = $this->reseller(2000);
        $this->price('bvn.mod.name', 300);
        $this->price('bvn.mod.name_dob_phone', 900);

        $this->apiCall('POST', '/api/v1/bvn/modification', $this->modificationBody([
            'service_type' => 'modify-name-dob-phone',
            'old_dob' => '1990-05-21',
            'new_dob' => '1991-05-21',
            'old_phone' => '08012345678',
            'new_phone' => '08087654321',
        ]))->assertStatus(201)->assertJsonPath('amount', 900);

        $this->assertSame(1100.0, (float) $user->fresh()->balance);
    }

    public function test_a_modification_only_records_the_fields_its_service_type_changes(): void
    {
        $this->reseller(1000);
        $this->price('bvn.mod.phone', 250);

        $this->apiCall('POST', '/api/v1/bvn/modification', $this->modificationBody([
            'service_type' => 'modify-phone',
            'old_phone' => '08012345678',
            'new_phone' => '08087654321',
            // Sent but irrelevant to a phone change: must not be recorded as a
            // name change nobody asked for.
            'old_first_name' => 'John',
            'new_first_name' => 'Johnny',
        ]))
            ->assertStatus(201)
            ->assertJsonPath('data.new_phone', '08087654321')
            ->assertJsonMissingPath('data.new_first_name');

        $this->assertNull(BvnModification::first()->newFirstName);
    }

    public function test_a_service_type_submitted_without_its_required_fields_is_not_charged(): void
    {
        $user = $this->reseller(1000);
        $this->price('bvn.mod.dob', 250);

        $this->apiCall('POST', '/api/v1/bvn/modification', $this->modificationBody([
            'service_type' => 'modify-dob',
            // old_dob / new_dob omitted.
        ]))
            ->assertStatus(422)
            ->assertJsonPath('code', 'missing_fields')
            ->assertJsonPath('fields', ['old_dob', 'new_dob']);

        $this->assertSame(1000.0, (float) $user->fresh()->balance);
        $this->assertSame(0, BvnModification::count());
    }

    public function test_a_nin_slip_that_is_not_an_image_or_pdf_is_rejected(): void
    {
        $user = $this->reseller(1000);
        $this->price('bvn.mod.name', 300);

        $this->apiCall('POST', '/api/v1/bvn/modification', $this->modificationBody([
            'nin_slip' => base64_encode('this is a text file, not a slip'),
        ]))
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_slip');

        $this->assertSame(1000.0, (float) $user->fresh()->balance);
    }

    public function test_a_nin_slip_can_also_be_uploaded_as_a_file(): void
    {
        $this->reseller(1000);
        $this->price('bvn.mod.name', 300);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.self::TOKEN,
            'Accept' => 'application/json',
        ])->post('/api/v1/bvn/modification', $this->modificationBody([
            'nin_slip' => UploadedFile::fake()->image('slip.jpg'),
        ]));

        $response->assertStatus(201);
    }

    public function test_a_modification_is_readable_back_by_id_and_by_bvn(): void
    {
        $this->reseller(1000);
        $this->price('bvn.mod.name', 300);

        $id = $this->apiCall('POST', '/api/v1/bvn/modification', $this->modificationBody())
            ->json('data.id');

        $this->apiCall('GET', '/api/v1/bvn/modification/'.$id)
            ->assertOk()
            ->assertJsonPath('data.bvn', '22345678901');

        $this->apiCall('GET', '/api/v1/bvn/modification/22345678901')
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->apiCall('GET', '/api/v1/bvn/modification')
            ->assertOk()
            ->assertJsonPath('data.submissions.0.id', $id);
    }

    public function test_an_empty_wallet_cannot_file_a_modification(): void
    {
        $this->reseller(50);
        $this->price('bvn.mod.name', 300);

        $this->apiCall('POST', '/api/v1/bvn/modification', $this->modificationBody())
            ->assertStatus(402)
            ->assertJsonPath('code', 'insufficient_balance');

        $this->assertSame(0, BvnModification::count());
    }

    public function test_a_switched_off_modification_service_is_refused(): void
    {
        $user = $this->reseller(1000);
        $this->price('bvn.mod.name', 300, active: false);

        $this->apiCall('POST', '/api/v1/bvn/modification', $this->modificationBody())
            ->assertStatus(503)
            ->assertJsonPath('code', 'service_unavailable');

        $this->assertSame(1000.0, (float) $user->fresh()->balance);
    }

    public function test_a_reseller_cannot_read_another_users_modification(): void
    {
        $this->reseller(1000);

        $other = User::factory()->create(['role' => UserRole::USER]);
        BvnModification::create([
            'bvn' => '99345678901',
            'nin' => '99345678901',
            'ninSlipUrl' => 'slip.jpg',
            'ninSlipImage' => 'x',
            'serviceType' => 'modify-name',
            'status' => 'pending',
            'userId' => $other->id,
        ]);

        $this->apiCall('GET', '/api/v1/bvn/modification/99345678901')->assertStatus(404);
        $this->apiCall('GET', '/api/v1/bvn/modification')
            ->assertOk()
            ->assertJsonCount(0, 'data.submissions');
    }

    // ------------------------------------------------------------------ retrieval

    public function test_a_retrieval_charges_and_is_readable_back(): void
    {
        $user = $this->reseller(1000);
        $this->price('bvn.retrieve.id', 400);

        $id = $this->apiCall('POST', '/api/v1/bvn/retrieval', ['ticket_id' => '12345678'])
            ->assertStatus(201)
            ->assertJsonPath('amount', 400)
            ->assertJsonPath('data.status', 'pending')
            // The BVN is what the request produces; it is not known yet.
            ->assertJsonPath('data.bvn', null)
            ->json('data.id');

        $this->assertSame(600.0, (float) $user->fresh()->balance);

        $this->apiCall('GET', '/api/v1/bvn/retrieval/'.$id)->assertOk();
        $this->apiCall('GET', '/api/v1/bvn/retrieval/12345678')
            ->assertOk()
            ->assertJsonPath('data.id', $id);
    }

    public function test_a_retrieval_accepts_the_bms_id_spelling(): void
    {
        $this->reseller(1000);
        $this->price('bvn.retrieve.id', 400);

        $this->apiCall('POST', '/api/v1/bvn/retrieval', ['bms_id' => '87654321'])
            ->assertStatus(201)
            ->assertJsonPath('data.ticket_id', '87654321');
    }

    public function test_a_ticket_id_must_be_eight_digits(): void
    {
        $user = $this->reseller(1000);
        $this->price('bvn.retrieve.id', 400);

        $this->apiCall('POST', '/api/v1/bvn/retrieval', ['ticket_id' => '123'])->assertStatus(422);

        $this->assertSame(1000.0, (float) $user->fresh()->balance);
        $this->assertSame(0, BvnRetrieval::count());
    }

    public function test_a_completed_retrieval_reports_the_bvn(): void
    {
        $this->reseller(1000);
        $this->price('bvn.retrieve.id', 400);

        $id = $this->apiCall('POST', '/api/v1/bvn/retrieval', ['ticket_id' => '12345678'])
            ->json('data.id');

        // What an admin does when they finish the request.
        BvnRetrieval::find($id)->update(['status' => 'completed', 'bvn' => '22345678901']);

        $this->apiCall('GET', '/api/v1/bvn/retrieval/'.$id)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.bvn', '22345678901');
    }

    // ----------------------------------------------------------------- onboarding

    public function test_an_onboarding_charges_and_is_readable_back(): void
    {
        $user = $this->reseller(3000);
        $this->price('bvn.onboarding1', 1500);

        $id = $this->apiCall('POST', '/api/v1/bvn/onboarding', $this->onboardingBody())
            ->assertStatus(201)
            ->assertJsonPath('amount', 1500)
            ->assertJsonPath('data.status', 'Submitted')
            ->assertJsonPath('data.zone', 'north-west')
            ->json('data.id');

        $this->assertSame(1500.0, (float) $user->fresh()->balance);

        $this->apiCall('GET', '/api/v1/bvn/onboarding/'.$id)->assertOk();
        $this->apiCall('GET', '/api/v1/bvn/onboarding/agent@example.com')
            ->assertOk()
            ->assertJsonPath('data.id', $id);
    }

    public function test_an_agent_cannot_be_onboarded_twice_on_the_same_email(): void
    {
        $user = $this->reseller(5000);
        $this->price('bvn.onboarding1', 1500);

        $this->apiCall('POST', '/api/v1/bvn/onboarding', $this->onboardingBody())->assertStatus(201);

        $this->apiCall('POST', '/api/v1/bvn/onboarding', $this->onboardingBody([
            'phone_number' => '08099999999',
        ]))->assertStatus(422);

        // Charged once, for the one registration that was filed.
        $this->assertSame(3500.0, (float) $user->fresh()->balance);
        $this->assertSame(1, BvnSdkForm::count());
    }

    public function test_an_unknown_zone_is_rejected(): void
    {
        $this->reseller(3000);
        $this->price('bvn.onboarding1', 1500);

        $this->apiCall('POST', '/api/v1/bvn/onboarding', $this->onboardingBody([
            'zone' => 'north-pole',
        ]))->assertStatus(422);

        $this->assertSame(0, BvnSdkForm::count());
    }

    public function test_a_reseller_cannot_read_another_users_onboarding(): void
    {
        $this->reseller(3000);

        $other = User::factory()->create(['role' => UserRole::USER]);
        BvnSdkForm::create($this->onboardingCamelBody() + ['userId' => $other->id]);

        $this->apiCall('GET', '/api/v1/bvn/onboarding/agent@example.com')->assertStatus(404);
    }

    /** The web-side column spellings, for seeding another user's row directly. */
    private function onboardingCamelBody(): array
    {
        return [
            'agentLocation' => 'Kano Main Market',
            'agentBvn' => '22345678901',
            'bankName' => 'First Bank',
            'accountNumber' => '0123456789',
            'accountName' => 'John Doe',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'agent@example.com',
            'phoneNumber' => '08012345678',
            'address' => '12 Broad Street',
            'stateOfResidence' => 'Kano',
            'lga' => 'Nassarawa',
            'zone' => 'north-west',
            'dateOfBirth' => '1990-05-21',
            'oldBal' => '0',
            'newBal' => '0',
            'status' => 'Submitted',
        ];
    }

    // -------------------------------------------------------------- record search

    public function test_the_record_search_finds_by_ticket_id_and_costs_nothing(): void
    {
        $user = $this->reseller(1000);

        Record::create([
            'ticket_id' => 'TKT12345678',
            'bvn' => '22345678901',
            'enrollee_name' => 'JOHN DOE',
            'enroller_id' => 'AGT0099',
            'status' => 'enrolled',
        ]);

        $this->apiCall('GET', '/api/v1/bvn/records?query=TKT123')
            ->assertOk()
            ->assertJsonPath('data.type', 'ticket_id')
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.records.0.enrollee_name', 'JOHN DOE');

        $this->assertSame(1000.0, (float) $user->fresh()->balance);
    }

    public function test_the_record_search_can_search_by_enroller_id(): void
    {
        $this->reseller(1000);

        Record::create(['ticket_id' => 'TKT12345678', 'enroller_id' => 'AGT009911']);
        Record::create(['ticket_id' => 'TKT87654321', 'enroller_id' => 'AGT770022']);

        $this->apiCall('GET', '/api/v1/bvn/records?type=enroller_id&query=AGT0099')
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.records.0.ticket_id', 'TKT12345678');
    }

    /**
     * The floor exists because a short query matches most of the table; without
     * it an integrator "searching" for two characters pages through everything.
     */
    public function test_a_short_record_query_is_rejected(): void
    {
        $this->reseller(1000);

        $this->apiCall('GET', '/api/v1/bvn/records?query=TKT')->assertStatus(422);
        $this->apiCall('GET', '/api/v1/bvn/records')->assertStatus(422);
    }

    public function test_an_unknown_record_search_type_is_rejected(): void
    {
        $this->reseller(1000);

        $this->apiCall('GET', '/api/v1/bvn/records?type=bvn&query=22345678901')->assertStatus(422);
    }

    // ----------------------------------------------------------------------- auth

    public function test_the_new_bvn_endpoints_all_require_a_token(): void
    {
        $this->reseller(1000);

        foreach ([
            ['POST', '/api/v1/bvn/modification'],
            ['GET', '/api/v1/bvn/modification'],
            ['POST', '/api/v1/bvn/retrieval'],
            ['GET', '/api/v1/bvn/retrieval'],
            ['POST', '/api/v1/bvn/onboarding'],
            ['GET', '/api/v1/bvn/onboarding'],
            ['GET', '/api/v1/bvn/records'],
        ] as [$method, $uri]) {
            $this->json($method, $uri)->assertStatus(401);
        }
    }
}
