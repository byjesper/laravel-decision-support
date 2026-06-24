<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Conditions;

use ByJesper\DecisionSupport\Enums\ConditionType;
use ByJesper\DecisionSupport\Enums\Operator;

/**
 * A serializable edge guard. Structured conditions reference a named fact via an
 * operator and value; expression conditions carry a symfony/expression-language
 * string. `always` and `unknown` are sentinel branches used for routing.
 */
final readonly class Condition
{
    public function __construct(
        public ConditionType $type,
        public ?string $fact = null,
        public ?Operator $operator = null,
        public mixed $value = null,
        public ?string $expression = null,
    ) {}

    public static function structured(string $fact, Operator $operator, mixed $value = null): self
    {
        return new self(ConditionType::Structured, fact: $fact, operator: $operator, value: $value);
    }

    public static function expression(string $expression): self
    {
        return new self(ConditionType::Expression, expression: $expression);
    }

    public static function always(): self
    {
        return new self(ConditionType::Always);
    }

    public static function unknown(string $fact): self
    {
        return new self(ConditionType::Unknown, fact: $fact);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type->value,
            'fact' => $this->fact,
            'operator' => $this->operator?->value,
            'value' => $this->value,
            'expression' => $this->expression,
        ], static fn (mixed $v): bool => $v !== null);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $type = ConditionType::from(is_string($data['type'] ?? null) ? $data['type'] : 'always');
        $operatorRaw = $data['operator'] ?? null;

        return new self(
            type: $type,
            fact: is_string($data['fact'] ?? null) ? $data['fact'] : null,
            operator: is_string($operatorRaw) ? Operator::from($operatorRaw) : null,
            value: $data['value'] ?? null,
            expression: is_string($data['expression'] ?? null) ? $data['expression'] : null,
        );
    }
}
