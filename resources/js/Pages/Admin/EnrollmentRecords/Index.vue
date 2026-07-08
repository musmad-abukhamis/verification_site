<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const fileInput = ref(null);
const fileName = ref('');

const form = useForm({
    file: null,
});

const onFile = (e) => {
    const file = e.target.files?.[0] || null;
    form.file = file;
    fileName.value = file?.name || '';
};

const submit = () => {
    if (!form.file) return;
    form.post(route('admin.enrollment-records.upload'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('file');
            fileName.value = '';
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};
</script>

<template>
    <Head title="Enrollment Records Upload" />
    <AdminLayout>
        <div class="max-w-xl mx-auto">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">Upload Enrollment Records</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Upload a spreadsheet of BVN enrolment rows. Existing rows (matched by Ticket ID) are updated; new ones are added.
                </p>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg text-sm">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="form.errors.file" class="mb-4 p-3 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded-lg text-sm">
                    {{ form.errors.file }}
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <label class="flex flex-col items-center justify-center w-full h-40 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6 px-4 text-center">
                            <svg class="w-10 h-10 mb-3 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                            </svg>
                            <p v-if="fileName" class="text-sm font-medium text-gray-900 dark:text-gray-100 break-all">{{ fileName }}</p>
                            <template v-else>
                                <p class="mb-1 text-sm text-gray-500 dark:text-gray-400"><span class="font-semibold">Click to upload</span> a spreadsheet</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">XLSX or CSV (max 20MB)</p>
                            </template>
                        </div>
                        <input ref="fileInput" type="file" class="hidden" accept=".xlsx,.csv" @change="onFile" />
                    </label>

                    <div v-if="form.progress" class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                        <div class="bg-indigo-600 h-2 rounded-full transition-all" :style="{ width: form.progress.percentage + '%' }"></div>
                    </div>

                    <button type="submit" :disabled="form.processing || !form.file"
                        class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        {{ form.processing ? 'Uploading...' : 'Upload File' }}
                    </button>
                </form>

                <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-700/40 rounded-lg text-xs text-gray-500 dark:text-gray-400">
                    <p class="font-semibold text-gray-700 dark:text-gray-300 mb-1">Expected column order (no header row required):</p>
                    <p>Ticket ID, BVN, Org Name, Org ID, Enrollee Name, Enroller ID, Enroller ID 2, MSC, MSC1, MSC2, Ticket ID 2, Status, Comment, Amount, Date Enrolled, Timestamp 1, Timestamp 2, Timestamp 3, Time Zone.</p>
                    <p class="mt-2">Rows without a Ticket ID in the first column are skipped. Legacy <code>.xls</code> files must be re-saved as <code>.xlsx</code> or CSV.</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
