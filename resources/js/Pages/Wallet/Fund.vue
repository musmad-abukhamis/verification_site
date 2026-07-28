<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Modal from '@/Components/Modal.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    wallet: Object,
    reserved_accounts: { type: Array, default: () => [] },
});

const page = usePage();
const showModal = ref(false);
const copied = ref(null);

// BVN is the only input PayVessel needs: the account name comes from the
// user's registered name, and both banks are issued in one request.
const form = useForm({
    bvn: '',
});

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));

const openModal = () => {
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
};

const submit = () => {
    form.transform((d) => ({ ...d, bvn: d.bvn.replace(/\D/g, '') }))
        .post(route('wallet.virtual-account.create'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
};

const copy = async (value) => {
    try {
        await navigator.clipboard.writeText(value);
        copied.value = value;
        setTimeout(() => (copied.value = null), 1500);
    } catch (e) {
        // clipboard not available; ignore
    }
};
</script>

<template>
    <Head title="Fund Wallet" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl space-y-6">
            <PageHeader
                eyebrow="Wallet"
                title="Fund wallet"
                description="Transfer to any of your accounts below. Credits land automatically once the transfer clears."
            />

            <div v-if="page.props.flash?.success" class="rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-900 dark:bg-success-950 dark:text-success-300">
                {{ page.props.flash.success }}
            </div>

            <!-- Balance -->
            <section class="slip slip-guilloche px-5 py-5">
                <p class="eyebrow">Current balance</p>
                <p class="mt-2 font-mono text-3xl font-semibold tracking-tight text-ink-950 dark:text-white">
                    {{ formatCurrency(wallet.total_balance) }}
                </p>
            </section>

            <!-- Accounts -->
            <section class="card">
                <div class="flex flex-col gap-3 border-b rule px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">
                            Your virtual accounts
                        </h2>
                        <p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">
                            Money sent to these numbers credits your wallet instantly.
                        </p>
                    </div>
                    <PrimaryButton v-if="reserved_accounts.length" @click="openModal">Add account</PrimaryButton>
                </div>

                <div v-if="reserved_accounts.length" class="grid gap-4 p-5 sm:grid-cols-2">
                    <div v-for="acct in reserved_accounts" :key="acct.account_number" class="rounded-lg border rule p-4">
                        <p class="eyebrow">{{ acct.bank }}</p>
                        <div class="mt-2 flex items-center justify-between gap-3">
                            <span class="font-mono text-xl font-semibold tracking-wide text-ink-950 dark:text-white">
                                {{ acct.account_number }}
                            </span>
                            <button type="button" class="btn btn-secondary btn-sm" @click="copy(acct.account_number)">
                                {{ copied === acct.account_number ? 'Copied' : 'Copy' }}
                            </button>
                        </div>
                        <p class="mt-1.5 text-sm text-ink-500 dark:text-ink-400">{{ acct.account_name }}</p>
                    </div>
                </div>

                <EmptyState
                    v-else
                    title="You don't have a virtual account yet"
                    description="It takes one step: confirm your BVN and we'll issue account numbers in your registered name."
                    icon="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                >
                    <PrimaryButton @click="openModal">Create virtual account</PrimaryButton>
                </EmptyState>
            </section>

            <p class="text-sm text-ink-500 dark:text-ink-400">
                Funds reflect automatically once your transfer is confirmed. Virtual accounts are created with BVN KYC verification.
            </p>
        </div>

        <!-- Create account dialog -->
        <Modal :show="showModal" max-width="md" @close="closeModal">
            <div class="p-6">
                <h2 class="font-display text-lg font-semibold text-ink-950 dark:text-white">Create virtual account</h2>
                <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
                    Your accounts will be created in your registered name using your BVN.
                </p>

                <form @submit.prevent="submit" class="mt-6 space-y-4">
                    <div>
                        <InputLabel for="bvn" value="BVN (Bank Verification Number)" />
                        <TextInput
                            id="bvn"
                            v-model="form.bvn"
                            type="text"
                            inputmode="numeric"
                            maxlength="11"
                            class="mt-1 block w-full font-mono"
                            placeholder="11-digit BVN"
                            autofocus
                        />
                        <InputError class="mt-2" :message="form.errors.bvn" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <SecondaryButton type="button" @click="closeModal" :disabled="form.processing">Cancel</SecondaryButton>
                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            {{ form.processing ? 'Creating…' : 'Create account' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
