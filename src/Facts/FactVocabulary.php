<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Facts;

use ByJesper\DecisionSupport\Contracts\FactProvider;

/**
 * The set of facts a {@see FactProvider}
 * exposes to the editor. Drives the structured condition builder and the
 * publish-time fact-reference validation.
 */
final readonly class FactVocabulary
{
    /** @var array<string, FactDefinition> */
    public array $facts;

    /** @param list<FactDefinition> $facts */
    public function __construct(array $facts)
    {
        $keyed = [];
        foreach ($facts as $fact) {
            $keyed[$fact->name] = $fact;
        }

        $this->facts = $keyed;
    }

    public function get(string $name): ?FactDefinition
    {
        return $this->facts[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->facts[$name]);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->facts);
    }
}
