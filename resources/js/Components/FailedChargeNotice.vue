<script setup>
/**
 * Tells the user, before they submit, that a verification the register answers
 * "no" to still costs money.
 *
 * The admin toggle for this lives in Admin > Verification > Routing, and when
 * it is off the controller sends `notice: null` and nothing renders — there is
 * no "failed verifications are free" variant, because that is the default the
 * user already assumes.
 *
 * The wording has to survive a support ticket, so it draws the line the billing
 * code actually draws: a definitive negative from the register is charged, a
 * timeout or a provider error is not.
 */
const props = defineProps({
    // { identity_type: 'nin'|'bvn', amount: number } | null
    notice: { type: Object, default: null },
});

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 0,
    }).format(Number(amount));

const label = () => (props.notice?.identity_type === 'bvn' ? 'BVN' : 'NIN');
</script>

<template>
    <div
        v-if="notice"
        class="rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 dark:border-warning-900 dark:bg-warning-950"
        role="note"
    >
        <div class="flex gap-2.5">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-warning-600 dark:text-warning-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            <div class="text-sm text-warning-800 dark:text-warning-300">
                <p class="font-semibold">
                    Failed verifications cost {{ formatCurrency(notice.amount) }}
                </p>
                <p class="mt-1 text-xs leading-relaxed text-warning-700 dark:text-warning-400">
                    If the register confirms there is no record for the {{ label() }} you enter, the
                    verification fee is refunded in full and {{ formatCurrency(notice.amount) }} is
                    charged instead. Network problems, timeouts and provider errors are never
                    charged — those are refunded in full, so please check the number carefully
                    before you submit.
                </p>
            </div>
        </div>
    </div>
</template>
