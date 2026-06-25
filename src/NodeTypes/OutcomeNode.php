<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\NodeTypes;

use ByJesper\DecisionSupport\Contracts\NodeType;
use ByJesper\DecisionSupport\Definition\NodeDefinition;
use ByJesper\DecisionSupport\Runtime\EvaluationContext;
use ByJesper\DecisionSupport\Runtime\NodeResult;
use ByJesper\DecisionSupport\Runtime\Outcome;
use ByJesper\DecisionSupport\Support\PortSet;
use ByJesper\DecisionSupport\Validation\ValidationResult;

/**
 * A terminal verdict: text plus optional warnings. Reaching it ends the run.
 */
final class OutcomeNode implements NodeType
{
    public const string KEY = 'outcome';

    #[\Override]
    public function key(): string
    {
        return self::KEY;
    }

    #[\Override]
    public function configSchema(): array
    {
        return [
            'verdict' => ['type' => 'string', 'required' => true, 'help' => 'The short verdict shown when this outcome is reached, e.g. "Eligible". Reaching this node ends the run.'],
            'text' => ['type' => 'string', 'required' => false, 'help' => 'Optional longer explanation shown beneath the verdict.'],
            'warnings' => ['type' => 'list', 'required' => false, 'help' => 'Optional caveats shown with the verdict — one per line.'],
        ];
    }

    #[\Override]
    public function ports(NodeDefinition $node): PortSet
    {
        return PortSet::none();
    }

    #[\Override]
    public function validate(NodeDefinition $node): ValidationResult
    {
        if (! is_string($node->config('verdict')) || $node->config('verdict') === '') {
            return ValidationResult::error('outcome.verdict_required', 'Outcome node requires a verdict.', $node->key);
        }

        return ValidationResult::valid();
    }

    #[\Override]
    public function evaluate(NodeDefinition $node, EvaluationContext $context): NodeResult
    {
        return NodeResult::terminate($this->outcome($node));
    }

    private function outcome(NodeDefinition $node): Outcome
    {
        return new Outcome(
            nodeKey: $node->key,
            verdict: is_string($node->config('verdict')) ? $node->config('verdict') : $node->key,
            text: is_string($node->config('text')) ? $node->config('text') : null,
            warnings: $this->warnings($node),
        );
    }

    /** @return list<string> */
    private function warnings(NodeDefinition $node): array
    {
        $warnings = $node->config('warnings');
        if (! is_array($warnings)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $w): string => is_scalar($w) ? (string) $w : '', $warnings),
            static fn (string $w): bool => $w !== '',
        ));
    }
}
