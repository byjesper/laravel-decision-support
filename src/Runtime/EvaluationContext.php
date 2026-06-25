<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Runtime;

use ByJesper\DecisionSupport\Contracts\ConditionEvaluator;
use ByJesper\DecisionSupport\Contracts\FactProvider;
use ByJesper\DecisionSupport\Contracts\NodeType;
use ByJesper\DecisionSupport\Definition\EdgeDefinition;
use ByJesper\DecisionSupport\Definition\GuideDefinition;

/**
 * Everything a {@see NodeType} needs to
 * evaluate one step. `input` is the value fed back on resume — null when the
 * runner first arrives at the node, which is how a question knows to suspend
 * (ask) versus consume an answer.
 */
final readonly class EvaluationContext
{
    public function __construct(
        public GuideDefinition $definition,
        public RunState $state,
        public FactProvider $facts,
        public ConditionEvaluator $conditions,
        public mixed $input = null,
    ) {}

    public function context(): GuideContext
    {
        return $this->state->context;
    }

    public function locale(): ?string
    {
        return $this->state->context->locale;
    }

    public function fallbackLocale(): ?string
    {
        return $this->state->context->fallbackLocale;
    }

    /** Resolves `*_i18n` content through the run's locale chain (locale → fallback → base). */
    public function localeResolver(): LocaleResolver
    {
        return new LocaleResolver($this->locale(), $this->fallbackLocale());
    }

    public function hasInput(): bool
    {
        return $this->input !== null;
    }

    /** @return list<EdgeDefinition> */
    public function edgesFrom(string $nodeKey): array
    {
        return $this->definition->edgesFrom($nodeKey);
    }
}
