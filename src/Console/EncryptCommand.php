<?php

namespace Thiennhant95\LaravelEncryptedFields\Console;

use Illuminate\Database\Eloquent\Model;
use Throwable;
use Thiennhant95\LaravelEncryptedFields\EncryptedFieldsManager;

final class EncryptCommand extends EncryptedCommand
{
    protected $signature = 'encrypted-fields:encrypt
        {--model= : Encrypt only this model class}
        {--chunk=500 : Rows per chunk}
        {--dry-run : Count rows that need encryption without writing}';

    protected $description = 'Encrypt existing plaintext fields and fill blind indexes';

    public function handle(EncryptedFieldsManager $manager): int
    {
        $models = $this->configuredModels($manager, $this->option('model'));

        if (array() === $models) {
            $this->error('No encrypted models are configured in config/encrypted-fields.php.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));
        $totalNeeds = 0;
        $totalChanged = 0;
        $totalErrors = 0;

        foreach ($models as $model => $fields) {
            if (!is_string($model) || !class_exists($model) || !is_subclass_of($model, Model::class)) {
                $this->warn(sprintf('Skipping invalid Eloquent model: %s', is_string($model) ? $model : gettype($model)));
                continue;
            }

            if (!is_array($fields) || array() === $fields) {
                continue;
            }

            $this->line(sprintf('%s (%d fields)', $model, count($fields)));
            $needs = 0;
            $changed = 0;
            $errors = 0;

            $model::query()->chunkById($chunk, function ($records) use (
                $manager,
                $model,
                $fields,
                $dryRun,
                &$needs,
                &$changed,
                &$errors
            ): void {
                foreach ($records as $record) {
                    $rowNeeds = false;

                    foreach ($fields as $field => $options) {
                        $field = (string) $field;
                        $raw = $record->getRawOriginal($field);

                        if (null === $raw || '' === $raw) {
                            continue;
                        }

                        if (!$manager->isEncrypted((string) $raw)) {
                            $rowNeeds = true;

                            if (!$dryRun) {
                                $record->setAttribute($field, (string) $raw);
                            }
                        }

                        $index = $manager->indexColumn($model, $field);
                        if (null !== $index && null === $record->getRawOriginal($index)) {
                            $rowNeeds = true;
                        }
                    }

                    if (!$rowNeeds) {
                        continue;
                    }

                    ++$needs;

                    if ($dryRun) {
                        continue;
                    }

                    try {
                        $record->syncEncryptedIndexes();

                        if ($record->isDirty()) {
                            $record->saveQuietly();
                            ++$changed;
                        }
                    } catch (Throwable $exception) {
                        ++$errors;
                        $this->warn(sprintf('Row %s: %s', (string) $record->getKey(), $exception->getMessage()));
                    }
                }
            });

            $totalNeeds += $needs;
            $totalChanged += $changed;
            $totalErrors += $errors;

            $this->line(sprintf(
                '  %s=%d changed=%d errors=%d',
                $dryRun ? 'would_change' : 'needs_change',
                $needs,
                $changed,
                $errors
            ));
        }

        $this->info(sprintf(
            '%s: %d rows need changes, %d saved, %d errors.',
            $dryRun ? 'Dry run' : 'Encryption',
            $totalNeeds,
            $totalChanged,
            $totalErrors
        ));

        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
