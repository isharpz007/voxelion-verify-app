<?php

namespace App\Services;

/**
 * Canonicalises an email address for storage and comparison: trims,
 * lowercases, and converts an internationalised (IDN) domain to its
 * punycode form so `user@münchen.de` and `user@xn--mnchen-3ya.de` are
 * the same address. Applied before validation, so downstream rules and
 * uniqueness checks always see the canonical form.
 */
class EmailNormalizer
{
    public static function normalize(string $email): string
    {
        $email = mb_strtolower(trim($email));

        $at = strrpos($email, '@');
        if ($at === false) {
            return $email; // malformed — left for the validator to reject
        }

        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);

        if ($domain !== '' && preg_match('/[^\x00-\x7F]/', $domain)) {
            $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($ascii !== false) {
                $domain = $ascii;
            }
        }

        return $local.'@'.$domain;
    }
}
