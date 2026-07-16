<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Mermaid;

use ByJesper\DecisionSupport\Conditions\Condition;
use ByJesper\DecisionSupport\Definition\EdgeDefinition;
use ByJesper\DecisionSupport\Definition\GuideDefinition;
use ByJesper\DecisionSupport\Definition\NodeDefinition;
use ByJesper\DecisionSupport\Enums\ConditionType;
use ByJesper\DecisionSupport\NodeTypes\DecisionNode;
use ByJesper\DecisionSupport\NodeTypes\FactNode;
use ByJesper\DecisionSupport\NodeTypes\OutcomeNode;
use ByJesper\DecisionSupport\NodeTypes\QuestionNode;
use ByJesper\DecisionSupport\Runtime\LocaleResolver;
use ByJesper\DecisionSupport\Runtime\RunState;

/**
 * Renders a guide as Mermaid `flowchart` source — the single source of truth
 * for both the editor preview and the runner diagram. Framework-free: a pure
 * {@see GuideDefinition} (plus an optional {@see RunState}) in, a string out.
 * Passing a run state highlights the reached path.
 *
 * Node text is resolved through the same locale chain as the runner (locale →
 * fallback → base), so the diagram reads in the run's language. The locale can
 * be passed explicitly (for the pre-start diagram, which has no run state) or is
 * derived from the highlighted run state when omitted. With no locale at all it
 * renders the base strings — the pre-i18n behaviour.
 */
final class MermaidRenderer
{
    public function render(
        GuideDefinition $definition,
        ?RunState $highlight = null,
        ?string $locale = null,
        ?string $fallbackLocale = null,
    ): string {
        $resolver = $this->resolver($highlight, $locale, $fallbackLocale);

        $lines = ['flowchart TD'];

        foreach ($definition->nodes as $node) {
            $lines[] = '    '.$this->nodeLine($node, $resolver);
        }

        foreach ($definition->edges as $edge) {
            $lines[] = '    '.$this->edgeLine($edge, $resolver);
        }

        foreach ($this->highlightLines($highlight) as $line) {
            $lines[] = '    '.$line;
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * An explicit locale wins; otherwise fall back to the highlighted run's
     * locale chain so an active run localizes without the caller threading it.
     */
    private function resolver(?RunState $highlight, ?string $locale, ?string $fallbackLocale): LocaleResolver
    {
        if ($locale === null && $fallbackLocale === null && $highlight !== null) {
            return new LocaleResolver($highlight->context->locale, $highlight->context->fallbackLocale);
        }

        return new LocaleResolver($locale, $fallbackLocale);
    }

    private function nodeLine(NodeDefinition $node, LocaleResolver $resolver): string
    {
        $id = $this->id($node->key);
        $text = $this->escape($this->text($node, $resolver));

        return match ($node->type) {
            QuestionNode::KEY, DecisionNode::KEY => "{$id}{\"{$text}\"}",
            FactNode::KEY => "{$id}[/\"{$text}\"/]",
            OutcomeNode::KEY => "{$id}([\"{$text}\"])",
            default => "{$id}[\"{$text}\"]",
        };
    }

    private function edgeLine(EdgeDefinition $edge, LocaleResolver $resolver): string
    {
        $from = $this->id($edge->from);
        $to = $this->id($edge->to);
        $label = $this->edgeLabel($edge, $resolver);

        if ($label === '') {
            return "{$from} --> {$to}";
        }

        return "{$from} -->|\"{$this->escape($label)}\"| {$to}";
    }

    private function edgeLabel(EdgeDefinition $edge, LocaleResolver $resolver): string
    {
        // An authored label (with its own `label_i18n`) overrides the derived one,
        // resolved through the same locale chain as node text.
        $custom = $resolver->localizedNullableString($edge->labelI18n, $edge->label);
        if (is_string($custom) && $custom !== '') {
            return $custom;
        }

        if ($edge->fromPort !== 'out' && $edge->fromPort !== '') {
            return $edge->fromPort;
        }

        return $edge->condition === null ? '' : $this->conditionLabel($edge->condition);
    }

    private function conditionLabel(Condition $condition): string
    {
        return match ($condition->type) {
            ConditionType::Always => 'else',
            ConditionType::Unknown => "{$condition->fact} unknown",
            ConditionType::Expression => (string) $condition->expression,
            ConditionType::Structured => $this->structuredLabel($condition),
        };
    }

    private function structuredLabel(Condition $condition): string
    {
        $operator = $condition->operator;

        if ($operator === null) {
            return trim((string) $condition->fact.' '.$this->scalar($condition->value));
        }

        return trim(sprintf(
            '%s %s %s',
            (string) $condition->fact,
            $operator->value,
            $this->scalar($condition->value),
        ));
    }

    private function scalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return '['.implode(', ', array_map($this->scalar(...), $value)).']';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /** @return list<string> */
    private function highlightLines(?RunState $highlight): array
    {
        if ($highlight === null || $highlight->path === []) {
            return [];
        }

        // A cyclic run's path may revisit nodes; collapse duplicates so each
        // reached node is styled once.
        $ids = array_values(array_unique(array_map($this->id(...), $highlight->path)));

        return [
            'classDef reached fill:#dcfce7,stroke:#16a34a,stroke-width:2px;',
            'class '.implode(',', $ids).' reached;',
        ];
    }

    private function text(NodeDefinition $node, LocaleResolver $resolver): string
    {
        // An explicit label (with its own `label_i18n`) wins for every node type.
        $label = $resolver->localizedNullableString($this->i18n($node, 'label_i18n'), $node->label);
        if (is_string($label) && $label !== '') {
            return $label;
        }

        // Otherwise fall back to the type's display field, resolved through the
        // same locale chain the runner uses (so fact/decision still show the key).
        $candidate = match ($node->type) {
            QuestionNode::KEY => $resolver->localizedNullableString($this->i18n($node, 'prompt_i18n'), $this->stringConfig($node, 'prompt')),
            OutcomeNode::KEY => $resolver->localizedNullableString($this->i18n($node, 'verdict_i18n'), $this->stringConfig($node, 'verdict')),
            FactNode::KEY, DecisionNode::KEY => $this->stringConfig($node, 'fact'),
            default => null,
        };

        return is_string($candidate) && $candidate !== '' ? $candidate : $node->key;
    }

    /** @return array<string, mixed> */
    private function i18n(NodeDefinition $node, string $key): array
    {
        $map = $node->config($key);

        return is_array($map) ? $map : [];
    }

    private function stringConfig(NodeDefinition $node, string $key): ?string
    {
        $value = $node->config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function id(string $key): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_]/', '_', $key) ?? '_';

        return 'n_'.$safe;
    }

    private function escape(string $text): string
    {
        return str_replace(['"', "\n"], ['&quot;', ' '], $text);
    }
}
