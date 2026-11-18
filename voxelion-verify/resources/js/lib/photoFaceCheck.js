import { getFaceDetector, detectionToFace } from './faceDetector';
import { validatePhoto } from './faceGeometry';

const MAX_BYTES = 10 * 1024 * 1024; // match the server's 10MB upload cap
const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const LUMA_SAMPLE = 64; // downscale before reading pixels — cheap

/**
 * Analyse an uploaded photo file with the same face detector used by the
 * live camera. Everything runs in-browser — the image never leaves the
 * device for detection.
 *
 * Returns:
 *   state    'ok' | 'invalid' | 'unreadable' | 'unsupported'
 *   verdict/text/tone  from validatePhoto (on 'ok'/'invalid')
 *   face     normalised face metrics + image dims (for the oval overlay)
 */
export async function analyzePhotoFile(file) {
    if (!ACCEPTED_TYPES.includes(file.type) || file.size > MAX_BYTES) {
        return { state: 'unreadable' };
    }

    let bitmap;
    try {
        // 'from-image' bakes EXIF rotation into the pixels so detector
        // coordinates match what the <img> renders.
        bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
        try {
            bitmap = await createImageBitmap(file);
        } catch {
            return { state: 'unreadable' }; // corrupt / undecodable
        }
    }

    let detector;
    try {
        detector = await getFaceDetector('IMAGE');
    } catch {
        bitmap.close?.();
        return { state: 'unsupported' };
    }

    let result;
    try {
        result = detector.detect(bitmap);
    } catch {
        bitmap.close?.();
        return { state: 'unreadable' };
    }

    const luma = meanLuminance(bitmap);
    const imgW = bitmap.width;
    const imgH = bitmap.height;
    const faces = (result.detections ?? []).map((d) => detectionToFace(d, imgW, imgH));
    const primary = faces.reduce((a, b) => (a && a.w * a.h > b.w * b.h ? a : b), null);
    bitmap.close?.();

    const { verdict, text, tone } = validatePhoto(faces, luma);
    return {
        state: verdict === 'ok' ? 'ok' : 'invalid',
        verdict,
        text,
        tone,
        face: primary,
        imgW,
        imgH,
        faceCount: faces.length,
    };
}

/** Mean image luminance (0–255) from a downsampled draw. */
function meanLuminance(bitmap) {
    try {
        const canvas = document.createElement('canvas');
        canvas.width = LUMA_SAMPLE;
        canvas.height = LUMA_SAMPLE;
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(bitmap, 0, 0, LUMA_SAMPLE, LUMA_SAMPLE);
        const data = ctx.getImageData(0, 0, LUMA_SAMPLE, LUMA_SAMPLE).data;
        let sum = 0;
        for (let i = 0; i < data.length; i += 4) {
            sum += 0.2126 * data[i] + 0.7152 * data[i + 1] + 0.0722 * data[i + 2];
        }
        return sum / (data.length / 4);
    } catch {
        return null; // can't measure — don't fail the check on lighting
    }
}
