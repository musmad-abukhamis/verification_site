<template>
    <div>
        <button
            @click="handleDownload"
            :disabled="loading"
            class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-lime-600 hover:bg-lime-700 text-white rounded-full font-medium disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
            <svg v-if="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            {{ loading ? 'Processing...' : 'Download Long Slip' }}
        </button>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { PDFDocument, rgb, StandardFonts } from 'pdf-lib';

// Keys mirror the React BvnLongSlip component / BvnLongSlipGenerator `data` prop.
const props = defineProps({
    bvn: { type: String, default: '' },
    nin: { type: String, default: '' },
    firstName: { type: String, default: '' },
    lastName: { type: String, default: '' },
    middleName: { type: String, default: '' },
    phone: { type: String, default: '' },
    email: { type: String, default: '' },
    dob: { type: String, default: '' },
    gender: { type: String, default: '' },
    marital: { type: String, default: '' },
    state: { type: String, default: '' },
    lga: { type: String, default: '' },
    address: { type: String, default: '' },
    enrollmentBank: { type: String, default: '' },
    enrollmentBranch: { type: String, default: '' },
    regDate: { type: String, default: '' },
    residentialAddr: { type: String, default: '' },
    imageUrl: { type: String, default: '' },
});

const loading = ref(false);

const generatePDF = async () => {
    const width = 595;
    const height = 842;
    const margin = 30;
    const fontSize = 12;
    const lineHeight = 18;

    const pdfDoc = await PDFDocument.create();
    const page = pdfDoc.addPage([width, height]);
    const font = await pdfDoc.embedFont(StandardFonts.Helvetica);
    const fontBold = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
    const black = rgb(0, 0, 0);

    const headerX = margin;
    const headerY = height - margin;

    // ===== Header logo (top-left) =====
    let logoW = 0;
    let logoH = 0;
    try {
        const logoBytes = await fetch('/images/bvnlogo.jpeg').then((r) => r.arrayBuffer());
        const logo = await pdfDoc.embedJpg(logoBytes);
        const scaled = logo.scaleToFit((width - 2 * margin) * 0.3, 50);
        logoW = scaled.width;
        logoH = scaled.height;
        page.drawImage(logo, { x: headerX + 9, y: headerY - logoH, width: logoW, height: logoH });
    } catch (e) {
        // ignore missing logo
    }

    // ===== Header text box ("successfully verified") =====
    const headerTextX = headerX + logoW + 10;
    const headerTextWidth = width - headerTextX - margin;

    page.drawRectangle({
        x: headerTextX, y: headerY - 50, width: headerTextWidth, height: 50,
        borderColor: black, borderWidth: 0.5,
    });

    const wrapText = (text, maxWidth, f, size) => {
        const words = text.split(' ');
        const lines = [];
        let current = '';
        for (const word of words) {
            const test = current ? `${current} ${word}` : word;
            if (f.widthOfTextAtSize(test, size) <= maxWidth) {
                current = test;
            } else {
                if (current) lines.push(current);
                current = word;
            }
        }
        if (current) lines.push(current);
        return lines;
    };

    const headerLines = wrapText(
        'The Bank Verification Number has been Successfully Verified',
        headerTextWidth - 10,
        fontBold,
        fontSize,
    );
    let hy = headerY - 30;
    headerLines.forEach((line) => {
        page.drawText(line, { x: headerTextX + 5, y: hy, size: fontSize, font: fontBold, color: black });
        hy -= lineHeight;
    });

    // ===== Timestamp =====
    const now = new Date();
    const currentDate = `${now.getMonth() + 1}/${now.getDate()}/${now.getFullYear()}, ${now.toLocaleTimeString('en-US')}`;
    page.drawText(currentDate, { x: width - margin - 140, y: headerY - 70, size: fontSize, font, color: black });

    // ===== Column geometry =====
    const detailsColumnWidth = (width - 2 * margin) * 0.6;
    const imageColumnWidth = (width - 2 * margin) * 0.4;
    const detailsColumnX = margin + imageColumnWidth;

    // ===== "Personal Details" header row =====
    const detailsHeaderY = headerY - 100;
    page.drawRectangle({
        x: detailsColumnX, y: detailsHeaderY, width: detailsColumnWidth, height: lineHeight,
        borderColor: black, borderWidth: 0.5,
    });
    page.drawText('Personal Details', {
        x: detailsColumnX + detailsColumnWidth / 2 - 40, y: detailsHeaderY + 5,
        size: fontSize, font: fontBold, color: black,
    });

    // ===== User photo (left column) =====
    if (props.imageUrl) {
        try {
            const photoBytes = await fetch(props.imageUrl).then((r) => r.arrayBuffer());
            const isJpg = props.imageUrl.includes('image/jpeg') || props.imageUrl.includes('image/jpg')
                || props.imageUrl.endsWith('.jpg') || props.imageUrl.endsWith('.jpeg');
            const photo = isJpg ? await pdfDoc.embedJpg(photoBytes) : await pdfDoc.embedPng(photoBytes);
            const scaled = photo.scaleToFit(imageColumnWidth - 20, 100);
            const drawW = scaled.width + 57;
            const drawH = scaled.height + 150;
            const drawBottomY = detailsHeaderY - scaled.height - 170;
            page.drawImage(photo, { x: margin + 10, y: drawBottomY, width: drawW, height: drawH });
        } catch (e) {
            // ignore photo embed failure
        }
    }

    // ===== Details table rows =====
    const details = [
        ['BVN', props.bvn],
        ['NIN', props.nin],
        ['First Name', props.firstName],
        ['Last Name', props.lastName],
        ['Middle Name', props.middleName],
        ['Phone', props.phone],
        ['Email', props.email],
        ['Date of birth', props.dob],
        ['Gender', props.gender],
        ['Marital Status', props.marital],
        ['State of Origin', props.state],
        ['LGA of Origin', props.lga],
        ['Address', props.address],
        ['Enrollment Bank', props.enrollmentBank],
        ['Enrollment Branch', props.enrollmentBranch],
        ['Registration Date', props.regDate],
        ['Residential Address', props.residentialAddr],
    ];

    const labelWidth = detailsColumnWidth * 0.4;
    let cursorY = detailsHeaderY - lineHeight;
    details.forEach(([label, value]) => {
        // Row border.
        page.drawRectangle({
            x: detailsColumnX, y: cursorY - lineHeight, width: detailsColumnWidth, height: lineHeight,
            borderColor: black, borderWidth: 0.5,
        });
        // Vertical divider.
        page.drawRectangle({
            x: detailsColumnX + labelWidth, y: cursorY - lineHeight, width: 1, height: lineHeight, color: black,
        });
        // Label + value.
        page.drawText(String(label), { x: detailsColumnX + 5, y: cursorY - 12, size: fontSize, font, color: black });
        page.drawText(String(value || ''), { x: detailsColumnX + labelWidth + 5, y: cursorY - 12, size: fontSize, font, color: black });
        cursorY -= lineHeight;
    });

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
            link.download = `${props.lastName || 'bvn'}_${props.bvn}_long_slip.pdf`;
            link.click();
            URL.revokeObjectURL(pdfURL);
        }
    } catch (error) {
        console.error('Error generating BVN long slip PDF:', error);
        alert('Error generating PDF. Please try again.');
    }
    loading.value = false;
};

defineExpose({ generatePDF, handleDownload });
</script>
