<?php

namespace Tests\Unit;

use App\Models\VerificationEndpoint;
use App\Models\VerificationProvider;
use App\Services\Verification\RequestBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pins the ArewaSmart async endpoints to the shapes in the vendor's docs.
 *
 * These four calls are configured by hand in Admin > Verification, so nothing
 * else stops a mapping drifting away from what the provider accepts. The
 * failure they exist to catch is silent: `field_code` is not a caller input, so
 * putting it in the field map instead of the constants emits *nothing* and the
 * provider answers "The field code field is required" — a request that looks
 * correctly configured in the admin and is not.
 */
class ArewaSmartRequestShapeTest extends TestCase
{
    private function provider(): VerificationProvider
    {
        return new VerificationProvider([
            'name' => 'ArewaSmart',
            'slug' => 'arewasmart',
            'base_url' => 'https://api.arewasmart.com.ng/api/v1',
            'auth_type' => 'bearer',
            'credentials' => ['token' => 'test-token'],
            'extra_headers' => ['Accept' => 'application/json'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function endpoint(array $attributes): VerificationEndpoint
    {
        return new VerificationEndpoint($attributes + [
            'body_type' => 'json',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function the_ipe_submission_matches_the_documented_payload(): void
    {
        $request = (new RequestBuilder)->build(
            $this->provider(),
            $this->endpoint([
                'service' => 'nin.ipe',
                'http_method' => 'POST',
                'path' => '/nin/ipe',
                'field_map' => ['tracking_id' => 'tracking_id', 'description' => 'description', 'nin' => false],
                'static_fields' => ['field_code' => '002'],
            ]),
            ['tracking_id' => '0T3NU7RSKMCHTT4', 'description' => 'My Reference'],
        );

        $this->assertSame('POST', $request['method']);
        $this->assertSame('https://api.arewasmart.com.ng/api/v1/nin/ipe', $request['url']);

        // Exactly the documented body — no more, no less.
        $this->assertEqualsCanonicalizing([
            'field_code' => '002',
            'tracking_id' => '0T3NU7RSKMCHTT4',
            'description' => 'My Reference',
        ], $request['body']);

        $this->assertSame('Bearer test-token', $request['headers']['Authorization']);
    }

    #[Test]
    public function the_validation_submission_carries_its_own_field_code(): void
    {
        $request = (new RequestBuilder)->build(
            $this->provider(),
            $this->endpoint([
                'service' => 'nin.validation',
                'http_method' => 'POST',
                'path' => '/nin/validation',
                'field_map' => ['nin' => 'nin', 'description' => 'description'],
                'static_fields' => ['field_code' => '015'],
            ]),
            ['nin' => '12345678901', 'description' => 'My Reference'],
        );

        $this->assertEqualsCanonicalizing([
            'field_code' => '015',
            'nin' => '12345678901',
            'description' => 'My Reference',
        ], $request['body']);
    }

    #[Test]
    public function the_ipe_status_check_is_a_get_with_the_tracking_id_in_the_query(): void
    {
        $request = (new RequestBuilder)->build(
            $this->provider(),
            $this->endpoint([
                'service' => 'nin.ipe.status',
                'http_method' => 'GET',
                'path' => '/nin/ipe',
                'field_map' => ['tracking_id' => 'tracking_id'],
            ]),
            ['tracking_id' => '0T3NU7RSKMCHTT4'],
        );

        $this->assertSame('GET', $request['method']);
        $this->assertSame('https://api.arewasmart.com.ng/api/v1/nin/ipe', $request['url']);

        // A GET has no body: the mapped fields become query parameters.
        $this->assertSame([], $request['body']);
        $this->assertSame(['tracking_id' => '0T3NU7RSKMCHTT4'], $request['query']);
    }

    #[Test]
    public function the_validation_status_check_queries_by_nin(): void
    {
        $request = (new RequestBuilder)->build(
            $this->provider(),
            $this->endpoint([
                'service' => 'nin.validation.status',
                'http_method' => 'GET',
                'path' => '/nin/validation',
                'field_map' => ['nin' => 'nin'],
            ]),
            ['nin' => '71073866272'],
        );

        $this->assertSame([], $request['body']);
        $this->assertSame(['nin' => '71073866272'], $request['query']);
    }

    /**
     * The regression itself: a field_code mapped as a request field never
     * reaches the provider, because a field map only renames values the caller
     * actually sent.
     */
    #[Test]
    public function a_field_code_in_the_field_map_is_never_sent(): void
    {
        $request = (new RequestBuilder)->build(
            $this->provider(),
            $this->endpoint([
                'service' => 'nin.ipe',
                'http_method' => 'POST',
                'path' => '/nin/ipe',
                // What the admin form produces when field_code is offered as a
                // mappable input — including a value table holding "002".
                'field_map' => [
                    'tracking_id' => 'tracking_id',
                    'field_code' => ['field' => 'field_code', 'values' => ['002' => '002']],
                ],
                'static_fields' => [],
            ]),
            ['tracking_id' => '0T3NU7RSKMCHTT4'],
        );

        $this->assertArrayNotHasKey('field_code', $request['body']);
    }
}
