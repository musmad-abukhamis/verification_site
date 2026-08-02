<?php

namespace Tests\Unit;

use App\Services\Verification\WorkStatusNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The status vocabularies of the two live vendors, and the traps in them.
 */
class WorkStatusNormalizerTest extends TestCase
{
    private WorkStatusNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new WorkStatusNormalizer;
    }

    #[Test]
    public function arewasmart_pending_ipe_stays_processing(): void
    {
        // The important case: `success: true` here means the *call* worked. The
        // clearance itself has not been touched.
        $reading = $this->normalizer->normalize([
            'success' => true,
            'tracking_id' => '0T3NU7RSKMCHTT4',
            'status' => 'processing',
            'comment' => 'status: New, reply: SUCCESSFUL, nin: 12345678900, name: MUSA TANKO',
            'message' => 'Status checked.',
        ]);

        $this->assertSame('processing', $reading['status']);
        $this->assertFalse($reading['terminal']);
        $this->assertTrue($reading['recognised']);
        $this->assertStringContainsString('MUSA TANKO', $reading['detail']);
    }

    #[Test]
    public function arewasmart_pending_validation_stays_processing(): void
    {
        $reading = $this->normalizer->normalize([
            'success' => true,
            'nin' => '71073866272',
            'status' => 'processing',
            'comment' => 'nin: 71073866272, status: New, validationErrorType: NO RECORD FUND, reply: null',
            'message' => 'Status checked.',
        ]);

        $this->assertSame('processing', $reading['status']);
        // The reason a validation is stuck only ever appears in this string, so
        // it has to survive to the user.
        $this->assertStringContainsString('NO RECORD FUND', $reading['detail']);
    }

    #[Test]
    public function robost_completed_clearance_is_terminal(): void
    {
        $reading = $this->normalizer->normalize([
            'message' => 'Clearance Successfull',
            'cleared' => true,
            'success' => true,
            'status' => 'completed',
            'tracking_id' => '0RQ6C5ASWFS36LU',
            'reply' => '2GVZ0SI8KO000VK',
        ]);

        $this->assertSame('completed', $reading['status']);
        $this->assertTrue($reading['terminal']);
        $this->assertSame('2GVZ0SI8KO000VK', $reading['reply']);
    }

    #[Test]
    public function robost_failed_clearance_is_failed(): void
    {
        $reading = $this->normalizer->normalize([
            'success' => false,
            'tracking_id' => '0SN5NFZSGDEGZ0N',
            'message' => 'Previous Clearance Failed',
        ]);

        $this->assertSame('failed', $reading['status']);
        $this->assertTrue($reading['terminal']);
        $this->assertSame('Previous Clearance Failed', $reading['detail']);
    }

    #[Test]
    public function a_false_verdict_alongside_a_pending_status_is_not_a_rejection(): void
    {
        // "not cleared yet" reads identically to "refused" unless the status
        // word is allowed to disambiguate it.
        $reading = $this->normalizer->normalize([
            'success' => true,
            'cleared' => false,
            'status' => 'processing',
        ]);

        $this->assertSame('processing', $reading['status']);
        $this->assertFalse($reading['terminal']);
    }

    #[Test]
    public function success_true_alone_is_never_a_completion(): void
    {
        // A bare acknowledgement says the request was accepted, nothing more.
        $reading = $this->normalizer->normalize(['success' => true, 'message' => 'Status checked.']);

        $this->assertFalse($reading['recognised']);
        $this->assertSame('processing', $reading['status']);
    }

    #[Test]
    public function an_error_envelope_is_not_recognised_as_a_status(): void
    {
        $reading = $this->normalizer->normalize(['error' => 'no_provider_configured'], 'processing');

        $this->assertFalse($reading['recognised']);
    }

    #[Test]
    public function a_wrapped_status_is_found(): void
    {
        $reading = $this->normalizer->normalize([
            'success' => true,
            'data' => ['status' => 'completed', 'response' => 'Validation approved'],
        ]);

        $this->assertSame('completed', $reading['status']);
        $this->assertSame('Validation approved', $reading['detail']);
    }

    #[Test]
    public function the_literal_string_null_is_not_shown_as_a_reply(): void
    {
        $reading = $this->normalizer->normalize([
            'success' => true,
            'status' => 'processing',
            'reply' => 'null',
        ]);

        $this->assertNull($reading['reply']);
    }
}
