<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * The balance line every priced service page opens with. Before the redesign
 * each one carried its own gradient banner in a different hue, which made the
 * same number look like a different number on every screen.
 *
 * Deliberately quieter than the sidebar slip: the balance is context here, not
 * the subject. Pass `price` to show what the action on this page will cost.
 */
defineProps({
    wallet: { type: Object, required: true },
    /** What one run of this service costs, if the page charges. */
    price: { type: [Number, String], default: null },
});

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));
</script>

<template>
    <div class="card flex flex-wrap items-center justify-between gap-4 px-5 py-4">
        <div>
            <p class="eyebrow">Wallet balance</p>
            <p class="mt-1 font-mono text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">
                {{ formatCurrency(wallet.total_balance) }}
            </p>
            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">
                Main {{ formatCurrency(wallet.balance) }} · Bonus {{ formatCurrency(wallet.bonus_balance) }}
            </p>
        </div>

        <div class="flex items-center gap-4">
            <!-- The price sits on brass because it is money leaving the wallet. -->
            <div v-if="price !== null" class="text-right">
                <p class="eyebrow">Price per run</p>
                <p class="mt-1 font-mono text-xl font-semibold text-brass-700 dark:text-brass-400">
                    {{ formatCurrency(price) }}
                </p>
            </div>
            <Link :href="route('wallet.fund')" class="btn btn-accent btn-sm">Fund</Link>
        </div>
    </div>
</template>
