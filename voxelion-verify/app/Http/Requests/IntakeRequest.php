<?php

namespace App\Http\Requests;

use App\Models\Participant;
use App\Services\EmailNormalizer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class IntakeRequest extends FormRequest
{
    /**
     * Allowed national number lengths per supported calling code
     * (digits only, incl. trunk prefix): NG 10–11 · US/CA 10 ·
     * UK 10–11 · KE 9–10 · ZA 9–10. Fallback covers any other valid code.
     */
    private const PHONE_LENGTHS = [
        '+234' => [10, 11],
        '+1' => [10, 10],
        '+44' => [10, 11],
        '+254' => [9, 10],
        '+27' => [9, 10],
    ];

    /**
     * Canonicalise the email before any rule sees it — trim, lowercase,
     * IDN domain → punycode. Format rules and uniqueness then operate on
     * one representation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge(['email' => EmailNormalizer::normalize((string) $this->input('email'))]);
        }
    }

    public function rules(): array
    {
        return [
            // Full legal name: at least a first and last name; letters only
            // (accents, hyphens and apostrophes allowed — no digits/symbols).
            'full_name' => [
                'required',
                'string',
                'min:2',
                'max:120',
                "regex:/^[\\p{L}]+(?:[-'’][\\p{L}]+)*(?:\\s+[\\p{L}]+(?:[-'’][\\p{L}]+)*)+$/u",
            ],
            'email' => [
                'required',
                'email:rfc',
                'max:160',
                // Require a real domain with a TLD — `email:rfc` alone
                // accepts addresses like `v@example`. No provider allow-list:
                // any standards-valid address (personal or business) passes.
                'regex:/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/',
                $this->emailDomainRule(),
                $this->emailUniquenessRule(),
            ],
            'phone_country_code' => ['required', 'string', 'in:+234,+1,+44,+254,+27'],
            'phone' => [
                'required',
                'string',
                'min:6',
                'max:20',
                'regex:/^[\d\s\-()]+$/',
                $this->phoneLengthRule(),
            ],
            'preferred_contact' => ['nullable', 'in:email,whatsapp,phone'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.regex' => 'Enter your full legal name first and last name, letters only.',
            'email.regex' => 'Enter a valid email address.',
        ];
    }

    /**
     * The domain must actually resolve — MX record first, then A/AAAA as
     * fallback (domains that host a website but outsource mail still pass).
     * Catches typoed/fake domains like `gmdd.comm` that pass format checks.
     * Disabled in tests via EMAIL_DNS_CHECK for deterministic offline runs.
     */
    private function emailDomainRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! config('verification.email_dns_check')) {
                return;
            }

            $domain = substr((string) strrchr((string) $value, '@'), 1);

            if ($domain !== ''
                && ! checkdnsrr($domain, 'MX')
                && ! checkdnsrr($domain, 'A')
                && ! checkdnsrr($domain, 'AAAA')
            ) {
                $fail('This email domain does not appear to exist check the spelling.');
            }
        };
    }

    /**
     * Case-insensitive uniqueness (emails are normalised to lowercase):
     * one address may not be tied to two different volunteer identities.
     * The same volunteer may reuse their own address across tasks.
     */
    private function emailUniquenessRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $ref = $this->attributes->get('verificationSession')?->participant?->op_volunteer_ref;

            $taken = Participant::where('email', (string) $value)
                ->when($ref, fn ($query) => $query->where('op_volunteer_ref', '!=', $ref))
                ->exists();

            if ($taken) {
                $fail('This email address is already registered to a different volunteer.');
            }
        };
    }

    private function phoneLengthRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $digits = (string) preg_replace('/\D/', '', (string) $value);
            [$min, $max] = self::PHONE_LENGTHS[$this->input('phone_country_code')] ?? [6, 15];
            $len = strlen($digits);

            if ($len < $min || $len > $max) {
                $range = $min === $max ? (string) $min : "between {$min} and {$max}";
                $fail("The phone number must be {$range} digits for the selected country code.");
            }
        };
    }
}
