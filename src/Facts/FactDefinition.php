<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Facts;

use ByJesper\DecisionSupport\Enums\FactType;

/**
 * A single named fact the editor can branch on. `outcomes` enumerates the
 * allowed values for {@see FactType::Enum} facts so the structured builder can
 * offer them as choices.
 */
final readonly class FactDefinition
{
    /** @param list<string> $outcomes */
    public function __construct(
        public string $name,
        public FactType $type,
        public array $outcomes = [],
        public ?string $label = null,
    ) {}
}
