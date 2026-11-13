<?php

namespace Thiennhant95\LaravelEncryptedFields\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Thiennhant95\LaravelEncryptedFields\Casts\EncryptedString;
use Thiennhant95\LaravelEncryptedFields\EncryptedFieldsManager;

trait HasEncryptedFields
{
    public function initializeHasEncryptedFields(): void
    {
        $manager = app(EncryptedFieldsManager::class);
        $casts = array();

        foreach (array_keys($manager->fieldsFor(static::class)) as $field) {
            $casts[$field] = EncryptedString::class;
        }

        if (array() !== $casts) {
            $this->mergeCasts($casts);
        }
    }

    public static function bootHasEncryptedFields(): void
    {
        static::saving(function (Model $model): void {
            if (method_exists($model, 'syncEncryptedIndexes')) {
                $model->syncEncryptedIndexes();
            }
        });
    }

    public function syncEncryptedIndexes(): void
    {
        $manager = app(EncryptedFieldsManager::class);

        foreach ($manager->fieldsFor(static::class) as $field => $options) {
            $index = $manager->indexColumn(static::class, (string) $field);

            if (null === $index) {
                continue;
            }

            $value = $this->getAttribute((string) $field);

            if (null === $value || '' === $value) {
                $this->setAttribute($index, null);
                continue;
            }

            if (!is_scalar($value)) {
                throw new \InvalidArgumentException(sprintf('Encrypted index field "%s" must be a scalar value.', $field));
            }

            $this->setAttribute($index, $manager->blindIndex((string) $value, static::class, (string) $field));
        }
    }

    public function scopeWhereEncrypted(Builder $query, string $field, mixed $value): Builder
    {
        $index = $this->encryptedIndexColumn($field);

        if (null === $value || '' === $value) {
            return $query->whereNull($index);
        }

        return $query->where(
            $index,
            app(EncryptedFieldsManager::class)->blindIndex((string) $value, static::class, $field)
        );
    }

    /**
     * @param array<int, mixed> $values
     */
    public function scopeWhereEncryptedIn(Builder $query, string $field, array $values): Builder
    {
        $index = $this->encryptedIndexColumn($field);
        $manager = app(EncryptedFieldsManager::class);
        $hashes = array();

        foreach ($values as $value) {
            if (null === $value || '' === $value) {
                continue;
            }

            $hashes[] = $manager->blindIndex((string) $value, static::class, $field);
        }

        return $query->whereIn($index, $hashes);
    }

    public function scopeOrWhereEncrypted(Builder $query, string $field, mixed $value): Builder
    {
        $index = $this->encryptedIndexColumn($field);

        if (null === $value || '' === $value) {
            return $query->orWhereNull($index);
        }

        return $query->orWhere(
            $index,
            app(EncryptedFieldsManager::class)->blindIndex((string) $value, static::class, $field)
        );
    }

    /**
     * Order results by the blind index of an encrypted field.
     *
     * The blind index is a deterministic HMAC: rows that share the same
     * plaintext value group together and the ordering is stable across
     * queries, which is enough for grouping and deduplication. It is NOT
     * alphabetical — distinct values follow the HMAC bytes, not the
     * plaintext collation, and partial/LIKE ordering remains unsupported.
     */
    public function scopeOrderByEncrypted(Builder $query, string $field, string $direction = 'asc'): Builder
    {
        $index = $this->encryptedIndexColumn($field);

        return $query->orderBy($index, 'desc' === strtolower($direction) ? 'desc' : 'asc');
    }

    private function encryptedIndexColumn(string $field): string
    {
        return app(EncryptedFieldsManager::class)->assertIndexColumn(static::class, $field);
    }
}
