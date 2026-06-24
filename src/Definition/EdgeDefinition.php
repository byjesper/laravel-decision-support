<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Definition;

use ByJesper\DecisionSupport\Conditions\Condition;
use ByJesper\DecisionSupport\Enums\ConditionType;

/**
 * A directed connection leaving a node through a named port. A null condition
 * is an unconditional (default) edge.
 */
final readonly class EdgeDefinition
{
    public function __construct(
        public string $from,
        public string $fromPort,
        public string $to,
        public ?Condition $condition = null,
    ) {}

    public function isDefault(): bool
    {
        return $this->condition === null
            || $this->condition->type === ConditionType::Always;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'fromPort' => $this->fromPort,
            'to' => $this->to,
            'condition' => $this->condition?->toArray(),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $condition = is_array($data['condition'] ?? null) ? Condition::fromArray($data['condition']) : null;

        return new self(
            from: is_string($data['from'] ?? null) ? $data['from'] : '',
            fromPort: is_string($data['fromPort'] ?? null) ? $data['fromPort'] : 'out',
            to: is_string($data['to'] ?? null) ? $data['to'] : '',
            condition: $condition,
        );
    }
}
