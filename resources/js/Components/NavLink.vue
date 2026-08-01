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
        ? 'inline-flex items-center border-b-2 border-brand-500 px-1 pt-1 text-sm font-semibold leading-5 text-ink-950 transition dark:text-white'
        : 'inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium leading-5 text-ink-500 transition hover:border-ink-300 hover:text-ink-800 dark:text-ink-400 dark:hover:border-ink-600 dark:hover:text-ink-100',
);
</script>

<template>
    <Link :href="href" :class="classes" :aria-current="active ? 'page' : undefined">
        <slot />
    </Link>
</template>
