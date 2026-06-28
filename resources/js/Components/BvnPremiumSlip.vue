<template>
    <div>
        <!-- Hidden QR canvas rendered by qrcode.vue (logo composited in center) -->
        <div ref="qrWrapperRef" class="absolute opacity-0 pointer-events-none" style="left:-9999px;top:-9999px;">
            <QrcodeCanvas
                :value="qrValue"
                :size="qrRenderSize"
                level="M"
                :image-settings="qrImageSettings"
            />
        </div>

        <!-- Download Button -->
        <button
            @click="handleDownload"
            :disabled="loading"
            class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-amber-500 hover:bg-amber-600 text-white rounded-full font-medium disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
            <svg v-if="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            {{ loading ? 'Processing...' : 'Download BVN Slip' }}
        </button>
    </div>
</template>

<script setup>
import { ref, nextTick } from 'vue';
import { PDFDocument, rgb, StandardFonts, degrees } from 'pdf-lib';
import { QrcodeCanvas } from 'qrcode.vue';

// Keys mirror the React BvnSlip ("Plastic ID") component / BvnPremiumSlipGenerator.
const props = defineProps({
    surname: { type: String, default: '' },
    othernames: { type: String, default: '' },
    dob: { type: String, default: '' },
    gender: { type: String, default: '' },
    nin: { type: String, default: '' }, // the BVN-card big-number field (holds the BVN)
    photo: { type: String, default: '' },
    issuedDate: { type: String, default: '' },
    qrValue: { type: String, default: '' },
    watermark: { type: String, default: '' },
});

const qrWrapperRef = ref(null);
const loading = ref(false);
const qrRenderSize = 240;

// imageSettings: embed qr.jpeg logo in center of the QR code.
const qrImageSettings = {
    src: '/images/qr.jpeg',
    width: qrRenderSize * 0.3,
    height: qrRenderSize * 0.3,
    excavate: true,
};

const fetchImageAsBytes = async (url) => {
    const response = await fetch(url);
    return response.arrayBuffer();
};

// Get QR code data URL from the qrcode.vue canvas component.
const getQRDataURL = () => {
    return new Promise((resolve) => {
        nextTick(() => {
            setTimeout(() => {
                try {
                    const canvas = qrWrapperRef.value?.querySelector('canvas');
                    if (canvas && canvas.tagName === 'CANVAS') {
                        resolve(canvas.toDataURL('image/png'));
                    } else {
                        resolve(null);
                    }
                } catch (e) {
                    resolve(null);
                }
            }, 300);
        });
    });
};

const generatePDF = async () => {
    const qrDataURL = props.qrValue ? await getQRDataURL() : null;

    const pdfDoc = await PDFDocument.create();
    const page = pdfDoc.addPage([595.28, 841.89]);

    const font = await pdfDoc.embedFont(StandardFonts.Helvetica);

    const lightSlateColor = rgb(120 / 255, 135 / 255, 153 / 255);

    // Card dimensions / positioning box.
    const idCardWidth = 243;
    const idCardHeight = 153;
    const centerX = (page.getWidth() - idCardWidth) / 2;
    const centerY = (page.getHeight() - idCardHeight) / 2;

    // ===== FRONT SIDE - BVN card background =====
    try {
        const bgBytes = await fetch('/images/bvnfront.jpg').then((r) => r.arrayBuffer());
        const bg = await pdfDoc.embedJpg(bgBytes);
        const scaled = bg.scaleToFit(idCardWidth, idCardHeight);
        page.drawImage(bg, { x: centerX, y: centerY, width: scaled.width, height: scaled.height });
    } catch (e) {
        page.drawRectangle({
            x: centerX, y: centerY, width: idCardWidth, height: idCardHeight,
            color: rgb(0.95, 0.95, 0.95), borderColor: rgb(0.8, 0.8, 0.8), borderWidth: 1,
        });
    }

    // ===== BACK SIDE - BVN card background (shifted down) =====
    const backCenterY = centerY - 165;
    try {
        const bgBytes2 = await fetch('/images/bvnback.jpg').then((r) => r.arrayBuffer());
        const bg2 = await pdfDoc.embedJpg(bgBytes2);
        const scaled2 = bg2.scaleToFit(idCardWidth, idCardHeight);
        page.drawImage(bg2, { x: centerX, y: backCenterY, width: scaled2.width, height: scaled2.height });
    } catch (e) {
        page.drawRectangle({
            x: centerX, y: backCenterY, width: idCardWidth, height: idCardHeight,
            color: rgb(0.95, 0.95, 0.95), borderColor: rgb(0.8, 0.8, 0.8), borderWidth: 1,
        });
    }

    // ===== USER PHOTO =====
    if (props.photo) {
        try {
            const photoBytes = await fetchImageAsBytes(props.photo);
            const isJpg = props.photo.includes('image/jpeg') || props.photo.includes('image/jpg')
                || props.photo.endsWith('.jpg') || props.photo.endsWith('.jpeg');
            const photoImage = isJpg ? await pdfDoc.embedJpg(photoBytes) : await pdfDoc.embedPng(photoBytes);

            const photoWidth = 63;
            const photoHeight = 77;
            const photoX = centerX - photoWidth / 2 + 36;
            const photoY = centerY + idCardHeight / 2 - photoHeight + 38;
            page.drawImage(photoImage, { x: photoX, y: photoY, width: photoWidth, height: photoHeight });
        } catch (e) {
            // ignore photo embed failure
        }
    }

    // ===== INSTRUCTION PARAGRAPH (top of page) =====
    const instruction = [
        'Please find below your new High Resolution NIN Slip. You may cut it out of the paper, fold',
        'and laminate as desired. Please DO NOT allow others to make copies of your NIN Slip.',
    ];
    let iy = page.getHeight() - 20;
    instruction.forEach((line) => {
        page.drawText(line, { x: 90, y: iy, size: 12, font, color: rgb(0, 0, 0) });
        iy -= 15;
    });

    // ===== CARD TEXT FIELDS (8pt) =====
    const up = (v) => String(v || '').toUpperCase();
    const textSize = 8;

    const surX = centerX + idCardWidth / 2 - 45;
    const surY = centerY + idCardHeight / 2 + 16;
    page.drawText(up(props.surname), { x: surX, y: surY, size: textSize, font });

    const othY = centerY + idCardHeight / 2 - 9;
    page.drawText(up(props.othernames), { x: surX, y: othY, size: textSize, font });

    const dobY = centerY + idCardHeight / 2 + 7 - 40;
    page.drawText(up(props.dob), { x: surX, y: dobY, size: textSize, font });

    const genX = centerX + idCardWidth / 2 + 19;
    const genY = centerY + idCardHeight / 2 - 33;
    page.drawText(up(props.gender), { x: genX, y: genY, size: textSize, font });

    const issueX = centerX + idCardWidth / 2 + 62;
    page.drawText(up(props.issuedDate), { x: issueX, y: genY, size: textSize, font });

    // ===== NIN/BVN big number (20pt) =====
    const ninX = centerX + idCardWidth / 2 - 50;
    const ninY = centerY + idCardHeight / 2 - 65;
    page.drawText(props.nin || '-', { x: ninX, y: ninY, size: 20, font });

    // ===== WATERMARK (8pt, light-slate, rotated 45) =====
    if (props.watermark) {
        page.drawText(props.watermark, {
            x: ninX - 60, y: ninY + 3, size: 8, font, color: lightSlateColor, rotate: degrees(45),
        });
    }

    // ===== QR CODE (rendered by qrcode.vue with logo) =====
    if (qrDataURL) {
        try {
            const base64 = qrDataURL.split(',')[1];
            const binaryStr = atob(base64);
            const qrBytes = new Uint8Array(binaryStr.length);
            for (let i = 0; i < binaryStr.length; i++) qrBytes[i] = binaryStr.charCodeAt(i);

            const qrImage = await pdfDoc.embedPng(qrBytes);
            const qrWidth = 60;
            const qrHeight = 60;
            const qrX = centerX + idCardWidth / 2 - qrWidth + 110;
            const qrY = centerY - idCardHeight / 2 + 160;
            page.drawImage(qrImage, { x: qrX, y: qrY, width: qrWidth, height: qrHeight });
        } catch (e) {
            // ignore QR embed failure
        }
    }

    const pdfBytes = await pdfDoc.save();
    return new Blob([new Uint8Array(pdfBytes)], { type: 'application/pdf' });
};

const handleDownload = async () => {
    loading.value = true;
    try {
        const pdfBlob = await generatePDF();
        if (pdfBlob) {
            const pdfURL = URL.createObjectURL(pdfBlob);
            const link = document.createElement('a');
            link.href = pdfURL;
            link.download = `${props.surname || 'bvn'}_${props.nin}_bvn_slip.pdf`;
            link.click();
            URL.revokeObjectURL(pdfURL);
        }
    } catch (error) {
        console.error('Error generating BVN slip PDF:', error);
        alert('Error generating PDF. Please try again.');
    }
    loading.value = false;
};

defineExpose({ generatePDF, handleDownload });
</script>
