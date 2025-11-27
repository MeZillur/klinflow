<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Auth service (helpers only)
 * ---------------------------
 * - Password hashing / verify / rehash
 * - Identifier helpers (email / username / mobile)
 * - Normalizers for email and mobile numbers
 */
final class Auth
{
    /** Hash a password with sensible defaults (cost can be tuned via env). */
    public static function hash(string $password): string
    {
        // Use PHP's default algo (allows forward upgrades).
        // Tune cost via BCRYPT_COST (applies only if algo resolves to bcrypt).
        $options = [];
        $cost = (int)(getenv('BCRYPT_COST') ?: 10);
        if ($cost >= 4 && $cost <= 15) $options['cost'] = $cost;

        return password_hash($password, PASSWORD_DEFAULT, $options);
    }

    /** Verify a plain password against a stored hash. */
    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /** Determine if an existing hash should be upgraded (e.g., higher cost/new algo). */
    public static function needsRehash(string $hash): bool
    {
        $options = [];
        $cost = (int)(getenv('BCRYPT_COST') ?: 10);
        if ($cost >= 4 && $cost <= 15) $options['cost'] = $cost;

        return password_needs_rehash($hash, PASSWORD_DEFAULT, $options);
    }

    // ------------------------------------------------------------------
    // Identifiers
    // ------------------------------------------------------------------

    /** Return 'email' | 'mobile' | 'username' based on the input string. */
    public static function detectIdentifier(string $input): string
    {
        $input = trim($input);

        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }

        // If the string has mostly digits and reasonable length → mobile
        $digits = self::normalizeMobile($input);
        if ($digits !== '' && strlen($digits) >= 8 && strlen($digits) <= 15) {
            return 'mobile';
        }

        return 'username';
    }

    /** Very light username validator (3–32, alnum + underscore + dot + dash). */
    public static function usernameIsValid(string $username): bool
    {
        $username = trim($username);
        if ($username === '' || strlen($username) < 3 || strlen($username) > 32) return false;
        return (bool)preg_match('/^[A-Za-z0-9._-]+$/', $username);
    }

    /** Normalize email (lowercase local+domain, trim). */
    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * Normalize mobile: keep digits only (remove spaces, +, -, etc.).
     * You can extend to add country defaults if needed.
     */
    public static function normalizeMobile(string $mobile): string
    {
        return preg_replace('/\D+/', '', trim($mobile)) ?: '';
    }

    // ------------------------------------------------------------------
    // Password strength (optional helper)
    // ------------------------------------------------------------------

    /**
     * Minimal strength check: length >= 8 (optionally enforce more rules).
     * Returns an array: [bool $ok, string|null $message]
     */
    public static function strongEnough(string $password): array
    {
        if (strlen($password) < 8) {
            return [false, 'Password must be at least 8 characters.'];
        }
        // Uncomment to enforce stronger rules:
        // if (!preg_match('/[A-Z]/', $password)) return [false, 'Add at least one uppercase letter.'];
        // if (!preg_match('/[a-z]/', $password)) return [false, 'Add at least one lowercase letter.'];
        // if (!preg_match('/\d/', $password))    return [false, 'Add at least one digit.'];
        // if (!preg_match('/\W/', $password))    return [false, 'Add at least one symbol.'];
        return [true, null];
    }
}