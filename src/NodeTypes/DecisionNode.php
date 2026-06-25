<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\NodeTypes;

use ByJesper\DecisionSupport\Contracts\NodeType;
use ByJesper\DecisionSupport\Definition\NodeDefinition;
use ByJesper\DecisionSupport\Runtime\EvaluationContext;
use ByJesper\DecisionSupport\Runtime\NodeResult;
use ByJesper\DecisionSupport\Support\PortSet;
use ByJesper\DecisionSupport\Validation\ValidationResult;

/**
 * Branches on already-resolved facts. It emits a single `out` port; the routing
 * is carried by the outgoing edges' conditions, which the runner evaluates in
 * order (the first match wins, a default/`unknown` edge catches the rest). This
 * keeps branching data-driven and editable without code.
 */
final class DecisionNode implements NodeType
{
    public const string KEY = 'decision';

    #[\Override]
    public function key(): string
    {
        return self::KEY;
    }

    #[\Override]
    public function configSchema(): array
    {
        return [
            'fact' => ['type' => 'string', 'required' => false, 'help' => 'Optional fact this decision branches on. Leave blank to branch purely on the outgoing edge conditions.'],
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
        return ValidationResult::valid();
    }

    #[\Override]
    public function evaluate(NodeDefinition $node, EvaluationContext $context): NodeResult
    {
        return NodeResult::advance('out');
    }
}
