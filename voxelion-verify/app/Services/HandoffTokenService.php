<?php

namespace App\Services;

use App\Models\VerificationSession;

/**
 * Verifies the one-time RS256 JWT OpinionPadi signs when handing a volunteer
 * off to Voxelion. Enforces: signature, issuer, expiry, and jti replay
 * protection (a jti already bound to a session is rejected).
 */
class HandoffTokenService
{
    public function __construct(private string $publicKeyPath, private string $issuer, private int $maxAgeSeconds) {}

    /**
     * @return array{sub:string,task_id:string,submission_id:?string,jti:string,exp:int}
     *
     * @throws HandoffException
     */
    public function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new HandoffException('malformed');
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;

        $header = json_decode($this->b64d($headerB64), true);
        if (! is_array($header) || ($header['alg'] ?? null) !== 'RS256') {
            throw new HandoffException('malformed');
        }

        $payload = json_decode($this->b64d($payloadB64), true);
        if (! is_array($payload)) {
            throw new HandoffException('malformed');
        }

        $signature = $this->b64d($sigB64);
        $publicKey = @file_get_contents($this->publicKeyPath);
        if ($publicKey === false || openssl_pkey_get_public($publicKey) === false) {
            throw new HandoffException('unverifiable');
        }

        $valid = openssl_verify("$headerB64.$payloadB64", $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($valid !== 1) {
            throw new HandoffException('invalid');
        }

        foreach (['iss', 'sub', 'task_id', 'jti', 'iat', 'exp'] as $claim) {
            if (! array_key_exists($claim, $payload)) {
                throw new HandoffException('malformed');
            }
        }

        if ($payload['iss'] !== $this->issuer) {
            throw new HandoffException('invalid');
        }

        $now = time();
        if (! is_numeric($payload['iat']) || (int) $payload['iat'] > $now) {
            throw new HandoffException('invalid');
        }
        if ((int) $payload['exp'] <= $now || (int) $payload['exp'] > $now + $this->maxAgeSeconds) {
            throw new HandoffException('expired');
        }

        if (VerificationSession::where('handoff_jti', $payload['jti'])->exists()) {
            throw new HandoffException('used');
        }

        return [
            'sub' => (string) $payload['sub'],
            'task_id' => (string) $payload['task_id'],
            'submission_id' => isset($payload['submission_id']) ? (string) $payload['submission_id'] : null,
            'jti' => (string) $payload['jti'],
            'exp' => (int) $payload['exp'],
        ];
    }

    /** Dev helper: mint a token the same way OpinionPadi would (RS256). */
    public static function mint(string $privateKeyPem, array $claims): string
    {
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $header = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $b64(json_encode($claims));
        openssl_sign("$header.$payload", $sig, $privateKeyPem, OPENSSL_ALGO_SHA256);

        return "$header.$payload.".$b64($sig);
    }

    private function b64d(string $s): string
    {
        $pad = strlen($s) % 4;
        if ($pad) {
            $s .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode(strtr($s, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
