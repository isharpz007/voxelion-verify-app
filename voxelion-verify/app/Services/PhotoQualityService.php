<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * Runs the photo quality gate: file format, size, dimensions, blur, and
 * lighting. Face detection + blur/lighting scoring are delegated to the
 * configured VerificationProvider so the heuristics can be swapped with a
 * real vendor without touching this class.
 */
class PhotoQualityService
{
    public function __construct(private VerificationProvider\VerificationProvider $provider) {}

    /** @return array{passed:bool, checks:array<string,array{passed:bool,message:string}>} */
    public function assess(UploadedFile $file): array
    {
        return $this->assessStoredFile($file->getRealPath(), (string) $file->getMimeType(), (int) $file->getSize());
    }

    /**
     * Same gate for a file already on disk — used by the RunQualityChecks
     * job, which reads captures back from the media store.
     *
     * @return array{passed:bool, checks:array<string,array{passed:bool,message:string}>}
     */
    public function assessStoredFile(string $path, string $mimeType, int $sizeBytes): array
    {
        $config = config('verification.photo');

        $checks = [
            'format' => [
                'passed' => in_array($mimeType, $config['allowed_mimes'], true),
                'message' => 'Unsupported format — use JPEG, PNG or WebP.',
            ],
            'size' => [
                'passed' => $sizeBytes >= $config['min_size_kb'] * 1024
                    && $sizeBytes <= $config['max_size_kb'] * 1024,
                'message' => 'File size outside the accepted range.',
            ],
        ];

        $dimensions = @getimagesize($path);
        $checks['dimensions'] = [
            'passed' => $dimensions !== false
                && $dimensions[0] >= $config['min_width']
                && $dimensions[1] >= $config['min_height'],
            'message' => 'Photo resolution too low — retake in a sharper frame.',
        ];

        $providerChecks = $dimensions === false
            ? ['face' => ['passed' => false, 'message' => 'No face detected — centre your face in the oval.'],
                'blur' => ['passed' => false, 'message' => 'Photo is too blurry — hold still and retake.'],
                'lighting' => ['passed' => false, 'message' => 'Lighting is too dim — move to a brighter area.']]
            : $this->provider->photoChecks($path);

        $checks = array_merge($checks, $providerChecks);

        $passed = collect($checks)->every(fn ($c) => $c['passed']);

        return ['passed' => $passed, 'checks' => $checks];
    }
}
