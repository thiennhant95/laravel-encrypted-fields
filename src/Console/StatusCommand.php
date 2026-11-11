<?php

namespace Thiennhant95\LaravelEncryptedFields\Console;

use Thiennhant95\LaravelEncryptedFields\EncryptedFieldsManager;

final class StatusCommand extends EncryptedCommand
{
    protected $signature = 'encrypted-fields:status';

    protected $description = 'Show encrypted models, fields, key ids and configuration status';

    public function handle(EncryptedFieldsManager $manager): int
    {
        $keyIds = $manager->keyIds();
        $currentId = $manager->currentKeyId();
        $tokenConfigured = '' !== trim((string) config('encrypted-fields.token_secret'));

        $this->table(array('Setting', 'Value'), array(
            array('current_key_id', $currentId),
            array('current_key_configured', in_array($currentId, $keyIds, true) ? 'yes' : 'no'),
            array('available_key_ids', array() === $keyIds ? '-' : implode(', ', $keyIds)),
            array('token_secret_configured', $tokenConfigured ? 'yes' : 'no'),
        ));

        $rows = array();

        foreach ($manager->models() as $model => $fields) {
            if (!is_array($fields)) {
                continue;
            }

            foreach ($fields as $field => $options) {
                $options = is_array($options) ? $options : array();
                $index = $options['index'] ?? null;

                $rows[] = array(
                    (string) $model,
                    (string) $field,
                    is_string($index) && '' !== $index ? $index : '-',
                    (string) ($options['normalize'] ?? 'lower'),
                );
            }
        }

        if (array() === $rows) {
            $this->warn('No models are configured in config/encrypted-fields.php.');

            return self::SUCCESS;
        }

        $this->table(array('Model', 'Encrypted field', 'Blind index', 'Normalize'), $rows);

        return self::SUCCESS;
    }
}
