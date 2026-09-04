<?php
/**
 * QR Code Generator
 *
 * Generates REAL, standards-compliant, scannable QR codes server-side
 * using the bundled phpqrcode library (includes/lib/phpqrcode).
 *
 * This replaced a previous version that only drew a fake md5-hash
 * pattern (finder-corner squares + random blocks) that LOOKED like a
 * QR code but could never actually be decoded by a scanner - that
 * was the root cause of "valid-looking" QR images that scanners
 * couldn't read.
 */

require_once __DIR__ . '/lib/phpqrcode/qrlib.php';

/**
 * Generate a real QR code PNG (base64-encoded) for the given data.
 *
 * @param string $data  The raw text/JSON to encode (e.g. employee QR payload)
 * @param int    $size  Target image size in pixels (approximate; QR size is
 *                       controlled via pixel-per-module + margin instead)
 * @return string        Base64-encoded PNG image data
 */
function generateQRCode($data, $size = 300) {
    if (!extension_loaded('gd')) {
        // GD is required to rasterize the QR code as a PNG.
        // Without it we cannot produce a real scannable image.
        throw new RuntimeException('The GD PHP extension is required to generate QR codes.');
    }

    // Pick a pixel-per-module size that roughly matches the requested
    // overall image size. QR modules are drawn at a fixed scale rather
    // than stretched, so codes stay crisp and scannable at any size.
    $pixel_per_point = max(3, (int) round($size / 70));
    $margin = 4; // quiet zone (required by the QR spec so scanners can lock on)

    ob_start();
    QRcode::png(
        $data,
        false,                 // false = output directly to buffer instead of a file
        QR_ECLEVEL_H,          // high error correction, so a scuffed/partly-covered
                                // printout can still be read reliably
        $pixel_per_point,
        $margin
    );
    $image_data = ob_get_clean();

    if ($image_data === false || $image_data === '') {
        throw new RuntimeException('Failed to generate QR code image.');
    }

    return base64_encode($image_data);
}

/**
 * Convenience alias kept for backwards compatibility with existing code
 * that calls generateSimpleQR().
 */
function generateSimpleQR($data, $size = 300) {
    return generateQRCode($data, $size);
}
