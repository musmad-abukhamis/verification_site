<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * The footer of every list in the app. Takes a Laravel paginator straight from
 * the controller, so pages never hand-roll the link loop or the count line.
 *
 * Sits flush at the bottom of a `.card`, hence the top rule and no rounding.
 */
defineProps({
    paginator: { type: Object, required: true },
    /** Noun for the count line — "records", "purchases", "users". */
    label: { type: String, default: 'results' },
});
</script>

<template>
    <nav
        v-if="paginator?.total"
        class="flex flex-col gap-3 border-t rule px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
        aria-label="Pagination"
    >
        <p class="text-sm text-ink-500 dark:text-ink-400">
            Showing
            <span class="font-mono font-semibold text-ink-800 dark:text-ink-200">
                {{ paginator.from ?? 0 }}–{{ paginator.to ?? 0 }}
            </span>
            of
            <span class="font-mono font-semibold text-ink-800 dark:text-ink-200">{{ paginator.total }}</span>
            {{ label }}
        </p>

        <!-- Three links means prev / page 1 / next: there is nothing to page. -->
        <div v-if="paginator.links?.length > 3" class="flex flex-wrap items-center gap-1">
            <Link
                v-for="link in paginator.links"
                :key="link.label"
                :href="link.url || ''"
                preserve-scroll
                :aria-current="link.active ? 'page' : undefined"
                :class="[
                    'inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2.5 text-sm font-semibold transition',
                    link.active
                        ? 'bg-brand-800 text-white dark:bg-brand-700'
                        : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900 dark:text-ink-300 dark:hover:bg-ink-800 dark:hover:text-white',
                    !link.url && 'pointer-events-none opacity-40',
                ]"
                v-html="link.label"
            />
        </div>
    </nav>
</template>
