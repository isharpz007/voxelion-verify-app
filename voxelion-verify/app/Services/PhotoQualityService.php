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
        $config = config('verification.photo');

        $checks = [
            'format' => [
                'passed' => in_array($file->getMimeType(), $config['allowed_mimes'], true),
                'message' => 'Unsupported format — use JPEG, PNG or WebP.',
            ],
            'size' => [
                'passed' => $file->getSize() >= $config['min_size_kb'] * 1024
                    && $file->getSize() <= $config['max_size_kb'] * 1024,
                'message' => 'File size outside the accepted range.',
            ],
        ];

        $dimensions = @getimagesize($file->getRealPath());
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
            : $this->provider->photoChecks($file->getRealPath());

        $checks = array_merge($checks, $providerChecks);

        $passed = collect($checks)->every(fn ($c) => $c['passed']);

        return ['passed' => $passed, 'checks' => $checks];
    }
}
