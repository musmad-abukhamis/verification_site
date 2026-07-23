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
    // Reassigned if the details table needs a second page.
    let page = pdfDoc.addPage([width, height]);
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

    /**
     * Split a single token that is itself wider than the column — a long email
     * or an address run together without spaces. Without this a word-only wrap
     * emits it as one over-long line, which is the overflow all over again.
     */
    const breakToken = (token, maxWidth, f, size) => {
        const chunks = [];
        let current = '';

        for (const char of token) {
            const test = current + char;

            // `current === ''` guards a glyph wider than the column: keep it
            // rather than loop forever trying to make it fit.
            if (current === '' || f.widthOfTextAtSize(test, size) <= maxWidth) {
                current = test;
            } else {
                chunks.push(current);
                current = char;
            }
        }

        if (current) chunks.push(current);

        return chunks;
    };

    const wrapText = (text, maxWidth, f, size) => {
        const lines = [];
        let current = '';

        for (const word of String(text ?? '').split(/\s+/).filter(Boolean)) {
            const test = current ? `${current} ${word}` : word;

            if (f.widthOfTextAtSize(test, size) <= maxWidth) {
                current = test;
                continue;
            }

            if (current) {
                lines.push(current);
                current = '';
            }

            if (f.widthOfTextAtSize(word, size) <= maxWidth) {
                current = word;
                continue;
            }

            // Carry the tail forward so the next word can share its line.
            const chunks = breakToken(word, maxWidth, f, size);
            current = chunks.pop() ?? '';
            lines.push(...chunks);
        }

        if (current) lines.push(current);

        // One empty line, so a blank field still draws a normal-height row.
        return lines.length ? lines : [''];
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

    // Text is inset 5pt from the cell edge, so the usable width is 10pt less
    // than the cell. Wrapping to the full cell width would push the last
    // characters onto (and past) the border.
    const cellPadding = 5;
    const labelTextWidth = labelWidth - cellPadding * 2;
    const valueTextWidth = detailsColumnWidth - labelWidth - cellPadding * 2;

    let cursorY = detailsHeaderY - lineHeight;

    details.forEach(([label, value]) => {
        // A row is as tall as its tallest cell. Addresses routinely need two or
        // three lines; drawing them into a fixed 18pt row is what sent the text
        // off the edge of the page, since pdf-lib does not clip.
        const labelLines = wrapText(label, labelTextWidth, font, fontSize);
        const valueLines = wrapText(value, valueTextWidth, font, fontSize);
        const rowHeight = Math.max(labelLines.length, valueLines.length) * lineHeight;

        // Enough rows wrapped could otherwise run off the bottom of the page.
        if (cursorY - rowHeight < margin) {
            page = pdfDoc.addPage([width, height]);
            cursorY = height - margin;
        }

        // Row border.
        page.drawRectangle({
            x: detailsColumnX, y: cursorY - rowHeight, width: detailsColumnWidth, height: rowHeight,
            borderColor: black, borderWidth: 0.5,
        });
        // Vertical divider.
        page.drawRectangle({
            x: detailsColumnX + labelWidth, y: cursorY - rowHeight, width: 1, height: rowHeight, color: black,
        });

        // Label + value, both top-aligned within the row.
        labelLines.forEach((line, i) => {
            page.drawText(line, {
                x: detailsColumnX + cellPadding, y: cursorY - 12 - i * lineHeight,
                size: fontSize, font, color: black,
            });
        });
        valueLines.forEach((line, i) => {
            page.drawText(line, {
                x: detailsColumnX + labelWidth + cellPadding, y: cursorY - 12 - i * lineHeight,
                size: fontSize, font, color: black,
            });
        });

        cursorY -= rowHeight;
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
