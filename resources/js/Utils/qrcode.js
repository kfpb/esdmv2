import QRCode from 'qrcode';

/**
 * Generate SVG string for QR Code (Standard ISO/IEC 18004 compliant)
 * @param {string} text - The text/code to encode
 * @param {number} size - Output width & height in pixels (default: 180)
 * @returns {string} Clean SVG markup string
 */
export function generateQrSvg(text, size = 180) {
    if (!text) return '';
    try {
        let svgResult = '';
        QRCode.toString(
            String(text),
            {
                type: 'svg',
                margin: 4, // ISO/IEC 18004 4-module quiet zone requirement
                width: size,
                errorCorrectionLevel: 'M',
                color: {
                    dark: '#000000',
                    light: '#ffffff',
                },
            },
            (err, svg) => {
                if (!err && svg) {
                    svgResult = svg;
                }
            }
        );
        return svgResult;
    } catch (e) {
        console.error('Error generating QR code:', e);
        return '';
    }
}

/**
 * Generate DataURL (PNG base64) for QR Code
 * @param {string} text
 * @param {number} size
 * @returns {Promise<string>}
 */
export async function generateQrDataUrl(text, size = 180) {
    if (!text) return '';
    try {
        return await QRCode.toDataURL(String(text), {
            margin: 4,
            width: size,
            errorCorrectionLevel: 'M',
        });
    } catch (e) {
        console.error('Error generating QR DataURL:', e);
        return '';
    }
}
