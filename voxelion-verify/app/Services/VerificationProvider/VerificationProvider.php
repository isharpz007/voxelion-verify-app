<?php

namespace App\Services\VerificationProvider;

/**
 * Vendor boundary. Everything biometric flows through this interface so the
 * provider chosen in Phase 0 (Voxelion API or an alternative shortlisted via
 * NIST FRTE results) can be dropped in without touching the journey code.
 */
interface VerificationProvider
{
    /**
     * Photo quality heuristics (face detected, blur, lighting).
     *
     * @return array<string,array{passed:bool,message:string}>
     */
    public function photoChecks(string $localPath): array;

    /**
     * Submit a liveness capture for scoring.
     *
     * @return array{liveness_passed:bool, similarity_score:float, raw:array}
     */
    public function verifyLiveness(string $challenge, string $capturePath): array;
}
