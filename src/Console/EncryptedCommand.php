<?php

namespace Thiennhant95\LaravelEncryptedFields\Console;

use Illuminate\Console\Command;
use Thiennhant95\LaravelEncryptedFields\EncryptedFieldsManager;

abstract class EncryptedCommand extends Command
{
    /**
     * @return array<string, array<string, mixed>>
     */
    protected function configuredModels(EncryptedFieldsManager $manager, ?string $filter): array
    {
        $models = $manager->models();

        if (null === $filter || '' === $filter) {
            return $models;
        }

        $filter = ltrim($filter, '\\');
        $filtered = array();

        foreach ($models as $model => $fields) {
            if (ltrim((string) $model, '\\') === $filter) {
                $filtered[$model] = $fields;
            }
        }

        return $filtered;
    }
}
