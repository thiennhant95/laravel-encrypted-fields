<?php

namespace Thiennhant95\LaravelEncryptedFields;

use InvalidArgumentException;
use Thiennhant95\LaravelEncryptedFields\Crypto\BlindIndexer;
use Thiennhant95\LaravelEncryptedFields\Crypto\Cipher;

final class EncryptedFieldsManager
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private Cipher $cipher,
        private BlindIndexer $blindIndexer,
        private array $config
    ) {
    }

    public function cipher(): Cipher
    {
        return $this->cipher;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function fieldsFor(string $model): array
    {
        $models = $this->models();

        if (isset($models[$model]) && is_array($models[$model])) {
            return $models[$model];
        }

        foreach ($models as $class => $fields) {
            if (is_string($class) && is_a($model, $class, true) && is_array($fields)) {
                return $fields;
            }
        }

        return [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function models(): array
    {
        $models = $this->config['models'] ?? [];

        return is_array($models) ? $models : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function fieldConfig(string $model, string $field): array
    {
        $fields = $this->fieldsFor($model);

        return isset($fields[$field]) && is_array($fields[$field]) ? $fields[$field] : [];
    }

    public function indexColumn(string $model, string $field): ?string
    {
        $config = $this->fieldConfig($model, $field);
        $index = $config['index'] ?? null;

        return is_string($index) && '' !== $index ? $index : null;
    }

    public function normalizeMode(string $model, string $field): string
    {
        $mode = $this->fieldConfig($model, $field)['normalize'] ?? 'lower';

        return is_string($mode) && '' !== $mode ? $mode : 'lower';
    }

    public function blindIndex(string $value, string $model, string $field): string
    {
        return $this->blindIndexer->make($value, $field, $this->normalizeMode($model, $field));
    }

    public function encrypt(string $value): string
    {
        return $this->cipher->encrypt($value);
    }

    public function decrypt(string $value): string
    {
        return $this->cipher->decrypt($value);
    }

    public function reencrypt(string $value): string
    {
        return $this->cipher->reencrypt($value);
    }

    public function isEncrypted(?string $value): bool
    {
        return $this->cipher->isEncrypted($value);
    }

    public function keyIdOf(string $value): ?string
    {
        return $this->cipher->keyId($value);
    }

    public function currentKeyId(): string
    {
        return $this->cipher->keyIdForCurrent();
    }

    public function keyIds(): array
    {
        return $this->cipher->keyIds();
    }

    public function isConfigured(): bool
    {
        return '' !== trim((string) ($this->config['key'] ?? ''))
            || array() !== (is_array($this->config['keys'] ?? null) ? $this->config['keys'] : []);
    }

    public function assertIndexColumn(string $model, string $field): string
    {
        $index = $this->indexColumn($model, $field);

        if (null === $index) {
            throw new InvalidArgumentException(sprintf('Field "%s" on %s has no blind-index column configured.', $field, $model));
        }

        return $index;
    }
}
