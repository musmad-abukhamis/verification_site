<?php

namespace Tests\Unit;

use App\Support\DataRequestNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * The normalizer exists so an integrator already wired to another data-vending
 * API can point it at us without renaming every field. The cases below are the
 * body shapes those APIs actually document.
 */
class DataRequestNormalizerTest extends TestCase
{
    public function test_it_maps_numeric_network_ids(): void
    {
        $this->assertSame('mtn', DataRequestNormalizer::network(1));
        $this->assertSame('airtel', DataRequestNormalizer::network('2'));
        $this->assertSame('glo', DataRequestNormalizer::network(3));
        $this->assertSame('9mobile', DataRequestNormalizer::network('4'));
    }

    public function test_it_accepts_network_names_in_any_case(): void
    {
        $this->assertSame('mtn', DataRequestNormalizer::network('MTN'));
        $this->assertSame('glo', DataRequestNormalizer::network('Globacom'));
        $this->assertSame('9mobile', DataRequestNormalizer::network('Etisalat'));
        $this->assertSame('9mobile', DataRequestNormalizer::network('9Mobile'));
    }

    /**
     * Passed through, not nulled -- validation then names the bad value back to
     * the caller instead of silently treating it as "no network given".
     */
    public function test_an_unknown_network_survives_for_validation(): void
    {
        $this->assertSame('7', DataRequestNormalizer::network(7));
        $this->assertSame('starlink', DataRequestNormalizer::network('Starlink'));
    }

    public function test_it_normalizes_phone_formats(): void
    {
        $this->assertSame('08012345678', DataRequestNormalizer::phone('08012345678'));
        $this->assertSame('08012345678', DataRequestNormalizer::phone('+2348012345678'));
        $this->assertSame('08012345678', DataRequestNormalizer::phone('2348012345678'));
        $this->assertSame('08012345678', DataRequestNormalizer::phone('0801-234-5678'));
        $this->assertSame('08012345678', DataRequestNormalizer::phone('8012345678'));
    }

    /**
     * Body from a provider whose fields are network/phone/data_plan/request-id.
     */
    public function test_it_normalizes_the_first_provider_style(): void
    {
        $out = DataRequestNormalizer::normalize([
            'network' => 1,
            'phone' => '07063523516',
            'data_plan' => 1,
            'bypass' => false,
            'request-id' => 'Data_12345678900',
        ]);

        $this->assertSame('mtn', $out['network']);
        $this->assertSame('07063523516', $out['phone']);
        $this->assertSame(1, $out['plan_id']);
        $this->assertFalse($out['ported']);
        $this->assertSame('Data_12345678900', $out['client_ref']);
    }

    /**
     * Body using mobile_number/plan/Ported_number, plus a payment_medium field
     * we have no equivalent for.
     */
    public function test_it_normalizes_the_second_provider_style(): void
    {
        $out = DataRequestNormalizer::normalize([
            'network' => 2,
            'mobile_number' => '09095263835',
            'plan' => 7,
            'Ported_number' => true,
            'payment_medium' => 'MAIN WALLET',
        ]);

        $this->assertSame('airtel', $out['network']);
        $this->assertSame('09095263835', $out['phone']);
        $this->assertSame(7, $out['plan_id']);
        $this->assertTrue($out['ported']);
    }

    /**
     * Body using ref/ported_number with everything as strings.
     */
    public function test_it_normalizes_the_third_provider_style(): void
    {
        $out = DataRequestNormalizer::normalize([
            'network' => '1',
            'phone' => '07032529431',
            'ref' => '4545567k4h5300',
            'data_plan' => '1',
            'ported_number' => false,
        ]);

        $this->assertSame('mtn', $out['network']);
        $this->assertSame('07032529431', $out['phone']);
        $this->assertSame('1', $out['plan_id']);
        $this->assertFalse($out['ported']);
    }

    /**
     * The caller's own order id is stored verbatim: it is both the idempotency
     * key and what we echo back, so they can match our response to their order.
     */
    public function test_a_caller_reference_is_kept_verbatim(): void
    {
        $this->assertSame('Data_12345678900', DataRequestNormalizer::clientRef('Data_12345678900'));
        $this->assertSame('ORDER-77', DataRequestNormalizer::clientRef('  ORDER-77  '));
    }

    public function test_an_existing_uuid_is_kept_as_is(): void
    {
        $uuid = '3f2504e0-4f89-41d3-9a0c-0305e82c3301';

        $this->assertSame($uuid, DataRequestNormalizer::clientRef($uuid));
    }

    public function test_it_recovers_the_reference_the_caller_actually_sent(): void
    {
        $this->assertSame('Data_123', DataRequestNormalizer::originalReference(['request-id' => 'Data_123']));
        $this->assertSame('ORDER-9', DataRequestNormalizer::originalReference(['ref' => 'ORDER-9']));
        $this->assertNull(DataRequestNormalizer::originalReference(['phone' => '08012345678']));
    }

    public function test_a_missing_reference_gets_a_fresh_one(): void
    {
        $this->assertNotSame(
            DataRequestNormalizer::clientRef(null),
            DataRequestNormalizer::clientRef(null),
        );
    }
}
