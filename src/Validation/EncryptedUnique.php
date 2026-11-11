<?php

namespace Thiennhant95\LaravelEncryptedFields\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use LogicException;
use Thiennhant95\LaravelEncryptedFields\EncryptedFieldsManager;

final class EncryptedUnique implements ValidationRule
{
    public function __construct(
        private string $model,
        private string $field,
        private mixed $ignoreKey = null,
        private ?string $message = null
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value)) {
            $fail($this->message ?? sprintf('The %s must be a scalar value.', $attribute));
            return;
        }

        $manager = app(EncryptedFieldsManager::class);
        $index = $manager->indexColumn($this->model, $this->field);

        if (null === $index) {
            throw new LogicException(sprintf('Field "%s" on %s has no blind-index column configured.', $this->field, $this->model));
        }

        $query = $this->model::query()->where(
            $index,
            $manager->blindIndex((string) $value, $this->model, $this->field)
        );

        if (null !== $this->ignoreKey) {
            $query->where($query->getModel()->getQualifiedKeyName(), '!=', $this->ignoreKey);
        }

        if ($query->exists()) {
            $fail(str_replace(':attribute', $attribute, $this->message ?? ':attribute has already been taken.'));
        }
    }
}
