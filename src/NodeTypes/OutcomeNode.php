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
        return NodeResult::terminate($this->outcome($node, $context));
    }

    private function outcome(NodeDefinition $node, EvaluationContext $context): Outcome
    {
        $resolver = $context->localeResolver();

        $baseVerdict = is_string($node->config('verdict')) ? $node->config('verdict') : $node->key;
        $baseText = is_string($node->config('text')) ? $node->config('text') : null;

        return new Outcome(
            nodeKey: $node->key,
            verdict: $resolver->localizedString($this->i18n($node, 'verdict_i18n'), $baseVerdict),
            text: $resolver->localizedNullableString($this->i18n($node, 'text_i18n'), $baseText),
            warnings: $resolver->localizedList($this->i18n($node, 'warnings_i18n'), $this->warnings($node)),
        );
    }

    /** @return array<string, mixed> */
    private function i18n(NodeDefinition $node, string $key): array
    {
        $map = $node->config($key);

        return is_array($map) ? $map : [];
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
