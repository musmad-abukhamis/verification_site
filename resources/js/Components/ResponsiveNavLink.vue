<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'block w-full border-l-[3px] border-brass-500 bg-brand-50 py-2 pe-4 ps-3 text-start text-base font-semibold text-brand-800 transition dark:bg-brand-950/50 dark:text-brand-200'
        : 'block w-full border-l-[3px] border-transparent py-2 pe-4 ps-3 text-start text-base font-medium text-ink-600 transition hover:border-ink-300 hover:bg-ink-50 hover:text-ink-900 dark:text-ink-400 dark:hover:border-ink-600 dark:hover:bg-ink-800 dark:hover:text-white',
);
</script>

<template>
    <Link :href="href" :class="classes" :aria-current="active ? 'page' : undefined">
        <slot />
    </Link>
</template>
