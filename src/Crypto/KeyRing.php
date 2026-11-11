<?php

namespace Thiennhant95\LaravelEncryptedFields\Crypto;

use InvalidArgumentException;

final class KeyRing
{
    /** @var array<string, string> */
    private array $keys = [];

    private string $currentId;

    /**
     * @param array<string, string> $keys
     */
    public function __construct(?string $key, array $keys = [], string $currentId = 'main')
    {
        $this->currentId = self::validateId($currentId);

        if (is_string($key) && '' !== trim($key)) {
            $this->keys[$this->currentId] = self::decodeKey($key);
        }

        foreach ($keys as $id => $value) {
            if (!is_string($id) || !is_string($value) || '' === trim($value)) {
                continue;
            }

            $this->keys[self::validateId($id)] = self::decodeKey($value);
        }
    }

    public function currentId(): string
    {
        return $this->currentId;
    }

    public function has(string $id): bool
    {
        return isset($this->keys[$id]);
    }

    public function keyFor(string $id): string
    {
        if (!isset($this->keys[$id])) {
            throw new InvalidArgumentException(sprintf('No encryption key configured for key id "%s".', $id));
        }

        return $this->keys[$id];
    }

    public function currentKey(): string
    {
        return $this->keyFor($this->currentId);
    }

    /**
     * @return array<int, string>
     */
    public function ids(): array
    {
        return array_keys($this->keys);
    }

    private static function validateId(string $id): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id)) {
            throw new InvalidArgumentException('Encryption key ids may only contain letters, numbers, dashes and underscores.');
        }

        return $id;
    }

    private static function decodeKey(string $value): string
    {
        $value = trim($value);

        if (str_starts_with($value, 'base64:')) {
            $decoded = base64_decode(substr($value, 7), true);
            if (false !== $decoded && 32 === strlen($decoded)) {
                return $decoded;
            }

            throw new InvalidArgumentException('ENCRYPTED_FIELDS key is not valid base64.');
        }

        if (32 === strlen($value)) {
            return $value;
        }

        if (1 === preg_match('/^[0-9a-fA-F]{64}$/', $value)) {
            $decoded = hex2bin($value);
            if (false !== $decoded && 32 === strlen($decoded)) {
                return $decoded;
            }
        }

        $decoded = base64_decode($value, true);
        if (false !== $decoded && 32 === strlen($decoded)) {
            return $decoded;
        }

        throw new InvalidArgumentException('Encrypted Fields keys must be 32 bytes (base64, base64:... or 64 hex characters).');
    }
}
