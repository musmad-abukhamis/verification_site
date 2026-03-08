<template>
    <div>
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
            {{ loading ? 'Processing...' : 'Download NIN Slip v2' }}
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
    qrValue: { type: String, required: true },
});

const loading = ref(false);

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

// Generate a QR code image with rounded background
const generateQRWithRoundedBg = (value, size = 200, radius = 20) => {
    const qr = qrcode(0, 'M');
    qr.addData(value);
    qr.make();
    const padding = 10;
    const totalSize = size + padding * 2;
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    canvas.width = totalSize;
    canvas.height = totalSize;
    // Draw rounded background
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.moveTo(radius, 0);
    ctx.lineTo(totalSize - radius, 0);
    ctx.quadraticCurveTo(totalSize, 0, totalSize, radius);
    ctx.lineTo(totalSize, totalSize - radius);
    ctx.quadraticCurveTo(totalSize, totalSize, totalSize - radius, totalSize);
    ctx.lineTo(radius, totalSize);
    ctx.quadraticCurveTo(0, totalSize, 0, totalSize - radius);
    ctx.lineTo(0, radius);
    ctx.quadraticCurveTo(0, 0, radius, 0);
    ctx.closePath();
    ctx.fill();
    // Draw shadow border
    ctx.strokeStyle = '#cccccc';
    ctx.lineWidth = 1;
    ctx.stroke();
    // Draw QR cells
    const cellSize = size / qr.getModuleCount();
    ctx.fillStyle = '#000000';
    for (let row = 0; row < qr.getModuleCount(); row++) {
        for (let col = 0; col < qr.getModuleCount(); col++) {
            if (qr.isDark(row, col)) {
                ctx.fillRect(padding + col * cellSize, padding + row * cellSize, cellSize, cellSize);
            }
        }
    }
    return canvas.toDataURL('image/png');
};

// Draw a rounded rectangle using clipping path
const drawRoundedRect = (page, x, y, w, h, r, fillColor, borderColor, borderWidth) => {
    // Simulate rounded corners by drawing slightly inset rectangles and corner circles
    // Main fill
    page.drawRectangle({ x: x + r, y, width: w - r * 2, height: h, color: fillColor });
    page.drawRectangle({ x, y: y + r, width: w, height: h - r * 2, color: fillColor });
    // Corners
    page.drawEllipse({ x: x + r, y: y + r, xScale: r, yScale: r, color: fillColor });
    page.drawEllipse({ x: x + w - r, y: y + r, xScale: r, yScale: r, color: fillColor });
    page.drawEllipse({ x: x + r, y: y + h - r, xScale: r, yScale: r, color: fillColor });
    page.drawEllipse({ x: x + w - r, y: y + h - r, xScale: r, yScale: r, color: fillColor });
    // Border lines
    if (borderColor && borderWidth) {
        page.drawLine({ start: { x: x + r, y }, end: { x: x + w - r, y }, thickness: borderWidth, color: borderColor });
        page.drawLine({ start: { x: x + r, y: y + h }, end: { x: x + w - r, y: y + h }, thickness: borderWidth, color: borderColor });
        page.drawLine({ start: { x, y: y + r }, end: { x, y: y + h - r }, thickness: borderWidth, color: borderColor });
        page.drawLine({ start: { x: x + w, y: y + r }, end: { x: x + w, y: y + h - r }, thickness: borderWidth, color: borderColor });
    }
};

// Draw PREVIEW watermark - tiled grid across a region
const drawWatermarkGrid = (page, text, x, y, width, height, font) => {
    const fontSize = 24;
    const watermarkColor = rgb(0.75, 0.75, 0.75);
    const cols = 3;
    const rows = 3;
    const cellW = width / cols;
    const cellH = height / rows;

    for (let row = 0; row < rows; row++) {
        for (let col = 0; col < cols; col++) {
            const cx = x + col * cellW + cellW / 2;
            const cy = y + row * cellH + cellH / 2;
            const textWidth = font.widthOfTextAtSize(text, fontSize);
            page.drawText(text, {
                x: cx - textWidth / 2,
                y: cy,
                size: fontSize,
                font,
                color: watermarkColor,
                rotate: degrees(30),
                opacity: 0.25,
            });
        }
    }
};

// Fetch image as array buffer
const fetchImageAsBytes = async (url) => {
    const response = await fetch(url);
    return response.arrayBuffer();
};

// Generate PDF
const generatePDF = async () => {
    // QR with rounded background
    const qrDataURL = generateQRWithRoundedBg(props.qrValue || props.nin, 170, 14);
    const qrImageBytes = await fetch(qrDataURL).then(res => res.arrayBuffer());

    const pdfDoc = await PDFDocument.create();
    const page = pdfDoc.addPage([595.28, 841.89]);

    const font = await pdfDoc.embedFont(StandardFonts.Helvetica);
    const fontBold = await pdfDoc.embedFont(StandardFonts.HelveticaBold);

    const blackColor = rgb(0, 0, 0);
    const darkGrayColor = rgb(0.2, 0.2, 0.2);
    const grayColor = rgb(0.45, 0.45, 0.45);
    const lightGrayColor = rgb(0.8, 0.8, 0.8);
    const cardBgColor = rgb(1, 1, 1);
    const cardBorderColor = rgb(0.75, 0.75, 0.75);

    const cardWidth = 360;
    const cardHeight = 220;
    const r = 10; // border radius
    const cardMarginX = (page.getWidth() - cardWidth) / 2;

    // ===================================================
    // ===== FRONT CARD ==================================
    // ===================================================
    const frontCardY = page.getHeight() - cardHeight - 55;

    // Rounded card background
    drawRoundedRect(page, cardMarginX, frontCardY, cardWidth, cardHeight, r, cardBgColor, cardBorderColor, 1.2);

    // ===== PHOTO (Left side) =====
    const photoWidth = 72;
    const photoHeight = 90;
    const photoX = cardMarginX + 14;
    const photoY = frontCardY + cardHeight - photoHeight - 35;

    // Photo border
    drawRoundedRect(page, photoX, photoY, photoWidth, photoHeight, 3, rgb(0.93, 0.93, 0.93), rgb(0.6, 0.6, 0.6), 0.8);

    try {
        const photoBytes = await fetchImageAsBytes(props.photo);
        let photoImage;
        if (props.photo.includes('data:image/jpeg') || props.photo.includes('data:image/jpg') || props.photo.endsWith('.jpg') || props.photo.endsWith('.jpeg')) {
            photoImage = await pdfDoc.embedJpg(photoBytes);
        } else {
            photoImage = await pdfDoc.embedPng(photoBytes);
        }
        page.drawImage(photoImage, { x: photoX + 2, y: photoY + 2, width: photoWidth - 4, height: photoHeight - 4 });
    } catch (e) {
        page.drawText('[PHOTO]', { x: photoX + 14, y: photoY + photoHeight / 2 - 4, size: 9, font, color: grayColor });
    }

    // ===== PERSONAL DETAILS (Center) =====
    const detailsX = photoX + photoWidth + 16;
    let detailY = frontCardY + cardHeight - 40;
    const labelSize = 10;
    const valueSize = 10;
    const fieldGap = 25;

    // Surname/Nom
    page.drawText('Surname/Nom', { x: detailsX, y: detailY, size: labelSize, font: fontBold, color: grayColor });
    page.drawText((props.surname || '-').toUpperCase(), { x: detailsX, y: detailY - 12, size: valueSize, font: fontBold, color: blackColor });
    detailY -= fieldGap;

    // Given Names/Prénoms
    page.drawText('Given Names/Prénoms', { x: detailsX, y: detailY-5, size: labelSize, font: fontBold, color: grayColor });
    const othernamesDisplay = (props.othernames || '-').toUpperCase();
    // truncate if too long to fit
    const maxOtherW = 145;
    let othernamesFit = othernamesDisplay;
    while (othernamesFit.length > 1 && font.widthOfTextAtSize(othernamesFit, valueSize) > maxOtherW) {
        othernamesFit = othernamesFit.slice(0, -1);
    }
    page.drawText(othernamesFit, { x: detailsX, y: detailY - 18, size: valueSize, font: fontBold, color: blackColor });
    detailY -= fieldGap;

    // Date of Birth
    page.drawText('Date of Birth', { x: detailsX, y: detailY-12, size: labelSize, font: fontBold, color: grayColor });
    page.drawText(props.dob || '-', { x: detailsX, y: detailY - 25, size: valueSize, font: fontBold, color: blackColor });

    // ===== QR CODE (Top-right) =====
    const qrSize = 70;
    const qrX = cardMarginX + cardWidth - qrSize - 14;
    const qrY = frontCardY + cardHeight - qrSize - 30;

    // NGA label
    const ngaW = fontBold.widthOfTextAtSize('NGA', 9);
    page.drawText('NGA', { x: qrX + (qrSize - ngaW) / 2, y: qrY + qrSize + 6, size: 9, font: fontBold, color: blackColor });

    try {
        const qrImage = await pdfDoc.embedPng(qrImageBytes);
        page.drawImage(qrImage, { x: qrX, y: qrY, width: qrSize, height: qrSize });
    } catch (e) {
        console.warn('Could not embed QR code:', e);
    }

    // ===== SEPARATOR LINE =====
    const sepY = frontCardY + 90;
    // page.drawLine({ start: { x: cardMarginX + 14, y: sepY }, end: { x: cardMarginX + cardWidth - 14, y: sepY }, thickness: 0.5, color: lightGrayColor });

    // ===== NIN SECTION (Bottom) =====
    const ninLabelY = sepY - 14;
    const ninLabelText = 'NATIONAL IDENTIFICATION NUMBER (NIN)';
    const ninLabelW = fontBold.widthOfTextAtSize(ninLabelText, 7.5);
    page.drawText(ninLabelText, { x: cardMarginX + (cardWidth - ninLabelW) / 2 - 20, y: ninLabelY, size: 10, font: fontBold, color: blackColor });

    const formattedNin = (props.nin || '').split('').join(' ');
    const ninFontSize = 20;
    const ninW = fontBold.widthOfTextAtSize(formattedNin, ninFontSize);
    page.drawText(formattedNin, { x: cardMarginX + (cardWidth - ninW) / 2, y: ninLabelY - 22, size: ninFontSize, font: fontBold, color: blackColor, opacity: 0.80 });

    // Verification note
    const noteText = 'KINDLY ENSURE YOU SCAN THE BARCODE TO VERIFY THE CREDENTIALS';
    const noteW = font.widthOfTextAtSize(noteText, 6);
    page.drawText(noteText, { x: cardMarginX + (cardWidth - noteW) / 2, y: frontCardY + 40, size: 6, font, color: grayColor });

    // ===== WATERMARK - single centered text on front =====
    const wmText = 'PREVIEW';
    const wmSize = 50;
    const wmW = fontBold.widthOfTextAtSize(wmText, wmSize);
    page.drawText(wmText, {
        x: cardMarginX + (cardWidth - wmW) / 2 + 10,
        y: frontCardY + cardHeight / 2 - wmSize / 2 - 50,
        size: wmSize,
        font: fontBold,
        color: rgb(0.72, 0.72, 0.72),
        rotate: degrees(30),
        opacity: 0.28,
    });

    // // ===== LABEL "FRONT" =====
    // page.drawText('FRONT', { x: cardMarginX + cardWidth + 8, y: frontCardY + cardHeight / 2 - 5, size: 9, font: fontBold, color: grayColor });

    // ===================================================
    // ===== BACK CARD ===================================
    // ===================================================
    const backCardY = frontCardY - cardHeight - 38;

    // Rounded card background
    drawRoundedRect(page, cardMarginX, backCardY, cardWidth, cardHeight, r, cardBgColor, cardBorderColor, 1.2);

   

       // ===== BACK SIDE =====
    try {
        const bgImageBytes2 = await fetch('/images/ninSlipbackv2.jpg').then(res => res.arrayBuffer());
        const bgImage2 = await pdfDoc.embedJpg(bgImageBytes2);
        const bgImageScaled2 = bgImage2.scaleToFit(cardWidth, cardHeight);
        
        page.drawImage(bgImage2, {
            x: cardMarginX,
            y: backCardY,
            width: bgImageScaled2.width+44,
            height: bgImageScaled2.height,
        });
    } catch (e) {
        console.warn('Could not load back background:', e);
        page.drawRectangle({
            x: cardMarginX, y: backCardY, width: cardWidth, height: cardHeight,
            color: rgb(0.95, 0.95, 0.95), borderColor: rgb(0.8, 0.8, 0.8), borderWidth: 1,
        });
    }
    // ===== INSTRUCTIONS =====
    page.drawText('Cut out the NIN slip below. Fold along the dotted line and laminate as desired.', {
        x: cardMarginX, y: frontCardY + cardHeight + 22, size: 8.5, font, color: grayColor,
    });

    // ===== FOOTER =====
    // page.drawText('This document is computer generated for verification purposes only.', {
    //     x: cardMarginX, y: backCardY - 22, size: 7.5, font, color: lightGrayColor,
    // });

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
            link.download = `${props.surname}_${props.nin}_slip_v2.pdf`;
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
