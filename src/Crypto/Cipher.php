<?php

namespace Thiennhant95\LaravelEncryptedFields\Crypto;

use RuntimeException;

final class Cipher
{
    public const PREFIX = 'enc:v1:';

    private const NONCE_BYTES = 12;
    private const TAG_BYTES = 16;

    public function __construct(private KeyRing $keys)
    {
    }

    public function encrypt(string $plaintext): string
    {
        if ($this->isEncrypted($plaintext)) {
            return $plaintext;
        }

        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->keys->currentKey(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_BYTES
        );

        if (false === $ciphertext || self::TAG_BYTES !== strlen($tag)) {
            throw new RuntimeException('Could not encrypt the value.');
        }

        return self::PREFIX
            . $this->keys->currentId()
            . ':'
            . base64_encode($nonce . $tag . $ciphertext);
    }

    public function decrypt(string $value): string
    {
        if (!$this->isEncrypted($value)) {
            return $value;
        }

        [$keyId, $raw] = $this->parse($value);

        $nonce = substr($raw, 0, self::NONCE_BYTES);
        $tag = substr($raw, self::NONCE_BYTES, self::TAG_BYTES);
        $ciphertext = substr($raw, self::NONCE_BYTES + self::TAG_BYTES);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->keys->keyFor($keyId),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if (false === $plaintext) {
            throw new DecryptionException('Could not decrypt the value; the key may be missing or the ciphertext may be corrupted.');
        }

        return $plaintext;
    }

    public function reencrypt(string $value): string
    {
        return $this->encrypt($this->decrypt($value));
    }

    public function isEncrypted(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    public function keyIdForCurrent(): string
    {
        return $this->keys->currentId();
    }

    /**
     * @return array<int, string>
     */
    public function keyIds(): array
    {
        return $this->keys->ids();
    }

    public function keyId(string $value): ?string
    {
        if (!$this->isEncrypted($value)) {
            return null;
        }

        try {
            return $this->parse($value)[0];
        } catch (DecryptionException) {
            return null;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parse(string $value): array
    {
        $body = substr($value, strlen(self::PREFIX));
        $separator = strpos($body, ':');

        if (false === $separator) {
            throw new DecryptionException('Invalid encrypted value format.');
        }

        $keyId = substr($body, 0, $separator);

        if (1 !== preg_match('/^[A-Za-z0-9_-]{1,40}$/', $keyId)) {
            throw new DecryptionException('Invalid key id in encrypted value.');
        }

        $raw = base64_decode(substr($body, $separator + 1), true);

        if (false === $raw || strlen($raw) < self::NONCE_BYTES + self::TAG_BYTES) {
            throw new DecryptionException('Invalid encrypted value payload.');
        }

        return [$keyId, $raw];
    }
}
