<?php

namespace Thiennhant95\LaravelEncryptedFields\Console;

use Illuminate\Database\Eloquent\Model;
use Throwable;
use Thiennhant95\LaravelEncryptedFields\EncryptedFieldsManager;

final class RotateKeysCommand extends EncryptedCommand
{
    protected $signature = 'encrypted-fields:rotate
        {--model= : Rotate only this model class}
        {--chunk=500 : Rows per chunk}
        {--dry-run : Count encrypted fields without writing}';

    protected $description = 'Re-encrypt configured fields with the current key';

    public function handle(EncryptedFieldsManager $manager): int
    {
        $currentId = $manager->currentKeyId();
        $keyIds = $manager->keyIds();

        if (!in_array($currentId, $keyIds, true)) {
            $this->error(sprintf('The current key id "%s" is not configured.', $currentId));

            return self::FAILURE;
        }

        $models = $this->configuredModels($manager, $this->option('model'));

        if (array() === $models) {
            $this->error('No encrypted models are configured in config/encrypted-fields.php.');

            return self::FAILURE;
        }

        $this->line(sprintf('Current key: %s (available: %s)', $currentId, implode(', ', $keyIds)));
        $this->warn('Keep all old keys in encrypted-fields.keys until this command succeeds.');

        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));
        $totalFields = 0;
        $totalChanged = 0;
        $totalErrors = 0;

        foreach ($models as $model => $fields) {
            if (!is_string($model) || !class_exists($model) || !is_subclass_of($model, Model::class) || !is_array($fields)) {
                $this->warn(sprintf('Skipping invalid Eloquent model: %s', is_string($model) ? $model : gettype($model)));
                continue;
            }

            if (array() === $fields) {
                continue;
            }

            $this->line($model);
            $fieldCount = 0;
            $changed = 0;
            $errors = 0;

            $model::query()->chunkById($chunk, function ($records) use (
                $manager,
                $fields,
                $dryRun,
                &$fieldCount,
                &$changed,
                &$errors
            ): void {
                foreach ($records as $record) {
                    $dirty = false;

                    foreach ($fields as $field => $options) {
                        $field = (string) $field;
                        $raw = $record->getRawOriginal($field);

                        if (!$manager->isEncrypted(is_string($raw) ? $raw : null)) {
                            continue;
                        }

                        ++$fieldCount;

                        if ($dryRun) {
                            continue;
                        }

                        try {
                            $record->setAttribute($field, $manager->decrypt((string) $raw));
                            $dirty = true;
                        } catch (Throwable $exception) {
                            ++$errors;
                            $this->warn(sprintf('Row %s field %s: %s', (string) $record->getKey(), $field, $exception->getMessage()));
                        }
                    }

                    if ($dryRun || !$dirty) {
                        continue;
                    }

                    try {
                        $record->syncEncryptedIndexes();
                        $record->saveQuietly();
                        ++$changed;
                    } catch (Throwable $exception) {
                        ++$errors;
                        $this->warn(sprintf('Row %s: %s', (string) $record->getKey(), $exception->getMessage()));
                    }
                }
            });

            $totalFields += $fieldCount;
            $totalChanged += $changed;
            $totalErrors += $errors;

            $this->line(sprintf('  fields=%d changed=%d errors=%d', $fieldCount, $changed, $errors));
        }

        $this->info(sprintf(
            '%s: %d encrypted fields, %d rows changed, %d errors.',
            $dryRun ? 'Dry run' : 'Rotation',
            $totalFields,
            $totalChanged,
            $totalErrors
        ));

        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
