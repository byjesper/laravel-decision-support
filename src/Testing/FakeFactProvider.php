<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Testing;

use ByJesper\DecisionSupport\Contracts\FactProvider;
use ByJesper\DecisionSupport\Enums\FactType;
use ByJesper\DecisionSupport\Facts\FactDefinition;
use ByJesper\DecisionSupport\Facts\FactValue;
use ByJesper\DecisionSupport\Facts\FactVocabulary;
use ByJesper\DecisionSupport\Facts\PendingInteraction;
use ByJesper\DecisionSupport\Runtime\GuideContext;
use ByJesper\DecisionSupport\Runtime\Interaction;

/**
 * An in-memory provider for tests and for code-authoring consumers (Spring)
 * that want to exercise a guide offline. Stack resolved values with `with()`
 * and suspension points with `pending()`.
 */
final class FakeFactProvider implements FactProvider
{
    /** @var array<string, mixed> */
    private array $values = [];

    /** @var array<string, Interaction> */
    private array $pending = [];

    /** @var list<FactDefinition> */
    private array $definitions = [];

    public static function make(): self
    {
        return new self;
    }

    /**
     * Register a resolved fact (and add it to the vocabulary). The type defaults
     * to a sensible guess from the value when not given.
     */
    public function with(string $name, mixed $value, ?FactType $type = null, ?string $label = null): self
    {
        $this->values[$name] = $value;
        $this->definitions[] = new FactDefinition($name, $type ?? $this->inferType($value), label: $label);

        return $this;
    }

    /** Register a fact that suspends the run for host input the first time it is resolved. */
    public function pending(string $name, Interaction $interaction, ?FactType $type = null): self
    {
        $this->pending[$name] = $interaction;
        $this->definitions[] = new FactDefinition($name, $type ?? FactType::Text);

        return $this;
    }

    /** Declare a fact in the vocabulary without giving it a value (stays unresolved at run time). */
    public function declare(string $name, FactType $type, string ...$outcomes): self
    {
        $this->definitions[] = new FactDefinition($name, $type, array_values($outcomes));

        return $this;
    }

    #[\Override]
    public function vocabulary(): FactVocabulary
    {
        return new FactVocabulary($this->definitions);
    }

    #[\Override]
    public function resolve(string $fact, GuideContext $context): FactValue|PendingInteraction
    {
        if (isset($this->pending[$fact])) {
            return new PendingInteraction($this->pending[$fact]);
        }

        return new FactValue($this->values[$fact] ?? null);
    }

    private function inferType(mixed $value): FactType
    {
        return match (true) {
            is_bool($value) => FactType::Boolean,
            is_int($value), is_float($value) => FactType::Number,
            default => FactType::Text,
        };
    }
}
