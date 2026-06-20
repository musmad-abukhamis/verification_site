<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    wallet: Object,
    reserved_accounts: { type: Array, default: () => [] },
});

const page = usePage();
const showModal = ref(false);
const copied = ref(null);

const authUser = computed(() => page.props.auth?.user ?? {});

const form = useForm({
    firstName: '',
    lastName: '',
    bvn: '',
    bank: 'PALMPAY',
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
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Fund Wallet</h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Balance -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                        <h3 class="text-lg font-medium mb-2">Current Balance</h3>
                        <p class="text-3xl font-bold">{{ formatCurrency(wallet.total_balance) }}</p>
                    </div>
                </div>

                <!-- Flash -->
                <div v-if="page.props.flash?.success" class="rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-700">
                    {{ page.props.flash.success }}
                </div>

                <!-- Reserved accounts -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Your Virtual Accounts</h3>
                                <p class="text-sm text-gray-500">Transfer to any account below to fund your wallet instantly.</p>
                            </div>
                            <PrimaryButton @click="openModal">+ Add Account</PrimaryButton>
                        </div>

                        <div v-if="reserved_accounts.length === 0" class="text-center py-10">
                            <p class="text-gray-500 mb-4">You don't have a virtual account yet.</p>
                            <PrimaryButton @click="openModal">Create Virtual Account</PrimaryButton>
                        </div>

                        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div
                                v-for="acct in reserved_accounts"
                                :key="acct.account_number"
                                class="border border-gray-200 rounded-lg p-4 flex flex-col gap-2"
                            >
                                <div class="text-xs font-medium text-indigo-600 uppercase">{{ acct.bank }}</div>
                                <div class="flex items-center justify-between">
                                    <span class="text-2xl font-mono font-bold tracking-wide">{{ acct.account_number }}</span>
                                    <button
                                        type="button"
                                        class="text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 text-gray-700"
                                        @click="copy(acct.account_number)"
                                    >
                                        {{ copied === acct.account_number ? 'Copied' : 'Copy' }}
                                    </button>
                                </div>
                                <div class="text-sm text-gray-600">{{ acct.account_name }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-600">
                    Funds reflect automatically once your transfer is confirmed. Virtual accounts are created with BVN KYC verification.
                </div>
            </div>
        </div>

        <!-- Create account dialog -->
        <Modal :show="showModal" @close="closeModal">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900">Create Virtual Account</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Fill in your details to create a virtual account with KYC verification.
                </p>

                <form @submit.prevent="submit" class="mt-6 space-y-4">
                    <div>
                        <InputLabel for="firstName" value="First Name" />
                        <TextInput id="firstName" v-model="form.firstName" type="text" class="mt-1 block w-full" placeholder="Enter your first name" />
                        <InputError class="mt-2" :message="form.errors.firstName" />
                    </div>

                    <div>
                        <InputLabel for="lastName" value="Last Name" />
                        <TextInput id="lastName" v-model="form.lastName" type="text" class="mt-1 block w-full" placeholder="Enter your last name" />
                        <InputError class="mt-2" :message="form.errors.lastName" />
                    </div>

                    <div>
                        <InputLabel for="bvn" value="BVN (Bank Verification Number)" />
                        <TextInput id="bvn" v-model="form.bvn" type="text" inputmode="numeric" maxlength="11" class="mt-1 block w-full" placeholder="Enter your 11-digit BVN" />
                        <InputError class="mt-2" :message="form.errors.bvn" />
                    </div>

                    <p class="text-xs text-gray-500">
                        Your account will be created and KYC automatically processed using your BVN.
                    </p>

                    <div class="flex justify-end gap-2 pt-2">
                        <SecondaryButton type="button" @click="closeModal" :disabled="form.processing">Cancel</SecondaryButton>
                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            {{ form.processing ? 'Creating...' : 'Create Account' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
