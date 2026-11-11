<?php

namespace Thiennhant95\LaravelEncryptedFields\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Thiennhant95\LaravelEncryptedFields\EncryptedFieldsManager;

final class EncryptedString implements CastsAttributes
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!is_string($value)) {
            $value = (string) $value;
        }

        return app(EncryptedFieldsManager::class)->decrypt($value);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!is_scalar($value)) {
            throw new InvalidArgumentException(sprintf('Encrypted field "%s" must be a scalar value.', $key));
        }

        $manager = app(EncryptedFieldsManager::class);
        $value = (string) $value;

        return $manager->isEncrypted($value) ? $value : $manager->encrypt($value);
    }
}
