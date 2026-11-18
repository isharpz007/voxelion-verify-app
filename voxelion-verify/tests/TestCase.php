<?php

namespace Tests;

use App\Models\VerificationSession;
use App\Services\HandoffTokenService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! is_file(storage_path('app/keys/opinionpadi_private.pem'))) {
            $this->artisan('voxelion:keys');
        }
    }

    /** Mint a signed RS256 handoff token the way OpinionPadi would. */
    protected function mintHandoff(array $overrides = []): string
    {
        $private = file_get_contents(storage_path('app/keys/opinionpadi_private.pem'));

        return HandoffTokenService::mint($private, array_merge([
            'iss' => config('opinionpadi.issuer'),
            'sub' => 'vol-'.Str::random(8),
            'task_id' => 'OP-2026-7842',
            'submission_id' => 'sub_'.Str::random(6),
            'jti' => (string) Str::uuid(),
            'iat' => time(),
            'exp' => time() + 600,
        ], $overrides));
    }

    /**
     * Headers OpinionPadi would send on a signed /api/v1 call:
     * X-OpinionPadi-Signature = HMAC-SHA256("<ts>.<raw body>", api_secret).
     * Pass the request body exactly as it will be sent (array → JSON).
     * getJson() transmits "[]" for an empty payload — the default here
     * matches so GET calls verify the same body the middleware sees.
     */
    protected function signedApiHeaders(array|string $body = '[]', ?int $timestamp = null): array
    {
        $ts = (string) ($timestamp ?? time());
        $body = is_array($body) ? json_encode($body) : $body;

        return [
            'X-OpinionPadi-Timestamp' => $ts,
            'X-OpinionPadi-Signature' => hash_hmac('sha256', $ts.'.'.$body, (string) config('opinionpadi.api_secret')),
            'Accept' => 'application/json',
        ];
    }

    /** Run GET+POST /start and return the created verification session. */
    protected function beginSession(array $claims = []): VerificationSession
    {
        $token = $this->mintHandoff($claims);
        $this->get('/start?t='.urlencode($token))->assertOk();
        $this->post('/start?t='.urlencode($token))->assertRedirect('/consent');

        // Read the id the controller stored — created_at ties make
        // latest() ambiguous when two sessions share a timestamp second.
        return VerificationSession::findOrFail(session('verification_session_id'));
    }

    /** Drive a session from created through intake_done. */
    protected function driveToPhoto(VerificationSession $session): void
    {
        $this->post('/consent', [
            'agree_processing' => 'yes',
            'agree_biometrics' => 'yes',
        ])->assertRedirect('/intake');

        $this->put('/intake', [
            'full_name' => 'Test Volunteer',
            'email' => 'volunteer@example.com',
            'phone_country_code' => '+234',
            'phone' => '801 234 5678',
            'preferred_contact' => 'email',
        ])->assertRedirect('/intake');

        $this->verifyEmail();
    }

    /** Complete the emailed-code step — intake only advances once verified. */
    protected function verifyEmail(): void
    {
        $this->post('/intake/verify', ['code' => session('dev_email_code')])
            ->assertRedirect('/photo');
    }

    /**
     * A real PNG whose bytes are random noise — guaranteed >10KB, valid
     * dimensions for getimagesize(), and mid-range average luminance so
     * the stub provider's lighting check passes. No GD required.
     */
    protected function noiseImage(int $width = 800, int $height = 800, string $mime = 'image/png'): UploadedFile
    {
        $raw = '';
        for ($y = 0; $y < $height; $y++) {
            $raw .= "\x00".random_bytes($width * 3);
        }

        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        $png = "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('N', $width).pack('N', $height).chr(8).chr(2).chr(0).chr(0).chr(0))
            .$chunk('IDAT', gzcompress($raw, 1))
            .$chunk('IEND', '');

        $path = tempnam(sys_get_temp_dir(), 'vximg').'.png';
        file_put_contents($path, $png);

        return new UploadedFile($path, 'face.png', $mime, null, true);
    }
}
