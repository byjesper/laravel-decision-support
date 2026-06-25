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
    /**
     * @param  array<string, string>  $labelI18n  per-locale display labels for the diagram
     */
    public function __construct(
        public string $from,
        public string $fromPort,
        public string $to,
        public ?Condition $condition = null,
        public ?string $label = null,
        public array $labelI18n = [],
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
            'label' => $this->label,
            'labelI18n' => $this->labelI18n,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $condition = is_array($data['condition'] ?? null) ? Condition::fromArray($data['condition']) : null;

        /** @var array<string, string> $labelI18n */
        $labelI18n = is_array($data['labelI18n'] ?? null)
            ? array_filter($data['labelI18n'], is_string(...))
            : [];

        return new self(
            from: is_string($data['from'] ?? null) ? $data['from'] : '',
            fromPort: is_string($data['fromPort'] ?? null) ? $data['fromPort'] : 'out',
            to: is_string($data['to'] ?? null) ? $data['to'] : '',
            condition: $condition,
            label: is_string($data['label'] ?? null) ? $data['label'] : null,
            labelI18n: $labelI18n,
        );
    }
}
