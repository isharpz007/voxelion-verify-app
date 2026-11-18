<?php

namespace App\Services;

use App\Mail\EmailVerificationCode;
use App\Models\Otp;
use App\Models\Participant;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Issues and verifies one-time passwords for contact verification.
 *
 * Security properties:
 *  - 6-digit codes from a CSPRNG, HMAC-hashed at rest, never logged or returned
 *  - 5-minute expiry, max 5 attempts per code, constant-time comparison
 *  - 60-second resend cooldown, max 5 sends per participant per 15 minutes
 *  - issuing a new code invalidates all previous active ones
 *  - verification runs inside a row-locked transaction so a code cannot be
 *    consumed twice by concurrent requests
 */
class OtpService
{
    public const TTL_MINUTES = 5;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_SENDS_PER_WINDOW = 5;

    public const SEND_WINDOW_MINUTES = 15;

    public const PURPOSE_CONTACT = 'contact_verification';

    public function __construct(private SmsSender $sms) {}

    /**
     * Issue a new OTP over the given channel and deliver it.
     *
     * @return array{channel: string, expires_at: \Carbon\Carbon}
     *
     * @throws OtpRateLimitException cooldown or send-window cap hit
     * @throws OtpDeliveryException the provider rejected the message
     * @throws \InvalidArgumentException unsupported channel / missing destination
     */
    public function issue(Participant $participant, string $channel, string $purpose = self::PURPOSE_CONTACT): array
    {
        $destination = $this->destination($participant, $channel);
        abort_unless($destination !== null, 422, 'No usable '.$channel.' destination on file.');

        $latest = Otp::where('participant_id', $participant->id)->where('purpose', $purpose)->latest()->first();

        if ($latest && $latest->created_at->diffInSeconds(now()) < self::RESEND_COOLDOWN_SECONDS) {
            throw new OtpRateLimitException('Please wait a moment before requesting another code.');
        }

        $sentRecently = Otp::where('participant_id', $participant->id)
            ->where('purpose', $purpose)
            ->where('created_at', '>=', now()->subMinutes(self::SEND_WINDOW_MINUTES))
            ->count();

        if ($sentRecently >= self::MAX_SENDS_PER_WINDOW) {
            throw new OtpRateLimitException('Too many verification codes requested. Please try again later.');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $otp = DB::transaction(function () use ($participant, $channel, $purpose, $code) {
            // One active OTP at a time — a new issue kills every earlier code.
            Otp::where('participant_id', $participant->id)
                ->where('purpose', $purpose)
                ->whereNull('verified_at')
                ->whereNull('invalidated_at')
                ->update(['invalidated_at' => now()]);

            return Otp::create([
                'participant_id' => $participant->id,
                'channel' => $channel,
                'purpose' => $purpose,
                'code_hash' => $this->hashCode($code),
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            ]);
        });

        try {
            $this->deliver($channel, $destination, $code);
        } catch (\Throwable $e) {
            // Never claim success when the provider rejected the message —
            // the issued code is dead so it can't linger half-delivered.
            $otp->update(['invalidated_at' => now()]);
            throw new OtpDeliveryException('We could not deliver the verification code. Please try another method or try again.', previous: $e);
        }

        return ['channel' => $channel, 'expires_at' => $otp->expires_at];
    }

    /**
     * Verify a submitted code against the participant's active OTP.
     *
     * @return string the channel that was verified
     *
     * @throws OtpVerificationException with a user-safe reason
     */
    public function verify(Participant $participant, string $code, string $purpose = self::PURPOSE_CONTACT): string
    {
        // Errors are returned (not thrown) inside the transaction so the
        // attempt counter still commits — a thrown exception would roll
        // the increment back and make the 5-attempt cap toothless.
        [$channel, $error] = DB::transaction(function () use ($participant, $code, $purpose) {
            $otp = Otp::where('participant_id', $participant->id)
                ->where('purpose', $purpose)
                ->whereNull('invalidated_at')
                ->latest()
                ->lockForUpdate()
                ->first();

            if (! $otp || $otp->verified_at !== null) {
                return [null, 'No active verification code — request a new one.'];
            }
            if ($otp->expires_at->isPast()) {
                $otp->update(['invalidated_at' => now()]);

                return [null, 'That code has expired — request a new one.'];
            }
            if ($otp->attempts >= self::MAX_ATTEMPTS) {
                $otp->update(['invalidated_at' => now()]);

                return [null, 'Too many incorrect attempts — request a new code.'];
            }

            if (! hash_equals($otp->code_hash, $this->hashCode($code))) {
                $otp->increment('attempts');
                $otp->refresh();

                if ($otp->attempts >= self::MAX_ATTEMPTS) {
                    $otp->update(['invalidated_at' => now()]);

                    return [null, 'Too many incorrect attempts — request a new code.'];
                }

                return [null, 'Incorrect code — '.(self::MAX_ATTEMPTS - $otp->attempts).' attempts remaining.'];
            }

            $otp->update(['verified_at' => now()]);

            return [$otp->channel, null];
        });

        if ($error !== null) {
            throw new OtpVerificationException($error);
        }

        return $channel;
    }

    /**
     * Public status for the verify screen — never leaks the code itself.
     *
     * @return array{active: bool, channel: string|null, expires_in: int, cooldown_remaining: int}
     */
    public function status(Participant $participant, string $purpose = self::PURPOSE_CONTACT): array
    {
        $latest = Otp::where('participant_id', $participant->id)->where('purpose', $purpose)->latest()->first();

        $active = $latest
            && $latest->verified_at === null
            && $latest->invalidated_at === null
            && $latest->expires_at->isFuture();

        return [
            'active' => (bool) $active,
            'channel' => $active ? $latest->channel : null,
            'expires_in' => $active ? max(0, (int) now()->diffInSeconds($latest->expires_at, false)) : 0,
            'cooldown_remaining' => $latest ? max(0, self::RESEND_COOLDOWN_SECONDS - (int) $latest->created_at->diffInSeconds(now())) : 0,
        ];
    }

    /** Masked destination for display, e.g. o***@gmail.com / +234***1234. */
    public function maskedDestination(Participant $participant, string $channel): ?string
    {
        $destination = $this->destination($participant, $channel);

        if ($destination === null) {
            return null;
        }

        if ($channel === 'email') {
            [$local, $domain] = explode('@', $destination, 2);

            return substr($local, 0, 1).'***@'.$domain;
        }

        return substr($destination, 0, 4).'***'.substr($destination, -4);
    }

    public function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function destination(Participant $participant, string $channel): ?string
    {
        return match ($channel) {
            'email' => $participant->email,
            'sms' => $participant->phone_country_code && $participant->phone
                ? $this->normalizeE164($participant->phone_country_code, $participant->phone)
                : null,
            default => null,
        };
    }

    private function normalizeE164(string $countryCode, string $national): string
    {
        return '+'.ltrim(preg_replace('/\D+/', '', $countryCode.$national), '0+');
    }

    private function deliver(string $channel, string $destination, string $code): void
    {
        match ($channel) {
            'email' => Mail::to($destination)->send(new EmailVerificationCode($code)),
            'sms' => $this->sms->send($destination, "Your Voxelion verification code is {$code}. It expires in ".self::TTL_MINUTES.' minutes.'),
            default => throw new \InvalidArgumentException("Unknown OTP channel: {$channel}"),
        };
    }
}
