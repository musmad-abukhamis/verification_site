<template>
    <div>
        <!-- Hidden QR Code canvas -->
        <div ref="qrCanvasRef" class="hidden">
            <canvas ref="qrCanvas"></canvas>
        </div>

        <!-- Download Button -->
        <button
            @click="handleDownload"
            :disabled="loading"
            class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-lime-500 hover:bg-lime-600 text-white rounded-full font-medium disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
            <svg v-if="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            {{ loading ? 'Processing...' : 'Download NIN Slip' }}
        </button>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { PDFDocument, rgb, StandardFonts, degrees } from 'pdf-lib';
import qrcode from 'qrcode-generator';

const props = defineProps({
    surname: { type: String, required: true },
    othernames: { type: String, required: true },
    dob: { type: String, required: true },
    gender: { type: String, default: '' },
    nin: { type: String, required: true },
    photo: { type: String, required: true },
    issuedDate: { type: String, default: '' },
    qrValue: { type: String, required: true },
    trackingId: { type: String, default: '' },
});

const qrCanvasRef = ref(null);
const qrCanvas = ref(null);
const loading = ref(false);

// Helper function to convert hex to RGB
const hexToRgbNormalized = (hex) => {
    const bigint = parseInt(hex.replace('#', ''), 16);
    return {
        r: ((bigint >> 16) & 255) / 255,
        g: ((bigint >> 8) & 255) / 255,
        b: (bigint & 255) / 255,
    };
};

// Generate QR Code as data URL
const generateQRCode = (value, size = 200) => {
    const qr = qrcode(0, 'M');
    qr.addData(value);
    qr.make();
    
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const cellSize = size / qr.getModuleCount();
    
    canvas.width = size;
    canvas.height = size;
    
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, size, size);
    
    ctx.fillStyle = '#000000';
    for (let row = 0; row < qr.getModuleCount(); row++) {
        for (let col = 0; col < qr.getModuleCount(); col++) {
            if (qr.isDark(row, col)) {
                ctx.fillRect(col * cellSize, row * cellSize, cellSize, cellSize);
            }
        }
    }
    
    return canvas.toDataURL('image/png');
};

// Fetch image as array buffer
const fetchImageAsBytes = async (url) => {
    const response = await fetch(url);
    return response.arrayBuffer();
};

// Generate PDF
const generatePDF = async () => {
    const qrDataURL = generateQRCode(props.qrValue || props.nin, 200);
    const qrImageBytes = await fetch(qrDataURL).then(res => res.arrayBuffer());

    const pdfDoc = await PDFDocument.create();
    const page = pdfDoc.addPage([595.28, 841.89]);

    const font = await pdfDoc.embedFont(StandardFonts.Helvetica);
    const fontBold = await pdfDoc.embedFont(StandardFonts.HelveticaBold);

    // Header text with word wrapping
    const headerText = `Please find below your new High Resolution NIN Slip. You may cut it out of the paper, fold and laminate as desired. Please DO NOT allow others to make copies of your NIN Slip.`;
    const fontSize = 10;
    const lineHeight = fontSize + 4;
    const margin = 20;
    const maxWidth = page.getWidth() - (margin * 2) - 140;
    const color = rgb(0, 0, 0);

    // Word wrap function
    const wrapText = (text, maxWidth, font, fontSize) => {
        const words = text.split(' ');
        const lines = [];
        let currentLine = '';
        for (const word of words) {
            const testLine = currentLine ? `${currentLine} ${word}` : word;
            const testWidth = font.widthOfTextAtSize(testLine, fontSize);
            if (testWidth <= maxWidth) {
                currentLine = testLine;
            } else {
                if (currentLine) lines.push(currentLine);
                currentLine = word;
            }
        }
        if (currentLine) lines.push(currentLine);
        return lines;
    };

    const headerLines = wrapText(headerText, maxWidth, font, fontSize);
    let y = page.getHeight() - margin;
    headerLines.forEach((line) => {
        page.drawText(line, { x: margin + 70, y, size: fontSize, font, color });
        y -= lineHeight;
    });

    // Colors
    const lightSlateColor = rgb(120 / 255, 135 / 255, 153 / 255);
    const limeColor = rgb(0 / 255, 192 / 255, 0 / 255);

    // Card dimensions
    const idCardWidth = 243;
    const idCardHeight = 153;
    const centerX = (page.getWidth() - idCardWidth) / 2;
    const centerY = (page.getHeight() - idCardHeight) / 2;

    // ===== FRONT SIDE =====
    try {
        const bgImageBytes = await fetch('/images/standardslipfront.jpg').then(res => res.arrayBuffer());
        const bgImage = await pdfDoc.embedJpg(bgImageBytes);
        const bgImageScaled = bgImage.scaleToFit(idCardWidth, idCardHeight);
        
        page.drawImage(bgImage, {
            x: centerX,
            y: centerY,
            width: bgImageScaled.width,
            height: bgImageScaled.height,
        });
    } catch (e) {
        console.warn('Could not load front background:', e);
        page.drawRectangle({
            x: centerX, y: centerY, width: idCardWidth, height: idCardHeight,
            color: rgb(0.95, 0.95, 0.95), borderColor: rgb(0.8, 0.8, 0.8), borderWidth: 1,
        });
    }

    // ===== BACK SIDE =====
    const backCenterY = centerY - idCardHeight - 20;
    try {
        const bgImageBytes2 = await fetch('/images/ninSlipback.jpg').then(res => res.arrayBuffer());
        const bgImage2 = await pdfDoc.embedJpg(bgImageBytes2);
        const bgImageScaled2 = bgImage2.scaleToFit(idCardWidth, idCardHeight);
        
        page.drawImage(bgImage2, {
            x: centerX - 1,
            y: backCenterY,
            width: bgImageScaled2.width +10,
            height: bgImageScaled2.height,
        });
    } catch (e) {
        console.warn('Could not load back background:', e);
        page.drawRectangle({
            x: centerX, y: backCenterY, width: idCardWidth, height: idCardHeight,
            color: rgb(0.95, 0.95, 0.95), borderColor: rgb(0.8, 0.8, 0.8), borderWidth: 1,
        });
    }

    // ===== USER PHOTO =====
    try {
        const photoBytes = await fetchImageAsBytes(props.photo);
        let photoImage;
        
        if (props.photo.includes('data:image/jpeg') || props.photo.includes('data:image/jpg') || props.photo.endsWith('.jpg') || props.photo.endsWith('.jpeg')) {
            photoImage = await pdfDoc.embedJpg(photoBytes);
        } else {
            photoImage = await pdfDoc.embedPng(photoBytes);
        }
        
        const photoWidth = 55;
        const photoHeight = 70;
        const photoX = centerX - photoWidth / 2 + 36;
        const photoY = centerY + idCardHeight / 2 - photoHeight + 43;

        page.drawImage(photoImage, { x: photoX, y: photoY, width: photoWidth, height: photoHeight });
    } catch (e) {
        console.warn('Could not embed photo:', e);
        const photoWidth = 55;
        const photoHeight = 70;
        const photoX = centerX - photoWidth / 2 + 36;
        const photoY = centerY + idCardHeight / 2 - photoHeight + 43;
        page.drawRectangle({ x: photoX, y: photoY, width: photoWidth, height: photoHeight, color: rgb(0.9, 0.9, 0.9) });
    }

    // ===== TEXT FIELDS =====
    // Surname position
    const SurX = centerX + idCardWidth / 2 - 53;
    const SurY = centerY + idCardHeight / 2 + 20;
    page.drawText(props.surname || '-', { x: SurX, y: SurY, size: 7, font });

    // Other names position
    const othX = centerX + idCardWidth / 2 - 53;
    const othY = centerY + idCardHeight / 2;
    page.drawText(props.othernames || '-', { x: othX, y: othY, size: 7, font });

    // DOB position
    const dobX = centerX + idCardWidth / 2 - 53;
    const dobY = centerY + idCardHeight / 2 - 20;
    page.drawText(props.dob || '-', { x: dobX, y: dobY, size: 7, font });

    // Gender position
    const genX = centerX + idCardWidth / 2 + 14;
    const genY = centerY + idCardHeight / 2 - 20;
    page.drawText(props.gender || '-', { x: genX, y: genY, size: 7, font });

    // NIN position (large)
    const ninX = centerX + idCardWidth / 2 - 68;
    const ninY = centerY + idCardHeight / 2 - 60;
    const formattedNin = props.nin.replace(/(\d{4})(\d{4})(\d{3})/, '$1 $2 $3');
    page.drawText(formattedNin, { x: ninX, y: ninY, size: 20, font: fontBold });

    // ===== WATERMARKS =====
    page.drawText(props.trackingId || 'VERIFIED', {
        x: ninX - 30,
        y: ninY + 2,
        size: 8,
        font,
        color: lightSlateColor,
        rotate: degrees(45),
    });
    page.drawText(props.trackingId || 'VERIFIED', {
        x: ninX + 180,
        y: ninY - 8,
        size: 8,
        font,
        color: lightSlateColor,
        rotate: degrees(135),
    });

    // ===== QR CODE (Front) =====
    try {
        const qrImage = await pdfDoc.embedPng(qrImageBytes);
        const qrWidth = 60;
        const qrHeight = 60;
        const qrX = centerX + idCardWidth / 2 - qrWidth + 115;
        const qrY = centerY - idCardHeight / 2 + 120;

        page.drawImage(qrImage, { x: qrX, y: qrY, width: qrWidth, height: qrHeight });
    } catch (e) {
        console.warn('Could not embed QR code:', e);
    }

   

    // Save and return PDF as Blob
    const pdfBytes = await pdfDoc.save();
    return new Blob([new Uint8Array(pdfBytes)], { type: 'application/pdf' });
};

// Handle download
const handleDownload = async () => {
    loading.value = true;
    try {
        const pdfBlob = await generatePDF();
        if (pdfBlob) {
            const pdfURL = URL.createObjectURL(pdfBlob);
            const link = document.createElement('a');
            link.href = pdfURL;
            link.download = `${props.surname}_${props.nin}_slip.pdf`;
            link.click();
            URL.revokeObjectURL(pdfURL);
        }
    } catch (error) {
        console.error('Error generating PDF:', error);
        alert('Error generating PDF. Please try again.');
    }
    loading.value = false;
};

defineExpose({ generatePDF, handleDownload });
</script>
