<?php

namespace Thiennhant95\LaravelEncryptedFields\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Thiennhant95\LaravelEncryptedFields\EncryptedFieldsManager;

final class EncryptedUserProvider extends EloquentUserProvider
{
    public function __construct(
        Hasher $hasher,
        private string $modelClass,
        private EncryptedFieldsManager $manager
    ) {
        parent::__construct($hasher, $modelClass);
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $fields = $this->manager->fieldsFor($this->modelClass);
        $mapped = array();

        foreach ($fields as $field => $options) {
            if (!isset($credentials[$field]) || !is_array($options)) {
                continue;
            }

            $index = $options['index'] ?? null;

            if (!is_string($index) || '' === $index || !is_scalar($credentials[$field])) {
                continue;
            }

            $mapped[$index] = $this->manager->blindIndex(
                (string) $credentials[$field],
                $this->modelClass,
                (string) $field
            );

            unset($credentials[$field]);
        }

        return parent::retrieveByCredentials(array_merge($credentials, $mapped));
    }
}
