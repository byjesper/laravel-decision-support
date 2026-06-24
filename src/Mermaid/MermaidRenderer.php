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
use ByJesper\DecisionSupport\Runtime\RunState;

/**
 * Renders a guide as Mermaid `flowchart` source — the single source of truth
 * for both the editor preview and the runner diagram. Framework-free: a pure
 * {@see GuideDefinition} (plus an optional {@see RunState}) in, a string out.
 * Passing a run state highlights the reached path.
 */
final class MermaidRenderer
{
    public function render(GuideDefinition $definition, ?RunState $highlight = null): string
    {
        $lines = ['flowchart TD'];

        foreach ($definition->nodes as $node) {
            $lines[] = '    '.$this->nodeLine($node);
        }

        foreach ($definition->edges as $edge) {
            $lines[] = '    '.$this->edgeLine($edge);
        }

        foreach ($this->highlightLines($highlight) as $line) {
            $lines[] = '    '.$line;
        }

        return implode("\n", $lines)."\n";
    }

    private function nodeLine(NodeDefinition $node): string
    {
        $id = $this->id($node->key);
        $text = $this->escape($this->text($node));

        return match ($node->type) {
            QuestionNode::KEY, DecisionNode::KEY => "{$id}{\"{$text}\"}",
            FactNode::KEY => "{$id}[/\"{$text}\"/]",
            OutcomeNode::KEY => "{$id}([\"{$text}\"])",
            default => "{$id}[\"{$text}\"]",
        };
    }

    private function edgeLine(EdgeDefinition $edge): string
    {
        $from = $this->id($edge->from);
        $to = $this->id($edge->to);
        $label = $this->edgeLabel($edge);

        if ($label === '') {
            return "{$from} --> {$to}";
        }

        return "{$from} -->|\"{$this->escape($label)}\"| {$to}";
    }

    private function edgeLabel(EdgeDefinition $edge): string
    {
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

        $ids = array_map($this->id(...), $highlight->path);

        return [
            'classDef reached fill:#dcfce7,stroke:#16a34a,stroke-width:2px;',
            'class '.implode(',', $ids).' reached;',
        ];
    }

    private function text(NodeDefinition $node): string
    {
        if (is_string($node->label) && $node->label !== '') {
            return $node->label;
        }

        $candidate = match ($node->type) {
            QuestionNode::KEY => $node->config('prompt'),
            OutcomeNode::KEY => $node->config('verdict'),
            FactNode::KEY, DecisionNode::KEY => $node->config('fact'),
            default => null,
        };

        return is_string($candidate) && $candidate !== '' ? $candidate : $node->key;
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
