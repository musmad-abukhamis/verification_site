<script setup>
import { computed } from 'vue';

/**
 * The single source of truth for how a status looks anywhere in the app.
 *
 * Statuses arrive from several tables with slightly different vocabularies
 * (`fail` vs `failed`, `success` vs `successful`, `refunded_unconfirmed`), so
 * the mapping is deliberately generous. Anything unrecognised falls back to
 * neutral rather than silently borrowing a meaningful colour.
 */
const props = defineProps({
    status: { type: [String, Boolean, null], default: null },
    /** Overrides the displayed text; the colour still comes from `status`. */
    label: { type: String, default: null },
});

const TONES = {
    success: 'pill-success',
    successful: 'pill-success',
    completed: 'pill-success',
    complete: 'pill-success',
    approved: 'pill-success',
    delivered: 'pill-success',
    active: 'pill-success',
    verified: 'pill-success',
    credit: 'pill-success',
    paid: 'pill-success',

    pending: 'pill-pending',
    queued: 'pill-pending',
    awaiting: 'pill-pending',
    submitted: 'pill-pending',
    unconfirmed: 'pill-pending',

    processing: 'pill-info',
    inprogress: 'pill-info',
    in_progress: 'pill-info',
    running: 'pill-info',
    sent: 'pill-info',

    fail: 'pill-danger',
    failed: 'pill-danger',
    error: 'pill-danger',
    rejected: 'pill-danger',
    declined: 'pill-danger',
    cancelled: 'pill-danger',
    canceled: 'pill-danger',
    inactive: 'pill-danger',
    debit: 'pill-danger',

    refunded: 'pill-refund',
    refunded_unconfirmed: 'pill-refund',
    refund: 'pill-refund',
    reversed: 'pill-refund',
};

const key = computed(() => String(props.status ?? '').toLowerCase().trim());

const tone = computed(() => TONES[key.value] ?? 'pill-neutral');

const text = computed(() => props.label ?? key.value.replace(/_/g, ' ') ?? '—');
</script>

<template>
    <span class="pill" :class="tone">{{ text || '—' }}</span>
</template>
