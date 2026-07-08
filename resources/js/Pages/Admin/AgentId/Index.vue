<script setup>
import { ref, reactive, computed, watch, nextTick } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { PDFDocument } from 'pdf-lib';

// Template assets (front is composited on a canvas, back is placed as-is).
const FRONT_TEMPLATE = '/images/id-card-template.png';
const BACK_TEMPLATE = '/images/id-card-back.jpg';

const userData = reactive({
    name: '',
    email: '',
    agentId: '',
    photo: null,
});

const showPreview = ref(false);
const isGenerating = ref(false);
const fileInput = ref(null);
const previewCanvas = ref(null);

const isFormValid = computed(() => !!(userData.name && userData.email && userData.agentId));

const handlePhotoUpload = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
        userData.photo = e.target?.result ?? null;
    };
    reader.readAsDataURL(file);
};

const loadImage = (src) => new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => resolve(img);
    img.onerror = () => reject(new Error(`Failed to load image: ${src}`));
    img.src = src;
});

// ---- Live preview (mirrors nimcweb id-card-preview) ----
const renderPreview = async () => {
    const canvas = previewCanvas.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    canvas.width = 539;
    canvas.height = 856;

    const template = await loadImage(FRONT_TEMPLATE);
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(template, 0, 0, canvas.width, canvas.height);

    const scaleX = canvas.width / 856;
    const scaleY = canvas.height / 539;

    const drawText = () => {
        ctx.fillStyle = '#000000';
        ctx.textAlign = 'left';

        ctx.font = `bold ${Math.round(50 * scaleX)}px Arial`;
        ctx.fillText(userData.name, Math.round(170 * scaleX), Math.round(322 * scaleY));

        ctx.font = `bold ${Math.round(30 * scaleX)}px Arial`;
        ctx.fillText(userData.email, Math.round(260 * scaleX), Math.round(423 * scaleY));

        ctx.font = `bold ${Math.round(36 * scaleX)}px Arial`;
        ctx.fillText(userData.agentId, Math.round(285 * scaleX), Math.round(447 * scaleY));
    };

    if (userData.photo) {
        const userImg = await loadImage(userData.photo);

        const photoX = Math.round(280 * scaleX);
        const photoY = Math.round(125 * scaleY);
        const photoWidth = Math.round(290 * scaleX);
        const photoHeight = Math.round(160 * scaleY);

        ctx.save();
        ctx.beginPath();
        ctx.roundRect(photoX, photoY, photoWidth, photoHeight, 8);
        ctx.clip();

        const imgAspect = userImg.width / userImg.height;
        const areaAspect = photoWidth / photoHeight;
        let drawWidth, drawHeight, drawX, drawY;
        if (imgAspect > areaAspect) {
            drawHeight = photoHeight;
            drawWidth = drawHeight * imgAspect;
            drawX = photoX - (drawWidth - photoWidth) / 2;
            drawY = photoY;
        } else {
            drawWidth = photoWidth;
            drawHeight = drawWidth / imgAspect;
            drawX = photoX;
            drawY = photoY - (drawHeight - photoHeight) / 2;
        }
        ctx.drawImage(userImg, drawX, drawY, drawWidth, drawHeight);
        ctx.restore();
    }

    drawText();
};

// Re-render whenever preview is visible and data changes.
watch([showPreview, () => ({ ...userData })], async () => {
    if (showPreview.value && isFormValid.value) {
        await nextTick();
        await renderPreview();
    }
}, { deep: true });

const togglePreview = () => {
    showPreview.value = !showPreview.value;
};

// ---- Build the composited front card as a PNG (mirrors nimcweb pdf-generator) ----
const buildFrontPng = async (frontImg) => {
    const frontAspect = frontImg.naturalWidth / frontImg.naturalHeight;
    const canvasWidth = 856;
    const canvasHeight = Math.round(canvasWidth / frontAspect);

    const canvas = document.createElement('canvas');
    canvas.width = canvasWidth;
    canvas.height = canvasHeight;
    const ctx = canvas.getContext('2d');

    ctx.drawImage(frontImg, 0, 0, canvasWidth, canvasHeight);

    if (userData.photo) {
        const userImg = await loadImage(userData.photo);
        const photoX = Math.round(115 * (canvasWidth / 400));
        const photoY = Math.round(135 * (canvasHeight / 600));
        const photoWidth = Math.round(170 * (canvasWidth / 400));
        const photoHeight = Math.round(180 * (canvasHeight / 600));

        ctx.save();
        ctx.beginPath();
        ctx.roundRect(photoX, photoY, photoWidth, photoHeight, 15);
        ctx.clip();
        ctx.drawImage(userImg, photoX, photoY, photoWidth, photoHeight);
        ctx.restore();
    }

    ctx.fillStyle = '#000000';
    ctx.textAlign = 'left';
    const scaleX = canvasWidth / 856;
    const scaleY = canvasHeight / 539;

    ctx.font = `bold ${Math.round(50 * scaleX)}px Arial`;
    ctx.fillText(userData.name, Math.round(170 * scaleX), Math.round(322 * scaleY));

    ctx.font = `bold ${Math.round(30 * scaleX)}px Arial`;
    ctx.fillText(userData.email, Math.round(260 * scaleX), Math.round(423 * scaleY));

    ctx.font = `bold ${Math.round(36 * scaleX)}px Arial`;
    ctx.fillText(userData.agentId, Math.round(285 * scaleX), Math.round(447 * scaleY));

    return { dataUrl: canvas.toDataURL('image/png'), aspect: frontAspect };
};

const handleGeneratePDF = async () => {
    if (!isFormValid.value) {
        alert('Please fill in all required fields');
        return;
    }

    isGenerating.value = true;
    try {
        const [frontImg, backBytes] = await Promise.all([
            loadImage(FRONT_TEMPLATE),
            fetch(BACK_TEMPLATE).then((r) => r.arrayBuffer()),
        ]);

        const { dataUrl, aspect: frontAspect } = await buildFrontPng(frontImg);
        const frontPngBytes = await fetch(dataUrl).then((r) => r.arrayBuffer());

        const pdfDoc = await PDFDocument.create();
        const frontPng = await pdfDoc.embedPng(frontPngBytes);
        const backJpg = await pdfDoc.embedJpg(backBytes);
        const backAspect = backJpg.width / backJpg.height;

        // A4 portrait; layout mirrors the source (mm), converted to PDF points.
        const MM = 72 / 25.4;
        const a4w = 210;
        const a4h = 297;
        const page = pdfDoc.addPage([a4w * MM, a4h * MM]);

        const cardW = 85.6; // standard ID card width (mm)
        const frontH = cardW / frontAspect;
        const backH = cardW / backAspect;

        const frontX = (a4w - cardW) / 2;
        const frontTop = 20; // from top edge
        const backX = (a4w - cardW) / 2;
        const backTop = frontTop + frontH + 5; // 5mm gap

        // pdf-lib is bottom-origin; y is the image's bottom edge.
        page.drawImage(frontPng, {
            x: frontX * MM,
            y: (a4h - frontTop - frontH) * MM,
            width: cardW * MM,
            height: frontH * MM,
        });
        page.drawImage(backJpg, {
            x: backX * MM,
            y: (a4h - backTop - backH) * MM,
            width: cardW * MM,
            height: backH * MM,
        });

        const pdfBytes = await pdfDoc.save();
        const blob = new Blob([new Uint8Array(pdfBytes)], { type: 'application/pdf' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${userData.name.replace(/\s+/g, '_')}_ID_Card.pdf`;
        link.click();
        URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Error generating PDF:', error);
        alert('Error generating PDF. Please try again.');
    } finally {
        isGenerating.value = false;
    }
};
</script>

<template>
    <Head title="Agent ID Card" />

    <AdminLayout>
        <div class="max-w-4xl mx-auto">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">
                    Agent ID Card Generator
                </h1>
                <p class="text-gray-600 dark:text-gray-400">
                    Generate a professional agent ID card with a front and back, ready to print.
                </p>
            </div>

            <div class="grid md:grid-cols-2 gap-8">
                <!-- Form Section -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="flex items-center gap-2 font-semibold text-gray-900 dark:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            Personal Information
                        </h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Full Name *</label>
                            <input
                                id="name"
                                v-model="userData.name"
                                type="text"
                                placeholder="Enter full name"
                                class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address *</label>
                            <input
                                id="email"
                                v-model="userData.email"
                                type="email"
                                placeholder="Enter email address"
                                class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <div>
                            <label for="agentId" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Agent ID *</label>
                            <input
                                id="agentId"
                                v-model="userData.agentId"
                                type="text"
                                placeholder="Enter agent ID"
                                class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Profile Photo</label>
                            <div class="mt-1">
                                <input
                                    ref="fileInput"
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="handlePhotoUpload"
                                />
                                <button
                                    type="button"
                                    class="w-full flex items-center justify-center gap-2 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                    @click="fileInput?.click()"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                    {{ userData.photo ? 'Change Photo' : 'Upload Photo' }}
                                </button>
                            </div>
                            <div v-if="userData.photo" class="mt-2">
                                <img :src="userData.photo" alt="Profile preview" class="w-20 h-20 object-cover rounded-lg border border-gray-200 dark:border-gray-600" />
                            </div>
                        </div>

                        <div class="flex gap-2 pt-4">
                            <button
                                type="button"
                                :disabled="!isFormValid"
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                @click="togglePreview"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                {{ showPreview ? 'Hide Preview' : 'Preview Card' }}
                            </button>
                            <button
                                type="button"
                                :disabled="!isFormValid || isGenerating"
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                @click="handleGeneratePDF"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                {{ isGenerating ? 'Generating...' : 'Download PDF' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Preview Section -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="flex items-center gap-2 font-semibold text-gray-900 dark:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            ID Card Preview
                        </h2>
                    </div>
                    <div class="p-6">
                        <div v-show="showPreview && isFormValid" class="flex justify-center">
                            <canvas
                                ref="previewCanvas"
                                class="border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-w-full h-auto"
                                style="max-height: 400px"
                            ></canvas>
                        </div>
                        <div v-if="!(showPreview && isFormValid)" class="flex items-center justify-center h-64 bg-gray-50 dark:bg-gray-700/40 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600">
                            <div class="text-center">
                                <svg class="w-12 h-12 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <p class="text-gray-500 dark:text-gray-400">
                                    {{ !isFormValid ? 'Fill in the required fields to see preview' : 'Click "Preview Card" to see the ID card' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
