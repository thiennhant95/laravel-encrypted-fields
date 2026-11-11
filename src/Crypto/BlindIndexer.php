<?php

namespace Thiennhant95\LaravelEncryptedFields\Crypto;

use InvalidArgumentException;

final class BlindIndexer
{
    public function __construct(private string $secret)
    {
    }

    public function make(string $value, string $field, string $normalize = 'lower'): string
    {
        if ('' === trim($this->secret)) {
            throw new InvalidArgumentException('ENCRYPTED_FIELDS_TOKEN_SECRET is required for searchable encrypted fields.');
        }

        return hash_hmac('sha256', $field . "\0" . $this->normalize($value, $normalize), $this->secret);
    }

    public function normalize(string $value, string $mode = 'lower'): string
    {
        $value = trim($value);

        if ('phone' === $mode) {
            return preg_replace('/[^0-9+]/', '', $value) ?? '';
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $value = $normalized;
            }
        }

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}
