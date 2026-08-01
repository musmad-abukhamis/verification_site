<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import BalanceStrip from '@/Components/BalanceStrip.vue';
import Alert from '@/Components/Alert.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    wallet: Object,
    prices: Object,
});

const activeTab = ref('old');

const form = useForm({
    serviceType: '',
    bvn: '',
    nin: '',
    ninSlip: null,
    oldFirstName: '',
    oldMiddleName: '',
    oldLastName: '',
    oldDob: '',
    oldPhoneNumber: '',
    newFirstName: '',
    newMiddleName: '',
    newLastName: '',
    newDob: '',
    newPhoneNumber: '',
});

// serviceType → bvnserviceprices column
const priceColumnMap = {
    'modify-name': 'name_mod',
    'modify-dob': 'dob_mod',
    'modify-name-dob': 'namedob_mod',
    'modify-phone': 'phone_mod',
    'modify-name-dob-phone': 'namephonedob_mod',
};

const serviceOptions = [
    { value: 'modify-name', label: 'Name modification', column: 'name_mod', description: 'Update your name information' },
    { value: 'modify-dob', label: 'DOB modification', column: 'dob_mod', description: 'Correct your date of birth' },
    { value: 'modify-phone', label: 'Phone number modification', column: 'phone_mod', description: 'Update your phone number' },
    { value: 'modify-name-dob', label: 'Name & DOB modification', column: 'namedob_mod', description: 'Update both name and date of birth' },
    { value: 'modify-name-dob-phone', label: 'Complete profile modification', column: 'namephonedob_mod', description: 'Update name, date of birth, and phone number' },
];

const needsName = computed(() => ['modify-name', 'modify-name-dob', 'modify-name-dob-phone'].includes(form.serviceType));
const needsDob = computed(() => ['modify-dob', 'modify-name-dob', 'modify-name-dob-phone'].includes(form.serviceType));
const needsPhone = computed(() => ['modify-phone', 'modify-name-dob-phone'].includes(form.serviceType));

const formatCurrency = (amount) => {
    if (amount === null || amount === undefined || amount === '' || amount === '0' || Number(amount) === 0) return 'Contact support';
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(Number(amount));
};

const selectedPrice = computed(() => {
    const col = priceColumnMap[form.serviceType];
    return col ? props.prices?.[col] : null;
});

const isAvailable = (amount) => amount && amount !== '0' && amount !== '' && Number(amount) > 0;

const onFile = (e) => {
    form.ninSlip = e.target.files?.[0] || null;
};

const submit = () => {
    form.post(route('bvn-modification.store'), {
        preserveScroll: true,
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="BVN Modification" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <PageHeader
                eyebrow="BVN services"
                title="BVN modification"
                description="Pick what needs correcting, then give us the record as it stands and as it should read."
            >
                <template #actions>
                    <Link :href="route('bvn-modification.requests')" class="btn btn-secondary">My requests</Link>
                </template>
            </PageHeader>

            <BalanceStrip :wallet="wallet" :price="selectedPrice" />

            <div class="space-y-3">
                <Alert v-if="form.errors.message" tone="danger">{{ form.errors.message }}</Alert>
                <Alert v-if="$page.props.flash?.success" tone="success">{{ $page.props.flash.success }}</Alert>
                <Alert v-if="form.errors.serviceType" tone="danger">{{ form.errors.serviceType }}</Alert>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Service type. One control, not two: the priced list *is* the
                     selector, so the price is never a step removed from the choice. -->
                <fieldset class="card card-pad">
                    <legend class="font-display text-base font-semibold text-ink-950 dark:text-white">
                        What needs changing?
                    </legend>

                    <div class="mt-4 space-y-2">
                        <label
                            v-for="opt in serviceOptions"
                            :key="opt.value"
                            :class="[
                                'flex cursor-pointer items-center justify-between gap-4 rounded-lg border p-3 transition',
                                form.serviceType === opt.value
                                    ? 'border-brand-700 bg-brand-50 dark:border-brand-500 dark:bg-brand-950/50'
                                    : 'border-ink-200 hover:border-ink-300 dark:border-ink-800 dark:hover:border-ink-700',
                            ]"
                        >
                            <span class="flex min-w-0 items-start gap-3">
                                <input type="radio" v-model="form.serviceType" :value="opt.value" class="mt-1 shrink-0" />
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-ink-900 dark:text-ink-100">{{ opt.label }}</span>
                                    <span class="block text-xs text-ink-500 dark:text-ink-400">{{ opt.description }}</span>
                                </span>
                            </span>

                            <span
                                class="pill shrink-0"
                                :class="isAvailable(prices?.[opt.column]) ? 'pill-brass' : 'pill-pending'"
                            >
                                {{ formatCurrency(prices?.[opt.column]) }}
                            </span>
                        </label>
                    </div>
                </fieldset>

                <!-- Identity -->
                <section class="card card-pad">
                    <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">Identity</h2>

                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="bvn" class="eyebrow">BVN</label>
                            <input id="bvn" v-model="form.bvn" type="text" inputmode="numeric" class="mt-1.5 block w-full font-mono" />
                            <p v-if="form.errors.bvn" class="mt-1 text-xs font-medium text-danger-600 dark:text-danger-400">{{ form.errors.bvn }}</p>
                        </div>
                        <div>
                            <label for="nin" class="eyebrow">NIN</label>
                            <input id="nin" v-model="form.nin" type="text" inputmode="numeric" class="mt-1.5 block w-full font-mono" />
                            <p v-if="form.errors.nin" class="mt-1 text-xs font-medium text-danger-600 dark:text-danger-400">{{ form.errors.nin }}</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="ninSlip" class="eyebrow">Attach NIN slip</label>
                        <input
                            id="ninSlip"
                            type="file"
                            accept="image/jpeg,image/png,application/pdf"
                            @change="onFile"
                            class="mt-1.5 block w-full text-sm text-ink-600 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:text-ink-300 dark:file:bg-brand-950 dark:file:text-brand-300"
                        />
                        <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">JPEG, PNG or PDF — max 5MB.</p>
                        <p v-if="form.errors.ninSlip" class="mt-1 text-xs font-medium text-danger-600 dark:text-danger-400">{{ form.errors.ninSlip }}</p>
                    </div>
                </section>

                <!-- Old / new record -->
                <section v-if="form.serviceType" class="card card-pad">
                    <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">The record</h2>
                    <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
                        Fill both sides: what the record says now, and what it should say.
                    </p>

                    <div class="mt-4 grid grid-cols-2 overflow-hidden rounded-lg border rule">
                        <button
                            type="button"
                            @click="activeTab = 'old'"
                            :aria-pressed="activeTab === 'old'"
                            :class="[
                                'py-2 text-sm font-semibold transition',
                                activeTab === 'old'
                                    ? 'bg-brand-600 text-white dark:bg-brand-600'
                                    : 'bg-ink-50 text-ink-600 hover:text-ink-900 dark:bg-ink-950/40 dark:text-ink-300 dark:hover:text-white',
                            ]"
                        >
                            Old record
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'new'"
                            :aria-pressed="activeTab === 'new'"
                            :class="[
                                'py-2 text-sm font-semibold transition',
                                activeTab === 'new'
                                    ? 'bg-brand-600 text-white dark:bg-brand-600'
                                    : 'bg-ink-50 text-ink-600 hover:text-ink-900 dark:bg-ink-950/40 dark:text-ink-300 dark:hover:text-white',
                            ]"
                        >
                            New record
                        </button>
                    </div>

                    <!-- OLD -->
                    <div v-show="activeTab === 'old'" class="mt-4 space-y-4">
                        <div v-if="needsName" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <label for="oldFirstName" class="eyebrow">First name</label>
                                <input id="oldFirstName" v-model="form.oldFirstName" type="text" class="mt-1.5 block w-full" />
                            </div>
                            <div>
                                <label for="oldMiddleName" class="eyebrow">Middle name</label>
                                <input id="oldMiddleName" v-model="form.oldMiddleName" type="text" class="mt-1.5 block w-full" />
                            </div>
                            <div>
                                <label for="oldLastName" class="eyebrow">Last name</label>
                                <input id="oldLastName" v-model="form.oldLastName" type="text" class="mt-1.5 block w-full" />
                            </div>
                        </div>
                        <div v-if="needsDob">
                            <label for="oldDob" class="eyebrow">Date of birth</label>
                            <input id="oldDob" v-model="form.oldDob" type="date" class="mt-1.5 block w-full" />
                        </div>
                        <div v-if="needsPhone">
                            <label for="oldPhoneNumber" class="eyebrow">Phone number</label>
                            <input id="oldPhoneNumber" v-model="form.oldPhoneNumber" type="tel" class="mt-1.5 block w-full font-mono" />
                        </div>
                    </div>

                    <!-- NEW -->
                    <div v-show="activeTab === 'new'" class="mt-4 space-y-4">
                        <div v-if="needsName" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <label for="newFirstName" class="eyebrow">First name</label>
                                <input id="newFirstName" v-model="form.newFirstName" type="text" class="mt-1.5 block w-full" />
                            </div>
                            <div>
                                <label for="newMiddleName" class="eyebrow">Middle name</label>
                                <input id="newMiddleName" v-model="form.newMiddleName" type="text" class="mt-1.5 block w-full" />
                            </div>
                            <div>
                                <label for="newLastName" class="eyebrow">Last name</label>
                                <input id="newLastName" v-model="form.newLastName" type="text" class="mt-1.5 block w-full" />
                            </div>
                        </div>
                        <div v-if="needsDob">
                            <label for="newDob" class="eyebrow">Date of birth</label>
                            <input id="newDob" v-model="form.newDob" type="date" class="mt-1.5 block w-full" />
                        </div>
                        <div v-if="needsPhone">
                            <label for="newPhoneNumber" class="eyebrow">Phone number</label>
                            <input id="newPhoneNumber" v-model="form.newPhoneNumber" type="tel" class="mt-1.5 block w-full font-mono" />
                        </div>
                    </div>
                </section>

                <!-- Held to a sane width: a full-bleed submit button across a
                     wide desktop column reads as a banner, not a control. -->
                <div class="max-w-md space-y-2">
                    <button type="submit" :disabled="form.processing || !form.serviceType" class="btn btn-primary btn-lg w-full">
                        <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        <span v-if="form.processing">Submitting…</span>
                        <span v-else>
                            Submit request<template v-if="form.serviceType"> — {{ formatCurrency(selectedPrice) }}</template>
                        </span>
                    </button>

                    <p v-if="form.serviceType" class="text-center text-xs text-ink-500 dark:text-ink-400">
                        By submitting this request, you agree to the service fee which will be deducted from your wallet balance.
                    </p>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
