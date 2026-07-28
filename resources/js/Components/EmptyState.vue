<script setup>
/**
 * An empty screen is an invitation to act, so the default slot is for the
 * action that fills it. Never used to apologise for the absence of data.
 */
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
    /** Heroicon-style path data. */
    icon: { type: String, default: null },
});
</script>

<template>
    <div class="px-6 py-14 text-center">
        <span
            v-if="icon"
            class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-ink-100 text-ink-400 dark:bg-ink-800 dark:text-ink-500"
        >
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="icon" />
            </svg>
        </span>

        <p class="font-display text-base font-semibold text-ink-900 dark:text-ink-100">{{ title }}</p>
        <p v-if="description" class="mx-auto mt-1.5 max-w-sm text-sm text-ink-500 dark:text-ink-400">
            {{ description }}
        </p>

        <div v-if="$slots.default" class="mt-5 flex flex-wrap items-center justify-center gap-2">
            <slot />
        </div>
    </div>
</template>
