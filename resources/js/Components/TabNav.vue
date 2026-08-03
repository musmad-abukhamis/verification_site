<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * The tab strip on a history screen.
 *
 * Tabs are links, not local state: each one is a distinct server query and a
 * distinct URL, so a view can be shared and the back button works. Switching
 * tabs deliberately drops the filters — a search for a tracking ID means
 * nothing in the tab that lists BVNs, and carrying it over silently returns an
 * empty table.
 *
 * The count sits in the tab because "how many do I have" is usually the
 * question that made someone open the page.
 */
defineProps({
    /** [{ key, label, count }] */
    tabs: { type: Array, required: true },
    /** Key of the tab currently rendered. */
    active: { type: String, required: true },
    /** Ziggy route name every tab links to. */
    routeName: { type: String, required: true },
});
</script>

<template>
    <nav class="scroll-slim -mb-px flex gap-1 overflow-x-auto border-b rule" aria-label="History sections">
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="route(routeName, { tab: tab.key })"
            preserve-scroll
            :aria-current="tab.key === active ? 'page' : undefined"
            :class="[
                'group inline-flex shrink-0 items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold transition',
                tab.key === active
                    ? 'border-brand-600 text-ink-950 dark:border-brand-400 dark:text-white'
                    : 'border-transparent text-ink-500 hover:border-ink-300 hover:text-ink-800 dark:text-ink-400 dark:hover:border-ink-700 dark:hover:text-ink-100',
            ]"
        >
            {{ tab.label }}

            <span
                v-if="tab.count !== undefined && tab.count !== null"
                :class="[
                    'rounded-full px-2 py-0.5 font-mono text-2xs font-semibold',
                    tab.key === active
                        ? 'bg-brand-600 text-white dark:bg-brand-500'
                        : 'bg-ink-100 text-ink-500 dark:bg-ink-800 dark:text-ink-400',
                ]"
            >
                {{ tab.count }}
            </span>
        </Link>
    </nav>
</template>
