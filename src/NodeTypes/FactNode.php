<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\NodeTypes;

use ByJesper\DecisionSupport\Contracts\FactProvider;
use ByJesper\DecisionSupport\Contracts\NodeType;
use ByJesper\DecisionSupport\Definition\NodeDefinition;
use ByJesper\DecisionSupport\Facts\FactValue;
use ByJesper\DecisionSupport\Facts\PendingInteraction;
use ByJesper\DecisionSupport\Runtime\EvaluationContext;
use ByJesper\DecisionSupport\Runtime\NodeResult;
use ByJesper\DecisionSupport\Support\PortSet;
use ByJesper\DecisionSupport\Validation\ValidationResult;

/**
 * Resolves a named fact through the guide's {@see FactProvider}
 * and stores it in context. If the provider needs host input (a lookup), it
 * suspends; the resumed input becomes the fact value.
 */
final class FactNode implements NodeType
{
    public const string KEY = 'fact';

    #[\Override]
    public function key(): string
    {
        return self::KEY;
    }

    #[\Override]
    public function configSchema(): array
    {
        return [
            'fact' => ['type' => 'string', 'required' => true],
        ];
    }

    #[\Override]
    public function ports(NodeDefinition $node): PortSet
    {
        return PortSet::of('out');
    }

    #[\Override]
    public function validate(NodeDefinition $node): ValidationResult
    {
        if (! is_string($node->config('fact')) || $node->config('fact') === '') {
            return ValidationResult::error('fact.fact_required', 'Fact node requires a fact name to resolve.', $node->key);
        }

        return ValidationResult::valid();
    }

    #[\Override]
    public function evaluate(NodeDefinition $node, EvaluationContext $context): NodeResult
    {
        $fact = $this->factName($node);

        if ($context->hasInput()) {
            return NodeResult::advance('out', ['facts' => [$fact => $context->input]]);
        }

        $resolved = $context->facts->resolve($fact, $context->context());

        if ($resolved instanceof PendingInteraction) {
            return NodeResult::suspend($resolved->interaction);
        }

        /** @var FactValue $resolved */
        return NodeResult::advance('out', ['facts' => [$fact => $resolved->value]]);
    }

    private function factName(NodeDefinition $node): string
    {
        $fact = $node->config('fact');

        return is_string($fact) && $fact !== '' ? $fact : $node->key;
    }
}
