/**
 * Labels for the failed-verification charge shown in the user's history.
 *
 * A failed verification either cost the user the configured fee or it did not,
 * and when it did not the reason matters: "the provider said no but charging is
 * off" and "we never got an answer, so you were not billed" are very different
 * things to read on your own statement.
 *
 * Mirrors App\Models\FailedVerificationCharge's REASON_* constants.
 */
const REASONS = {
    CHARGED: 'charged',
    CHARGING_DISABLED: 'charging is off',
    ZERO_AMOUNT: 'no fee configured',
    NOT_A_VERIFICATION_FAILURE: 'technical/API failure',
    INSUFFICIENT_BALANCE: 'insufficient balance',
    DUPLICATE_ATTEMPT: 'already billed on the first attempt',
};

/**
 * @param {{ charge_reason?: string|null }} record a history row
 * @returns {string} a short phrase for why nothing was charged
 */
export function chargeReasonLabel(record) {
    return REASONS[record?.charge_reason] ?? 'not charged';
}
