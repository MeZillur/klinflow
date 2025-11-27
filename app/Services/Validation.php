<?php
declare(strict_types=1);

namespace App\Services;

final class Validation
{
    private array $errors = [];

    public function errors(): array { return $this->errors; }
    public function fails(): bool    { return !empty($this->errors); }

    private function add(string $msg): void
    {
        if ($msg !== '') $this->errors[] = $msg;
    }

    /** Required non-empty (after trim) */
    public function required($value, string $message): self
    {
        if (is_array($value)) {
            if (count($value) === 0) $this->add($message);
        } else {
            if (trim((string)$value) === '') $this->add($message);
        }
        return $this;
    }

    /** Valid email (simple filter) */
    public function email(?string $value, string $message): self
    {
        $v = trim((string)$value);
        if ($v === '' || !filter_var($v, FILTER_VALIDATE_EMAIL)) $this->add($message);
        return $this;
    }

    /** Value in whitelist */
    public function in($value, array $allowed, string $message): self
    {
        if (!in_array($value, $allowed, true)) $this->add($message);
        return $this;
    }

    /** Equality */
    public function equals($a, $b, string $message): self
    {
        if ($a !== $b) $this->add($message);
        return $this;
    }

    /** Minimum string length */
    public function minLen(?string $value, int $min, string $message): self
    {
        if (mb_strlen((string)$value) < $min) $this->add($message);
        return $this;
    }

    /** Maximum string length */
    public function maxLen(?string $value, int $max, string $message): self
    {
        if (mb_strlen((string)$value) > $max) $this->add($message);
        return $this;
    }

    /**
     * Convenience: between min & max length (inclusive).
     * Matches your controller call: $v->length($val, 3, 64, 'message')
     */
    public function length(?string $value, int $min, int $max, string $message): self
    {
        $len = mb_strlen((string)$value);
        if ($len < $min || $len > $max) $this->add($message);
        return $this;
    }

    /** Regex match (true if matches, otherwise error) */
    public function regex(?string $value, string $pattern, string $message): self
    {
        if (@preg_match($pattern, (string)$value) !== 1) $this->add($message);
        return $this;
    }

    /** Only digits with length between min & max (inclusive) */
    public function digitsBetween(?string $value, int $min, int $max, string $message): self
    {
        $v = preg_replace('/\D+/', '', (string)$value);
        $len = mb_strlen($v);
        if ($v === '' || $len < $min || $len > $max) $this->add($message);
        return $this;
    }
}