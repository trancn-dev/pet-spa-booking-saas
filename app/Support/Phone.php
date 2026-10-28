<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use Stringable;

/**
 * A Vietnamese phone number, stored as E.164 (`+84901234567`). Customers are keyed by it
 * (db-schema 2.3); the Site OTP and Zalo/SMS reuse the same normalisation.
 *
 * Accepts `0901 234 567`, `090-123-4567`, `84901234567`, `+84 901 234 567`, `(024) 3826 1234`.
 * Mobile numbers have 9 digits after the country code (3x, 5x, 7x, 8x, 9x); landlines have 10 (2x).
 */
final class Phone implements Stringable
{
    public const INVALID = 'Số điện thoại không hợp lệ (vd 0901 234 567).';

    private function __construct(private readonly string $national) {}

    public static function tryParse(?string $input): ?self
    {
        if ($input === null) {
            return null;
        }

        $raw = trim($input);
        if ($raw === '' || preg_match('/[^0-9+\s().\-]/', $raw) === 1) {
            return null;
        }

        $digits = (string) preg_replace('/\D/', '', $raw);
        if (str_starts_with($raw, '+') || (str_starts_with($digits, '84') && strlen($digits) >= 11)) {
            if (! str_starts_with($digits, '84')) {
                return null; // another country
            }
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        } else {
            return null;
        }

        $valid = preg_match('/^[35789]\d{8}$/', $digits) === 1 || preg_match('/^2\d{9}$/', $digits) === 1;

        return $valid ? new self($digits) : null;
    }

    public static function parse(string $input): self
    {
        return self::tryParse($input) ?? throw new InvalidArgumentException(self::INVALID);
    }

    public static function isValid(?string $input): bool
    {
        return self::tryParse($input) !== null;
    }

    /** `+84901234567`: what the database stores. */
    public function e164(): string
    {
        return '+84'.$this->national;
    }

    /** `0901 234 567` (mobile) / `024 3826 1234` (landline): what people read. */
    public function display(): string
    {
        $n = '0'.$this->national;

        return strlen($n) === 10
            ? substr($n, 0, 4).' '.substr($n, 4, 3).' '.substr($n, 7)
            : substr($n, 0, 3).' '.substr($n, 3, 4).' '.substr($n, 7);
    }

    /** Last four digits, as in `customers.phone_suffix`. */
    public function suffix(): string
    {
        return substr($this->national, -4);
    }

    /** Formats a stored E.164 value for display; anything unparsable is returned unchanged. */
    public static function format(?string $e164): ?string
    {
        return $e164 === null ? null : (self::tryParse($e164)?->display() ?? $e164);
    }

    public function equals(self $other): bool
    {
        return $this->national === $other->national;
    }

    public function __toString(): string
    {
        return $this->e164();
    }
}
