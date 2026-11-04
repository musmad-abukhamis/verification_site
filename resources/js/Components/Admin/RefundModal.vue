<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

/**
 * Admin refund dialog for a staff-fulfilled request.
 *
 * `record` must carry `id` and `charged_amount` (from the backend's
 * refundPayload()); `details` are the label/value pairs shown for context.
 * Open it by passing a record, close it by the `close` event.
 */
const props = defineProps({
    record: { type: Object, default: null },
    routeName: { type: String, required: true },
    title: { type: String, default: 'Refund Fee' },
    details: { type: Array, default: () => [] },
    reasonPlaceholder: { type: String, default: 'e.g. Request could not be completed' },
});

const emit = defineEmits(['close']);

const form = useForm({ amount: 0, reason: '' });

const naira = (v) => '₦' + Number(v ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

watch(() => props.record, (r) => {
    if (!r) return;
    form.clearErrors();
    // Defaults to the full fee: a partial refund is the exception, not the norm.
    form.amount = r.charged_amount ?? 0;
    form.reason = '';
}, { immediate: true });

const submit = () => {
    form.post(route(props.routeName, props.record.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <div v-if="record" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="emit('close')">
        <div class="fixed inset-0 bg-black/50" @click="emit('close')"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-md w-full p-6 border-l-4 border-amber-400">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">{{ title }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Credits the wallet immediately. A request can only be refunded once.</p>

            <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg p-3 mb-4 grid grid-cols-2 gap-3 text-sm">
                <div v-for="d in details" :key="d.label">
                    <span class="text-gray-500 dark:text-gray-400">{{ d.label }}</span>
                    <p :class="['text-gray-900 dark:text-white break-words', d.mono ? 'font-mono' : '', d.capitalize ? 'capitalize' : '']">{{ d.value || '-' }}</p>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Charged</span>
                    <p class="text-gray-900 dark:text-white">{{ naira(record.charged_amount) }}</p>
                </div>
            </div>

            <div v-if="form.errors.message" class="mb-3 p-2 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded text-sm">{{ form.errors.message }}</div>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount (₦) *</label>
                    <input v-model="form.amount" type="number" step="0.01" min="0.01" :max="record.charged_amount"
                        class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Full fee by default. Lower it for a partial refund; it cannot exceed what the user paid.</p>
                    <p v-if="form.errors.amount" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.amount }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason</label>
                    <input v-model="form.reason" type="text" :placeholder="reasonPlaceholder"
                        class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Saved to the request's comment, which the user can see.</p>
                    <p v-if="form.errors.reason" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.reason }}</p>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="emit('close')" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-600 dark:text-gray-300">Cancel</button>
                    <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-lg disabled:opacity-50">
                        {{ form.processing ? 'Refunding...' : 'Refund User' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
