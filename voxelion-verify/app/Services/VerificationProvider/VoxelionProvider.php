<?php

namespace App\Services\VerificationProvider;

/**
 * Voxelion-backed provider. Until the vendor API contract lands (Phase 0
 * decision #1) this implementation simulates realistic behaviour locally so
 * the full journey is exercisable end-to-end. Swap `verifyLiveness` and
 * `photoChecks` internals for real HTTP calls when the SDK/API is confirmed.
 */
class VoxelionProvider implements VerificationProvider
{
    public function photoChecks(string $localPath): array
    {
        $info = @getimagesize($localPath);
        $hasImage = $info !== false;
        // Deterministic-but-realistic stand-ins: use real pixel data heuristics.
        $brightness = $hasImage ? $this->estimateBrightness($localPath) : 0.0;

        return [
            'face' => [
                'passed' => $hasImage,
                'message' => 'No face detected — centre your face inside the oval.',
            ],
            'blur' => [
                'passed' => $hasImage,
                'message' => 'Photo is too blurry — hold still and retake.',
            ],
            'lighting' => [
                'passed' => $brightness >= 0.15,
                'message' => 'Lighting is too dim — move to a brighter area.',
            ],
        ];
    }

    public function verifyLiveness(string $challenge, string $capturePath): array
    {
        // Simulated vendor response. Real implementation posts the capture to
        // the provider and maps its liveness + match scores.
        $exists = file_exists($capturePath);
        $score = $exists ? round(0.86 + (crc32(basename($capturePath)) % 12) / 100, 4) : 0.0;

        return [
            'liveness_passed' => $exists,
            'similarity_score' => $score,
            'raw' => [
                'provider' => 'voxelion',
                'simulated' => true,
                'challenge' => $challenge,
                'evaluated_at' => now()->toIso8601String(),
            ],
        ];
    }

    private function estimateBrightness(string $path): float
    {
        // Average luminance of a small sample of the file bytes — cheap
        // proxy until provider scoring is wired in.
        $sample = @file_get_contents($path, false, null, 0, 8192);
        if ($sample === false || $sample === '') {
            return 0.0;
        }

        $sum = 0;
        $len = strlen($sample);
        for ($i = 0; $i < $len; $i++) {
            $sum += ord($sample[$i]);
        }

        return ($sum / $len) / 255;
    }
}
