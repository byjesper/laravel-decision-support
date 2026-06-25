<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Validation;

final readonly class ValidationResult
{
    /** @param list<ValidationError> $errors */
    public function __construct(public array $errors = []) {}

    public static function valid(): self
    {
        return new self([]);
    }

    /**
     * @param  array<string, string|int>  $params  values interpolated into `$message`, kept structured for translation
     */
    public static function error(string $code, string $message, ?string $nodeKey = null, array $params = []): self
    {
        return new self([new ValidationError($code, $message, $nodeKey, $params)]);
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return ! $this->passes();
    }

    public function merge(ValidationResult $other): self
    {
        return new self([...$this->errors, ...$other->errors]);
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_map(static fn (ValidationError $e): string => $e->code, $this->errors);
    }

    public function hasCode(string $code): bool
    {
        return in_array($code, $this->codes(), true);
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(static fn (ValidationError $e): array => $e->toArray(), $this->errors);
    }
}
