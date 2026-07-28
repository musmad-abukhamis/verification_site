<script setup>
/**
 * Every screen opens the same way: a small eyebrow naming where you are, the
 * title, and an optional one-line explanation of what the screen is for.
 * Actions sit to the right on desktop and wrap underneath on mobile.
 */
defineProps({
    eyebrow: { type: String, default: null },
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p v-if="eyebrow" class="eyebrow">{{ eyebrow }}</p>
            <h1 class="mt-1 font-display text-2xl font-bold tracking-tight text-ink-950 dark:text-white sm:text-3xl">
                {{ title }}
            </h1>
            <p v-if="description" class="mt-1.5 max-w-2xl text-sm text-ink-500 dark:text-ink-400">
                {{ description }}
            </p>
        </div>

        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
