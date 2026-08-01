<script setup>
/**
 * A single figure. The label sits above the number rather than below it, so a
 * column of tiles scans as a list of questions with answers, and the numbers
 * all sit on one optical baseline regardless of label length.
 */
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    /** Small qualifier under the number — a delta, a period, a count. */
    hint: { type: String, default: null },
    /** Accent stripe. Keep to one meaningful tile per group. */
    tone: {
        type: String,
        default: 'neutral',
        validator: (v) => ['neutral', 'brand', 'brass', 'success', 'danger', 'warning', 'info'].includes(v),
    },
    /** Heroicon-style path data. */
    icon: { type: String, default: null },
});

const STRIPE = {
    neutral: 'bg-ink-300 dark:bg-ink-700',
    brand: 'bg-brand-600 dark:bg-brand-500',
    brass: 'bg-brass-500',
    success: 'bg-success-500',
    danger: 'bg-danger-500',
    warning: 'bg-warning-500',
    info: 'bg-info-500',
};

const ICON = {
    neutral: 'text-ink-400 dark:text-ink-500',
    brand: 'text-brand-700 dark:text-brand-300',
    brass: 'text-brass-600 dark:text-brass-400',
    success: 'text-success-600 dark:text-success-400',
    danger: 'text-danger-600 dark:text-danger-400',
    warning: 'text-warning-600 dark:text-warning-400',
    info: 'text-info-600 dark:text-info-400',
};
</script>

<template>
    <div class="card card-pad relative overflow-hidden">
        <!-- The stripe is the only colour on the tile, so the number stays ink. -->
        <span class="absolute inset-y-0 left-0 w-1" :class="STRIPE[tone]" aria-hidden="true"></span>

        <div class="flex items-start justify-between gap-3 pl-2">
            <div class="min-w-0">
                <p class="eyebrow">{{ label }}</p>
                <p class="mt-2 font-mono text-2xl font-semibold tracking-tight text-ink-950 dark:text-white sm:text-[1.75rem]">
                    {{ value }}
                </p>
                <p v-if="hint" class="mt-1 text-xs text-ink-500 dark:text-ink-400">{{ hint }}</p>
            </div>

            <svg
                v-if="icon"
                class="h-5 w-5 shrink-0"
                :class="ICON[tone]"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="icon" />
            </svg>
        </div>
    </div>
</template>
