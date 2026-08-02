<script setup>
/**
 * Settlement panel for an asynchronous NIN job — a validation (3 days to a week)
 * or an IPE clearance (30 minutes to 3 hours).
 *
 * These jobs are finished by people as often as by the API: a provider settles
 * one over the phone, a status endpoint stops answering, a customer disputes a
 * charge. So the panel offers the three actions in the order an admin actually
 * reaches for them — ask the provider again, then assert the outcome by hand,
 * then refund — and keeps the refund visually separate because it moves money.
 *
 * Route names are passed in rather than derived, so the same panel serves both
 * admin.nin-validations.* and admin.nin-ipe.*.
 */
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    record: { type: Object, required: true },
    statuses: { type: Array, default: () => ['processing', 'completed', 'failed'] },
    recheckRoute: { type: String, required: true },
    updateRoute: { type: String, required: true },
    refundRoute: { type: String, required: true },
});

const rechecking = ref(false);

const recheck = () => {
    if (rechecking.value) return;
    rechecking.value = true;
    router.post(route(props.recheckRoute, props.record.id), {}, {
        preserveScroll: true,
        onFinish: () => { rechecking.value = false; },
    });
};

const overrideForm = useForm({
    status: props.record.status,
    result: props.record.raw_result ?? props.record.result ?? '',
    comment: '',
});

const submitOverride = () => {
    overrideForm.put(route(props.updateRoute, props.record.id), { preserveScroll: true });
};

const refundForm = useForm({
    amount: props.record.charged_amount ?? 0,
    reason: '',
});

const submitRefund = () => {
    refundForm.post(route(props.refundRoute, props.record.id), { preserveScroll: true });
};

const charged = computed(() => Number(props.record.charged_amount ?? 0));
</script>

<template>
    <div class="space-y-6">
        <!-- Provider + automatic check -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Provider</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        <template v-if="record.provider">
                            Filed with <span class="font-medium text-gray-900 dark:text-white">{{ record.provider }}</span>.
                            Only this provider can report on the job.
                        </template>
                        <template v-else>
                            No provider recorded — this job predates provider tracking, so a check falls back
                            to the routed provider for the service.
                        </template>
                    </p>
                    <p v-if="record.provider_ref" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Upstream reference <span class="font-mono">{{ record.provider_ref }}</span>
                    </p>
                </div>
                <button
                    type="button"
                    @click="recheck"
                    :disabled="rechecking"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors"
                >
                    {{ rechecking ? 'Checking…' : 'Check status now' }}
                </button>
            </div>
        </div>

        <!-- Manual override -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Set status manually</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Records what you know, without contacting the provider. This does not move any money —
                use the refund below for that.
            </p>

            <form @submit.prevent="submitOverride" class="mt-4 space-y-4">
                <div>
                    <InputLabel for="job-status" value="Status" />
                    <select
                        id="job-status"
                        v-model="overrideForm.status"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-slate-900 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    >
                        <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                    </select>
                    <InputError class="mt-1" :message="overrideForm.errors.status" />
                </div>

                <div>
                    <InputLabel for="job-result" value="Result" />
                    <textarea
                        id="job-result"
                        v-model="overrideForm.result"
                        rows="4"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-slate-900 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm font-mono"
                    ></textarea>
                    <InputError class="mt-1" :message="overrideForm.errors.result" />
                </div>

                <div>
                    <InputLabel for="job-comment" value="Comment (shown to the user)" />
                    <TextInput
                        id="job-comment"
                        v-model="overrideForm.comment"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Leave blank to keep the current comment"
                    />
                    <InputError class="mt-1" :message="overrideForm.errors.comment" />
                </div>

                <button
                    type="submit"
                    :disabled="overrideForm.processing"
                    class="px-4 py-2 bg-gray-800 hover:bg-gray-900 dark:bg-gray-700 dark:hover:bg-gray-600 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors"
                >
                    {{ overrideForm.processing ? 'Saving…' : 'Save record' }}
                </button>
            </form>
        </div>

        <!-- Refund -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6 border-l-4 border-amber-400">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Refund</h2>

            <div v-if="record.refunded_at" class="mt-3 rounded-lg bg-gray-50 dark:bg-gray-900 p-4 text-sm text-gray-600 dark:text-gray-300">
                Already refunded
                <span class="font-medium text-gray-900 dark:text-white">₦{{ Number(record.refund_amount ?? 0).toLocaleString() }}</span>
                on {{ record.refunded_at }}. A record can only be refunded once.
            </div>

            <div v-else-if="charged <= 0" class="mt-3 rounded-lg bg-gray-50 dark:bg-gray-900 p-4 text-sm text-gray-600 dark:text-gray-300">
                Nothing was charged for this record, so there is nothing to refund.
            </div>

            <form v-else @submit.prevent="submitRefund" class="mt-4 space-y-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    The user paid <span class="font-medium text-gray-900 dark:text-white">₦{{ charged.toLocaleString() }}</span>.
                    Refund the full amount, or less for a partial delivery. Credits the wallet immediately.
                </p>

                <div>
                    <InputLabel for="refund-amount" value="Amount (₦)" />
                    <TextInput
                        id="refund-amount"
                        v-model="refundForm.amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        :max="charged"
                        class="mt-1 block w-full max-w-xs"
                    />
                    <InputError class="mt-1" :message="refundForm.errors.amount" />
                </div>

                <div>
                    <InputLabel for="refund-reason" value="Reason" />
                    <TextInput
                        id="refund-reason"
                        v-model="refundForm.reason"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="e.g. Provider could not clear this tracking ID"
                    />
                    <InputError class="mt-1" :message="refundForm.errors.reason" />
                </div>

                <InputError :message="refundForm.errors.message" />

                <button
                    type="submit"
                    :disabled="refundForm.processing"
                    class="px-4 py-2 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors"
                >
                    {{ refundForm.processing ? 'Refunding…' : 'Refund user' }}
                </button>
            </form>
        </div>
    </div>
</template>
