/**
 * Unit tests for CameraCapture (mocked mediaDevices).
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useCamera } from './CameraCapture';

const fakeStream = () => ({ getTracks: () => [{ stop: vi.fn() }] });

function stubGetUserMedia(stream = fakeStream()) {
    Object.defineProperty(navigator, 'mediaDevices', {
        value: { getUserMedia: vi.fn(async () => stream) },
        configurable: true,
    });
}

function stubCanvas() {
    const orig = document.createElement.bind(document);
    vi.spyOn(document, 'createElement').mockImplementation((tag, ...args) => {
        if (tag === 'canvas') {
            return {
                width: 0,
                height: 0,
                getContext: () => ({ drawImage: vi.fn() }),
                toBlob: (cb) => cb(new Blob(['jpeg'], { type: 'image/jpeg' })),
            };
        }
        return orig(tag, ...args);
    });
}

function attachFakeVideo(result) {
    result.current.videoRef.current = {
        videoWidth: 640,
        videoHeight: 480,
        srcObject: null,
        play: vi.fn(async () => {}),
    };
}

describe('useCamera', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
        delete navigator.mediaDevices;
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('reports unavailable when getUserMedia is missing', async () => {
        const { result } = renderHook(() => useCamera());
        await act(() => result.current.start());
        expect(result.current.state).toBe('unavailable');
    });

    it('recordClip resolves null when no stream is active', async () => {
        const { result } = renderHook(() => useCamera());
        const clip = await result.current.recordClip(10);
        expect(clip).toBeNull();
    });

    it('falls back to a still frame when MediaRecorder is undefined', async () => {
        stubGetUserMedia();
        stubCanvas();
        const { result } = renderHook(() => useCamera());
        attachFakeVideo(result);
        await act(() => result.current.start());
        expect(result.current.state).toBe('active');

        const clip = await result.current.recordClip(10);
        expect(clip).toBeInstanceOf(File);
        expect(clip.name).toBe('liveness.jpg');
        expect(clip.type).toBe('image/jpeg');
    });

    it('records a webm clip when MediaRecorder is available', async () => {
        stubGetUserMedia();

        class MockRecorder {
            static isTypeSupported(m) {
                return m === 'video/webm';
            }
            start() {
                this.ondataavailable?.({ data: new Blob(['vid'], { type: 'video/webm' }) });
            }
            stop() {
                this.onstop?.();
            }
        }
        vi.stubGlobal('MediaRecorder', MockRecorder);

        const { result } = renderHook(() => useCamera());
        await act(() => result.current.start());

        const clip = await result.current.recordClip(10);
        expect(clip).toBeInstanceOf(File);
        expect(clip.name).toBe('liveness.webm');
        expect(clip.type).toBe('video/webm');
    });

    it('falls back to a still frame when MediaRecorder errors', async () => {
        stubGetUserMedia();
        stubCanvas();

        class ErrorRecorder {
            static isTypeSupported() {
                return true;
            }
            start() {
                this.onerror?.(new Error('encoder failed'));
            }
        }
        vi.stubGlobal('MediaRecorder', ErrorRecorder);

        const { result } = renderHook(() => useCamera());
        attachFakeVideo(result);
        await act(() => result.current.start());

        const clip = await result.current.recordClip(10);
        expect(clip).toBeInstanceOf(File);
        expect(clip.name).toBe('liveness.jpg');
    });
});
