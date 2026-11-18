import { describe, it, expect } from 'vitest';
import { classifyFace, ema, faceToOverlay, isSteady, NEUTRAL_OVAL, validatePhoto } from './faceGeometry';

const face = (over = {}) => ({ cx: 0.5, cy: 0.5, w: 0.4, tiltDeg: 0, ...over });

describe('classifyFace', () => {
    it('returns none without a face', () => {
        expect(classifyFace(null)).toBe('none');
    });
    it('flags a small face as too far', () => {
        expect(classifyFace(face({ w: 0.2 }))).toBe('too_far');
    });
    it('flags a large face as too close', () => {
        expect(classifyFace(face({ w: 0.7 }))).toBe('too_close');
    });
    it('flags horizontal off-centre', () => {
        expect(classifyFace(face({ cx: 0.7 }))).toBe('off_center_x');
        expect(classifyFace(face({ cx: 0.3 }))).toBe('off_center_x');
    });
    it('flags vertical off-centre', () => {
        expect(classifyFace(face({ cy: 0.7 }))).toBe('off_center_y');
    });
    it('flags head tilt', () => {
        expect(classifyFace(face({ tiltDeg: 20 }))).toBe('tilted');
        expect(classifyFace(face({ tiltDeg: -20 }))).toBe('tilted');
    });
    it('accepts a centred, sized, level face', () => {
        expect(classifyFace(face())).toBe('ok');
    });
    it('checks distance before position', () => {
        // A tiny off-centre face should hear "move closer", not "centre".
        expect(classifyFace(face({ w: 0.15, cx: 0.9 }))).toBe('too_far');
    });
});

describe('ema', () => {
    it('returns next on first sample', () => {
        const f = face();
        expect(ema(null, f)).toEqual(f);
    });
    it('smooths toward the new value', () => {
        const out = ema(face({ cx: 0.5 }), face({ cx: 0.6 }), 0.5);
        expect(out.cx).toBeCloseTo(0.55);
    });
    it('damps a one-frame jump', () => {
        const out = ema(face({ cx: 0.5 }), face({ cx: 0.9 }), 0.35);
        expect(out.cx).toBeLessThan(0.7);
    });
});

describe('isSteady', () => {
    it('needs a ≥600ms window', () => {
        const samples = [{ t: 0, cx: 0.5, cy: 0.5 }, { t: 300, cx: 0.5, cy: 0.5 }];
        expect(isSteady(samples, 400)).toBe(false);
    });
    it('accepts a calm window', () => {
        const samples = [{ t: 0, cx: 0.5, cy: 0.5 }, { t: 700, cx: 0.51, cy: 0.49 }];
        expect(isSteady(samples, 700)).toBe(true);
    });
    it('rejects movement', () => {
        const samples = [{ t: 0, cx: 0.3, cy: 0.5 }, { t: 700, cx: 0.6, cy: 0.5 }];
        expect(isSteady(samples, 700)).toBe(false);
    });
});

describe('faceToOverlay', () => {
    const videoW = 1280, videoH = 720;

    it('centres a centred face in a matching-aspect container', () => {
        // Container 3:4 portrait; video 16:9 landscape → crop left/right.
        const viewW = 300, viewH = 400;
        const o = faceToOverlay(face(), videoW, videoH, viewW, viewH);
        expect(o.y).toBeCloseTo(0.5);
        expect(o.w).toBeGreaterThan(0.4); // oval is larger than the face box
        expect(o.h).toBeCloseTo((0.4 * 1.95 * (viewH / videoH) * videoH) / viewH, 5);
    });

    it('tracks a face to the correct side under cover-crop', () => {
        const viewW = 300, viewH = 400;
        // Face at 20% of the *video* width — inside the horizontally cropped area.
        const scale = viewH / videoH;          // cover scale: height binds
        const dispW = videoW * scale;          // ~711 px of video shown across 300 px
        const offX = (dispW - viewW) / 2;
        const expectedX = ((0.2 * dispW) - offX) / viewW;
        const o = faceToOverlay(face({ cx: 0.2 }), videoW, videoH, viewW, viewH);
        expect(o.x).toBeCloseTo(expectedX);
    });

    it('handles portrait video in a portrait container', () => {
        const o = faceToOverlay(face({ cy: 0.3 }), 480, 640, 300, 400);
        expect(o.y).toBeCloseTo(0.3);
    });

    it('returns null with zero dimensions', () => {
        expect(faceToOverlay(face(), 0, 720, 300, 400)).toBeNull();
    });

    it('neutral oval is a centred placeholder', () => {
        expect(NEUTRAL_OVAL).toMatchObject({ x: 0.5, y: 0.5 });
    });
});

// Upload faces carry edge fractions (from detectionToFace) unlike live faces.
const upFace = (over = {}) => ({
    cx: 0.5, cy: 0.5, w: 0.45, h: 0.55,
    left: 0.275, top: 0.225, right: 0.725, bottom: 0.775,
    tiltDeg: 0, ...over,
});

describe('validatePhoto (uploaded photos)', () => {
    it('rejects an image with no faces', () => {
        expect(validatePhoto([], 150).verdict).toBe('no_face');
    });
    it('rejects multiple faces', () => {
        expect(validatePhoto([upFace(), upFace()], 150).verdict).toBe('multi_face');
    });
    it('rejects a too-small face', () => {
        expect(validatePhoto([upFace({ w: 0.15 })], 150).verdict).toBe('too_small');
    });
    it('rejects a face cropped by a frame edge', () => {
        expect(validatePhoto([upFace({ left: 0.005 })], 150).verdict).toBe('cropped');
        expect(validatePhoto([upFace({ right: 0.999 })], 150).verdict).toBe('cropped');
        expect(validatePhoto([upFace({ top: 0 })], 150).verdict).toBe('cropped');
    });
    it('rejects a tilted face', () => {
        expect(validatePhoto([upFace({ tiltDeg: 25 })], 150).verdict).toBe('tilted');
    });
    it('rejects a dark image', () => {
        expect(validatePhoto([upFace()], 20).verdict).toBe('dark');
    });
    it('skips the lighting check when luminance is unavailable', () => {
        expect(validatePhoto([upFace()], null).verdict).toBe('ok');
    });
    it('accepts a well-lit single centred face', () => {
        const out = validatePhoto([upFace()], 150);
        expect(out.verdict).toBe('ok');
        expect(out.tone).toBe('ok');
    });
    it('checks face count before size', () => {
        // Two small faces → "one face" message, not "too far".
        expect(validatePhoto([upFace({ w: 0.1 }), upFace({ w: 0.1 })], 150).verdict).toBe('multi_face');
    });
    it('checks cropping before tilt', () => {
        expect(validatePhoto([upFace({ left: 0, tiltDeg: 30 })], 150).verdict).toBe('cropped');
    });
});
