<?php

namespace Tests\Unit;

use App\Services\Verification\ResponseNormalizer;
use App\Services\Verification\SuccessEvaluator;
use Tests\TestCase;

/**
 * The normalizer's whole job is that no downstream code has to know which
 * provider answered. These cases are the real documented response bodies of
 * every provider in the seeder, so a regression here is caught before it
 * reaches a customer's slip.
 */
class VerificationNormalizerTest extends TestCase
{
    private ResponseNormalizer $normalizer;

    private SuccessEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new ResponseNormalizer;
        $this->evaluator = new SuccessEvaluator;
    }

    public function test_it_normalizes_a_payvessel_bvn_response(): void
    {
        $raw = [
            'success' => true,
            'message' => 'BVN verification completed successfully',
            'data' => [
                'bvn' => '22123456789',
                'first_name' => 'John',
                'middle_name' => 'Adebayo',
                'last_name' => 'Doe',
                'gender' => 'MALE',
                'name_on_card' => 'JOHN A DOE',
                'birthday' => '1992-08-14',
                'photo' => '/9j/4AAQSkZJRgABAQAAAQABAAD',
                'phone_number' => '08012345678',
                'phone_number_2' => '08087654321',
            ],
            'charges' => ['charged' => false, 'charged_amount' => '0.00'],
        ];

        $data = $this->normalizer->normalize($raw);

        $this->assertSame('John', $data['first_name']);
        $this->assertSame('Adebayo', $data['middle_name']);
        $this->assertSame('Doe', $data['last_name']);
        $this->assertSame('MALE', $data['gender']);
        $this->assertSame('1992-08-14', $data['date_of_birth']);
        $this->assertSame('22123456789', $data['bvn']);
        $this->assertSame('08012345678', $data['phone']);
        $this->assertSame('08087654321', $data['phone2']);
        $this->assertSame('John Adebayo Doe', $data['full_name']);
    }

    public function test_it_normalizes_a_payvessel_nin_response_whose_surname_key_differs(): void
    {
        // PayVessel says `surname` + `birth_date` + `telephone_no` for NIN but
        // `last_name` + `birthday` + `phone_number` for BVN — same provider.
        $data = $this->normalizer->normalize([
            'success' => true,
            'data' => [
                'nin' => '12345678901',
                'first_name' => 'John',
                'surname' => 'Doe',
                'gender' => 'MALE',
                'birth_date' => '1992-08-14',
                'telephone_no' => '08012345678',
                'error_message' => '',
            ],
        ]);

        $this->assertSame('Doe', $data['last_name']);
        $this->assertSame('1992-08-14', $data['date_of_birth']);
        $this->assertSame('08012345678', $data['phone']);
    }

    public function test_it_normalizes_an_idtra_phone_response(): void
    {
        // Day-first date, single-letter gender, a data-URI photo, and NIMC's
        // "heigth" typo — all of which have to come out canonical.
        $data = $this->normalizer->normalize([
            'status' => true,
            'response_code' => '00',
            'message' => 'Phone Number Verification successful',
            'data' => [
                'birthdate' => '22-05-1998',
                'firstname' => 'CHIDERA',
                'gender' => 'f',
                'middlename' => 'ANITA',
                'nin' => '12345678901',
                'photo' => 'data:image/jpeg;base64,QUJD',
                'residence_address' => '21 AJANAKU STREET',
                'residence_state' => 'Lagos',
                'surname' => 'JOHNSON',
                'telephoneno' => '08093098607',
                'heigth' => '',
                'nok_firstname' => 'JONATHAN',
                'nok_lga' => 'Shomolu',
                // Parent's names must NOT be mistaken for the subject's.
                'pmiddlename' => '',
                'psurname' => '',
            ],
        ]);

        $this->assertSame('CHIDERA', $data['first_name']);
        $this->assertSame('JOHNSON', $data['last_name']);
        $this->assertSame('FEMALE', $data['gender']);
        $this->assertSame('1998-05-22', $data['date_of_birth']);
        $this->assertSame('QUJD', $data['photo'], 'the data-URI prefix should be stripped');
        $this->assertSame('08093098607', $data['phone']);
        $this->assertSame('21 AJANAKU STREET', $data['residence_address']);
        $this->assertSame('JONATHAN', $data['nok_first_name']);
    }

    public function test_it_normalizes_an_arewasmart_camel_case_response(): void
    {
        $data = $this->normalizer->normalize([
            'status' => 'success',
            'message' => 'Verification Successful',
            'data' => ['bvn' => '12345678901', 'firstName' => 'ABDULLAHI', 'lastName' => 'GARBA'],
            'transaction_ref' => 'BVN-123',
        ]);

        $this->assertSame('ABDULLAHI', $data['first_name']);
        $this->assertSame('GARBA', $data['last_name']);
        // The reference lives on the envelope, not inside `data`.
        $this->assertSame('BVN-123', $data['reference']);
    }

    public function test_it_normalizes_a_techhub_ipe_response_wrapped_in_user_data(): void
    {
        $data = $this->normalizer->normalize([
            'status' => 'success',
            'response_code' => '00',
            'user_data' => [
                'tracking_id' => 'IPE202400001',
                'new_tracking_id' => 'NEW123456789',
                'new_nin' => '98765432109',
                'status' => 'completed',
            ],
            'message' => 'IPE clearance completed',
        ]);

        $this->assertSame('IPE202400001', $data['tracking_id']);
        $this->assertSame('98765432109', $data['nin']);
    }

    public function test_it_normalizes_a_phone_response_double_wrapped_in_api_response(): void
    {
        // Some NIN-by-phone providers bury the person two levels down, under a
        // non-`data` envelope: api_response -> data -> data -> {person}.
        $data = $this->normalizer->normalize([
            'status' => true,
            'message' => 'NIN Phone Verification Successful',
            'api_response' => [
                'status' => true,
                'data' => [
                    'status' => true,
                    'message' => 'success',
                    'data' => [
                        'birthdate' => '25-05-1995',
                        'centralID' => '2IR10CVS8Z004AZ',
                        'firstname' => 'MADUGU',
                        'middlename' => 'M',
                        'nin' => '10740861707',
                        'gender' => 'm',
                        'photo' => '/9j/4AAQSkZJRgABAgAAAQAB',
                    ],
                ],
            ],
        ]);

        $this->assertSame('MADUGU', $data['first_name']);
        $this->assertSame('M', $data['middle_name']);
        $this->assertSame('MALE', $data['gender']);
        $this->assertSame('1995-05-25', $data['date_of_birth']);
        $this->assertSame('10740861707', $data['nin']);
        $this->assertSame('2IR10CVS8Z004AZ', $data['central_id']);
        $this->assertSame('/9j/4AAQSkZJRgABAgAAAQAB', $data['photo']);
    }

    public function test_a_seeded_input_never_overwrites_what_the_provider_returned(): void
    {
        $data = $this->normalizer->normalize(
            ['data' => ['nin' => '11111111111', 'surname' => 'DOE']],
            [],
            ['nin' => '99999999999', 'phone' => '08000000000'],
        );

        $this->assertSame('11111111111', $data['nin'], 'the provider is authoritative');
        $this->assertSame('08000000000', $data['phone'], 'but gaps are filled from the request');
    }

    public function test_response_map_overrides_beat_the_alias_table(): void
    {
        $data = $this->normalizer->normalize(
            ['data' => ['surname' => 'WRONG'], 'extra' => ['real_surname' => 'RIGHT']],
            ['last_name' => 'extra.real_surname'],
        );

        $this->assertSame('RIGHT', $data['last_name']);
    }

    public function test_success_evaluation_across_provider_dialects(): void
    {
        $cases = [
            'payvessel'  => [['success' => true, 'data' => ['bvn' => '1']], null, true],
            'idtra'      => [['status' => true, 'data' => ['nin' => '1']], null, true],
            'arewasmart' => [['status' => 'success', 'data' => ['bvn' => '1']], null, true],
            'techhub'    => [['status' => 'success', 'response_code' => '00'], null, true],
            'bare record' => [['nin' => '12345678901', 'surname' => 'DOE'], null, true],
            'explicit failure' => [['status' => false, 'message' => 'BVN does not exist'], null, false],
            'error key' => [['error' => 'Invalid key'], null, false],
        ];

        foreach ($cases as $name => [$body, $rule, $expected]) {
            $this->assertSame($expected, $this->evaluator->isSuccess($body, $rule, 200), "case: {$name}");
        }
    }

    public function test_a_charged_not_found_is_still_a_failure(): void
    {
        // ArewaSmart bills for code 222222 "BVN does not exist" — but the user
        // got no record, so the chain must treat it as a decline and move on.
        $rule = ['path' => 'status', 'in' => ['success', 'true', '111111'], 'data_path' => 'data'];

        $this->assertFalse($this->evaluator->isSuccess(
            ['status' => '222222', 'message' => 'BVN does not exist'],
            $rule,
            200,
        ));
    }

    /**
     * The admin form strips blank auth_config values before saving, so a
     * provider that relies on a style's default header name stores no key at
     * all. Reading it with `?:` alone raised "Undefined array key" and took the
     * whole request down.
     */
    public function test_auth_styles_fall_back_to_their_default_field_names(): void
    {
        $headers = \App\Services\Verification\AuthStyle::headers(
            'key_secret',
            ['key' => 'K', 'secret' => 'S'],
            [], // nothing configured
        );

        $this->assertSame(['api-key' => 'K', 'api-secret' => 'S'], $headers);

        $this->assertSame(
            ['x-api-key' => 'T'],
            \App\Services\Verification\AuthStyle::headers('header_key', ['token' => 'T'], []),
        );

        $this->assertSame(
            ['api_key' => 'T'],
            \App\Services\Verification\AuthStyle::bodyFields('body_key', ['token' => 'T'], []),
        );

        // An explicit name still wins.
        $this->assertSame(
            ['X-Custom' => 'T'],
            \App\Services\Verification\AuthStyle::headers('header_key', ['token' => 'T'], ['header_name' => 'X-Custom']),
        );
    }

    public function test_a_success_status_with_an_empty_data_object_is_a_failure(): void
    {
        $rule = ['path' => 'status', 'data_path' => 'data'];

        $this->assertFalse($this->evaluator->isSuccess(['status' => 'success', 'data' => null], $rule, 200));
        $this->assertTrue($this->evaluator->isSuccess(['status' => 'success', 'data' => ['nin' => '1']], $rule, 200));
    }
}
