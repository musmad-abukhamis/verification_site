<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import BalanceStrip from '@/Components/BalanceStrip.vue';
import Alert from '@/Components/Alert.vue';
import StatusPill from '@/Components/StatusPill.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import BvnPremiumSlip from '@/Components/BvnPremiumSlip.vue';
import BvnLongSlip from '@/Components/BvnLongSlip.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    wallet: Object,
    slipTypes: Array,
    history: Object,
});

const page = usePage();
const result = ref(null);

const form = useForm({
    idValue: '',
    slipType: props.slipTypes?.[0]?.code || 'premium',
});

const selectedPrice = computed(() => props.slipTypes.find((t) => t.code === form.slipType)?.price ?? null);

const formatCurrency = (amount) => {
    if (amount === null || amount === undefined) return '—';
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(Number(amount));
};

const submit = () => {
    form.transform((d) => ({ ...d, idValue: d.idValue.replace(/\D/g, '') }))
        .post(route('bvn-verify.store'), {
            preserveScroll: true,
            onSuccess: () => {
                if (page.props.flash?.verification_data) {
                    result.value = page.props.flash.verification_data;
                    form.reset('idValue');
                }
            },
        });
};

const reset = () => { result.value = null; };

const formatBvn = (bvn) => {
    const s = String(bvn || '');
    return s.length === 11 ? `${s.slice(0, 4)} ${s.slice(4, 7)} ${s.slice(7, 11)}` : s;
};

const fmtDate = (d) => {
    if (!d) return '—';
    const date = new Date(d);
    return isNaN(date) ? d : date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const fullName = computed(() => {
    const r = result.value || {};
    return [r.surname, r.firstname, r.middlename].filter(Boolean).join(' ');
});

// Raw base64 JPEG (no data: prefix) -> data URL for the slip generators.
const photoDataUrl = computed(() => {
    const photo = result.value?.photo;
    return photo ? `data:image/jpeg;base64,${photo}` : '';
});

const printSlip = () => window.print();
</script>

<template>
    <Head title="BVN Verification" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <div class="print:hidden">
                <PageHeader
                    eyebrow="BVN services"
                    title="BVN verification"
                    description="Verify a BVN and generate a printable slip."
                />
            </div>

            <div class="print:hidden">
                <BalanceStrip :wallet="wallet" />
            </div>

            <!-- Form -->
            <!-- The card fills the column; only the fields are held to a
                 readable measure, so there's no gutter beside the sidebar. -->
            <section v-if="!result" class="card card-pad print:hidden">
                <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">Verify a BVN</h2>

                <div class="mt-4 space-y-3">
                    <Alert v-if="form.errors.message" tone="danger">{{ form.errors.message }}</Alert>
                    <Alert v-if="slipTypes.length === 0" tone="warning">
                        No BVN verification prices configured. An admin must set a Search Slip price under BVN Service Prices.
                    </Alert>
                </div>

                <form @submit.prevent="submit" class="mt-4 max-w-xl space-y-4">
                    <div>
                        <label for="idValue" class="eyebrow">BVN number</label>
                        <input
                            id="idValue"
                            v-model="form.idValue"
                            type="text"
                            maxlength="11"
                            inputmode="numeric"
                            placeholder="11-digit BVN"
                            class="mt-1.5 block w-full font-mono text-lg"
                        />
                        <p v-if="form.errors.idValue" class="mt-1 text-xs font-medium text-danger-600 dark:text-danger-400">
                            {{ form.errors.idValue }}
                        </p>
                        <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">
                            Please enter the 11-digit Bank Verification Number.
                        </p>
                    </div>

                    <div>
                        <label for="slipType" class="eyebrow">Slip type</label>
                        <select id="slipType" v-model="form.slipType" class="mt-1.5 block w-full">
                            <option v-for="t in slipTypes" :key="t.code" :value="t.code">
                                {{ t.name }} — {{ formatCurrency(t.price) }}
                            </option>
                        </select>
                    </div>

                    <button type="submit" :disabled="form.processing || slipTypes.length === 0" class="btn btn-primary btn-lg w-full">
                        <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {{ form.processing ? 'Verifying…' : `Verify BVN${selectedPrice ? ` — ${formatCurrency(selectedPrice)}` : ''}` }}
                    </button>
                </form>
            </section>

            <!-- Result + slip -->
            <div v-else class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <h2 class="font-display text-xl font-bold tracking-tight text-ink-950 dark:text-white">
                        Verification result
                    </h2>
                    <div class="flex gap-2">
                        <button type="button" @click="printSlip" class="btn btn-primary">Print slip</button>
                        <button type="button" @click="reset" class="btn btn-secondary">Verify another</button>
                    </div>
                </div>

                <!-- Downloadable PDF slips -->
                <div class="mx-auto grid w-full max-w-xl grid-cols-1 gap-3 sm:grid-cols-2 print:hidden">
                    <BvnPremiumSlip
                        :surname="result.surname || ''"
                        :othernames="[result.firstname, result.middlename].filter(Boolean).join(' ')"
                        :dob="result.dob || ''"
                        :gender="result.gender || ''"
                        :nin="String(result.bvn || '')"
                        :photo="photoDataUrl"
                        :issued-date="fmtDate(new Date())"
                        :qr-value="`BVN:${result.bvn || ''}|${fullName}`"
                        :watermark="String(result.bvn || 'VERIFIED')"
                    />
                    <BvnLongSlip
                        :bvn="String(result.bvn || '')"
                        :nin="String(result.nin || '')"
                        :first-name="result.firstname || ''"
                        :last-name="result.surname || ''"
                        :middle-name="result.middlename || ''"
                        :phone="result.phone || ''"
                        :email="result.email || ''"
                        :dob="result.dob || ''"
                        :gender="result.gender || ''"
                        :marital="result.marital_status || ''"
                        :state="result.state_of_origin || ''"
                        :lga="result.lga_of_origin || ''"
                        :address="result.residential_Address || ''"
                        :enrollment-bank="result.enrollment_bank || ''"
                        :enrollment-branch="result.enrollment_bank_branch || ''"
                        :reg-date="result.registration_date || ''"
                        :residential-addr="result.residential_Address || ''"
                        :image-url="photoDataUrl"
                    />
                </div>

                <!--
                    The printable slip. Deliberately light-only — it is an
                    artifact that ends up on paper, so it must not follow the
                    viewer's dark theme. Hence no dark: variants below.
                -->
                <div class="slip mx-auto max-w-xl overflow-hidden bg-white text-ink-900">
                    <div class="bg-brand-800 px-6 py-3 text-center text-white">
                        <p class="text-2xs font-semibold uppercase tracking-[0.14em] text-brass-300">Verified record</p>
                        <h3 class="mt-0.5 font-display text-lg font-bold">Bank Verification Number Slip</h3>
                    </div>

                    <div class="slip-guilloche p-6">
                        <div class="mb-6 flex items-center gap-4">
                            <img
                                :src="result.photo ? `data:image/jpeg;base64,${result.photo}` : 'https://placehold.co/96x96?text=Photo'"
                                @error="(e) => e.target.src = 'https://placehold.co/96x96?text=Photo'"
                                alt="Photo"
                                class="h-24 w-24 rounded-lg border border-ink-200 bg-ink-50 object-cover"
                            />
                            <div class="min-w-0">
                                <p class="font-display text-xl font-bold uppercase text-ink-950">{{ fullName }}</p>
                                <p class="mt-0.5 font-mono text-lg tracking-wider text-brand-800">{{ formatBvn(result.bvn) }}</p>
                            </div>
                        </div>

                        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div>
                                <dt class="text-2xs font-semibold uppercase tracking-[0.14em] text-ink-500">Date of birth</dt>
                                <dd class="mt-0.5 font-medium">{{ fmtDate(result.dob) }}</dd>
                            </div>
                            <div>
                                <dt class="text-2xs font-semibold uppercase tracking-[0.14em] text-ink-500">Gender</dt>
                                <dd class="mt-0.5 font-medium">{{ result.gender || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-2xs font-semibold uppercase tracking-[0.14em] text-ink-500">Phone number</dt>
                                <dd class="mt-0.5 font-mono font-medium">{{ result.phone || '—' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="slip-tear bg-ink-50 px-6 py-2 text-center text-xs text-ink-400">
                        Generated {{ fmtDate(new Date()) }}
                    </div>
                </div>
            </div>

            <!-- History -->
            <section class="card overflow-hidden print:hidden">
                <div class="border-b rule px-5 py-4">
                    <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">Verification history</h2>
                </div>

                <template v-if="history.data.length">
                    <div class="scroll-slim overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="t-head">
                                <tr>
                                    <th>BVN</th>
                                    <th>Name</th>
                                    <th>Slip</th>
                                    <th>Status</th>
                                    <th>Price</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y rule">
                                <tr v-for="h in history.data" :key="h.id" class="t-row">
                                    <td class="font-mono font-semibold text-ink-950 dark:text-white">{{ h.bvn }}</td>
                                    <td class="text-ink-700 dark:text-ink-200">{{ h.name || '—' }}</td>
                                    <td class="capitalize text-ink-600 dark:text-ink-300">{{ h.slip_type }}</td>
                                    <td><StatusPill :status="h.status" /></td>
                                    <td class="font-mono text-ink-600 dark:text-ink-300">{{ formatCurrency(h.price) }}</td>
                                    <td class="whitespace-nowrap text-ink-500 dark:text-ink-400">{{ fmtDate(h.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <Pagination :paginator="history" label="searches" />
                </template>

                <EmptyState
                    v-else
                    title="No BVN searches yet"
                    description="Verify a BVN above and every search will be recorded here."
                    icon="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                />
            </section>
        </div>
    </AuthenticatedLayout>
</template>
