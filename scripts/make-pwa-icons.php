<?php

/**
 * Regenerates the PWA icon set in public/icons/ from public/images/abc logo.png.
 *
 *   php scripts/make-pwa-icons.php
 *
 * Only needs re-running when the logo changes. The output is committed, so a
 * deploy never depends on GD being present on the server.
 *
 * Two families are produced, because Android treats them very differently:
 *
 * - `any`      — drawn as-is. Padded lightly; the launcher may round the
 *                corners itself, and the mark is already a circle.
 * - `maskable` — the launcher crops it to whatever shape the device uses and
 *                only guarantees the centre 80%-diameter circle survives. The
 *                mark is scaled well inside that safe zone, on a full-bleed
 *                white plate, so a circular *or* squircle mask both look
 *                deliberate rather than clipped.
 *
 * The plate is white, not transparent: the mark is dark blue with black and
 * red inside it, so on a dark launcher or the iOS home screen (which fills
 * transparency with black) a transparent version would be close to invisible.
 */

$root = dirname(__DIR__);
$source = $root . '/public/images/abc logo.png';
$outDir = $root . '/public/icons';

if (! extension_loaded('gd')) {
    fwrite(STDERR, "GD is required.\n");
    exit(1);
}

if (! is_file($source)) {
    fwrite(STDERR, "Missing source logo: {$source}\n");
    exit(1);
}

if (! is_dir($outDir) && ! mkdir($outDir, 0755, true)) {
    fwrite(STDERR, "Could not create {$outDir}\n");
    exit(1);
}

$logo = imagecreatefrompng($source);
imagepalettetotruecolor($logo);
imagealphablending($logo, false);
imagesavealpha($logo, true);

/**
 * The source has a wide transparent/white margin. Crop to the ink so every
 * icon below can size the mark against a known, tight box — otherwise the
 * padding percentages are measured against whitespace and the mark renders
 * far smaller than intended.
 */
function inkBounds($img): array
{
    $w = imagesx($img);
    $h = imagesy($img);
    $minX = $w;
    $minY = $h;
    $maxX = -1;
    $maxY = -1;

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $rgba = imagecolorat($img, $x, $y);
            $alpha = ($rgba >> 24) & 0x7F;      // 0 opaque .. 127 transparent
            if ($alpha > 96) {
                continue;
            }

            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;
            if ($r > 244 && $g > 244 && $b > 244) {
                continue;                        // white margin counts as empty
            }

            if ($x < $minX) $minX = $x;
            if ($y < $minY) $minY = $y;
            if ($x > $maxX) $maxX = $x;
            if ($y > $maxY) $maxY = $y;
        }
    }

    return $maxX < 0 ? [0, 0, $w - 1, $h - 1] : [$minX, $minY, $maxX, $maxY];
}

[$x0, $y0, $x1, $y1] = inkBounds($logo);
$srcW = $x1 - $x0 + 1;
$srcH = $y1 - $y0 + 1;

printf("Source %dx%d, cropped to %dx%d at (%d,%d)\n", imagesx($logo), imagesy($logo), $srcW, $srcH, $x0, $y0);

/**
 * Draws the cropped mark centred on a white square of $size, occupying
 * $coverage of the shorter axis. Aspect ratio is preserved.
 */
function render($logo, int $size, float $coverage, array $crop, string $path): void
{
    [$x0, $y0, $srcW, $srcH] = $crop;

    $canvas = imagecreatetruecolor($size, $size);
    imagealphablending($canvas, true);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));

    $box = (int) round($size * $coverage);
    $scale = min($box / $srcW, $box / $srcH);
    $dstW = max(1, (int) round($srcW * $scale));
    $dstH = max(1, (int) round($srcH * $scale));

    imagecopyresampled(
        $canvas,
        $logo,
        (int) round(($size - $dstW) / 2),
        (int) round(($size - $dstH) / 2),
        $x0,
        $y0,
        $dstW,
        $dstH,
        $srcW,
        $srcH,
    );

    imagesavealpha($canvas, true);
    imagepng($canvas, $path, 9);
    imagedestroy($canvas);

    printf("  %-34s %dx%d\n", basename($path), $size, $size);
}

$crop = [$x0, $y0, $srcW, $srcH];

$targets = [
    // file                      size  coverage
    ['icon-192.png',              192, 0.86],
    ['icon-512.png',              512, 0.86],
    // Inside the 80%-diameter safe circle with room to spare.
    ['icon-maskable-192.png',     192, 0.60],
    ['icon-maskable-512.png',     512, 0.60],
    // iOS applies its own corner radius and never masks past the edge.
    ['apple-touch-icon.png',      180, 0.82],
];

foreach ($targets as [$file, $size, $coverage]) {
    render($logo, $size, $coverage, $crop, $outDir . '/' . $file);
}

imagedestroy($logo);

echo "Done.\n";
