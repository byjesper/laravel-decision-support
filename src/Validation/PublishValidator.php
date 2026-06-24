<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Validation;

use ByJesper\DecisionSupport\Conditions\Condition;
use ByJesper\DecisionSupport\Contracts\GuideProfile;
use ByJesper\DecisionSupport\Definition\EdgeDefinition;
use ByJesper\DecisionSupport\Definition\GuideDefinition;
use ByJesper\DecisionSupport\Enums\ConditionType;
use ByJesper\DecisionSupport\Facts\FactVocabulary;
use ByJesper\DecisionSupport\NodeTypes\OutcomeNode;
use ByJesper\DecisionSupport\Registry\NodeTypeRegistry;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * Rejects a draft before it can be published, loudly and structurally. Runs,
 * in order: per-node config validation, graph integrity (dangling edges,
 * orphans, uncovered ports, non-outcome dead ends), termination (cycle
 * detection — a guide must be acyclic so every path reaches an outcome), fact
 * references (structured facts must be in the vocabulary; expressions are
 * linted against it), then the declared profile's rules.
 */
final readonly class PublishValidator
{
    public function __construct(
        private NodeTypeRegistry $nodeTypes,
        private ExpressionLanguage $expressionLanguage = new ExpressionLanguage,
    ) {}

    public function validate(
        GuideDefinition $definition,
        FactVocabulary $vocabulary,
        ?GuideProfile $profile = null,
    ): ValidationResult {
        $result = ValidationResult::valid();
        $result = $result->merge($this->validateEntry($definition));
        $result = $result->merge($this->validateNodes($definition));
        $result = $result->merge($this->validateEdges($definition));
        $result = $result->merge($this->validatePorts($definition));
        $result = $result->merge($this->validateLeaves($definition));
        $result = $result->merge($this->validateReachability($definition));
        $result = $result->merge($this->validateAcyclic($definition));
        $result = $result->merge($this->validateFactReferences($definition, $vocabulary));

        if ($profile !== null) {
            $result = $result->merge($profile->validate($definition));
        }

        return $result;
    }

    private function validateEntry(GuideDefinition $definition): ValidationResult
    {
        if ($definition->entryNode === '' || $definition->entry() === null) {
            return ValidationResult::error('graph.no_entry', 'The guide has no resolvable entry node.');
        }

        return ValidationResult::valid();
    }

    private function validateNodes(GuideDefinition $definition): ValidationResult
    {
        $result = ValidationResult::valid();

        foreach ($definition->nodes as $node) {
            $type = $this->nodeTypes->get($node->type);

            if ($type === null) {
                $result = $result->merge(ValidationResult::error(
                    'graph.unknown_node_type',
                    "Node '{$node->key}' has an unregistered type '{$node->type}'.",
                    $node->key,
                ));

                continue;
            }

            $result = $result->merge($type->validate($node));
        }

        return $result;
    }

    private function validateEdges(GuideDefinition $definition): ValidationResult
    {
        $result = ValidationResult::valid();

        foreach ($definition->edges as $edge) {
            if ($definition->node($edge->from) === null) {
                $result = $result->merge(ValidationResult::error('graph.dangling_edge', "Edge starts at unknown node '{$edge->from}'.", $edge->from));
            }

            if ($definition->node($edge->to) === null) {
                $result = $result->merge(ValidationResult::error('graph.dangling_edge', "Edge points to unknown node '{$edge->to}'.", $edge->to));
            }
        }

        return $result;
    }

    private function validatePorts(GuideDefinition $definition): ValidationResult
    {
        $result = ValidationResult::valid();

        foreach ($definition->nodes as $node) {
            $type = $this->nodeTypes->get($node->type);
            if ($type === null) {
                continue;
            }

            $edges = $definition->edgesFrom($node->key);

            foreach ($type->ports($node)->ports as $port) {
                $covered = array_filter($edges, static fn (EdgeDefinition $e): bool => $e->fromPort === $port);

                if ($covered === []) {
                    $result = $result->merge(ValidationResult::error(
                        'graph.uncovered_port',
                        "Node '{$node->key}' has no outgoing edge for port '{$port}'.",
                        $node->key,
                    ));
                }
            }
        }

        return $result;
    }

    private function validateLeaves(GuideDefinition $definition): ValidationResult
    {
        $result = ValidationResult::valid();

        foreach ($definition->nodes as $node) {
            if ($definition->edgesFrom($node->key) !== []) {
                continue;
            }

            if ($node->type !== OutcomeNode::KEY) {
                $result = $result->merge(ValidationResult::error(
                    'graph.non_outcome_leaf',
                    "Node '{$node->key}' has no outgoing edges but is not an outcome.",
                    $node->key,
                ));
            }
        }

        return $result;
    }

    private function validateReachability(GuideDefinition $definition): ValidationResult
    {
        if ($definition->entry() === null) {
            return ValidationResult::valid();
        }

        $reached = $this->reachable($definition);
        $result = ValidationResult::valid();

        foreach ($definition->nodes as $node) {
            if (! isset($reached[$node->key])) {
                $result = $result->merge(ValidationResult::error(
                    'graph.orphan_node',
                    "Node '{$node->key}' is unreachable from the entry node.",
                    $node->key,
                ));
            }
        }

        return $result;
    }

    private function validateAcyclic(GuideDefinition $definition): ValidationResult
    {
        /** @var array<string, int> $color 0=unvisited, 1=in-stack, 2=done */
        $color = [];
        $found = null;

        $visit = function (string $key) use (&$visit, &$color, &$found, $definition): void {
            $color[$key] = 1;

            foreach ($definition->edgesFrom($key) as $edge) {
                $next = $edge->to;
                if ($definition->node($next) === null) {
                    continue;
                }

                $state = $color[$next] ?? 0;
                if ($state === 1) {
                    $found ??= $next;
                } elseif ($state === 0) {
                    $visit($next);
                }
            }

            $color[$key] = 2;
        };

        foreach ($definition->nodes as $node) {
            if (($color[$node->key] ?? 0) === 0) {
                $visit($node->key);
            }
        }

        if ($found !== null) {
            return ValidationResult::error('graph.cycle', "The guide contains a cycle through '{$found}'.", $found);
        }

        return ValidationResult::valid();
    }

    private function validateFactReferences(GuideDefinition $definition, FactVocabulary $vocabulary): ValidationResult
    {
        $result = ValidationResult::valid();

        foreach ($definition->edges as $edge) {
            if ($edge->condition === null) {
                continue;
            }

            $result = $result->merge($this->validateCondition($edge, $edge->condition, $vocabulary));
        }

        return $result;
    }

    private function validateCondition(EdgeDefinition $edge, Condition $condition, FactVocabulary $vocabulary): ValidationResult
    {
        if ($condition->type === ConditionType::Expression) {
            return $this->lintExpression($edge, $condition, $vocabulary);
        }

        if (in_array($condition->type, [ConditionType::Structured, ConditionType::Unknown], true)) {
            if ($condition->fact !== null && ! $vocabulary->has($condition->fact)) {
                return ValidationResult::error(
                    'fact.unknown_fact',
                    "Condition on edge '{$edge->from}' references unknown fact '{$condition->fact}'.",
                    $edge->from,
                );
            }
        }

        return ValidationResult::valid();
    }

    private function lintExpression(EdgeDefinition $edge, Condition $condition, FactVocabulary $vocabulary): ValidationResult
    {
        if ($condition->expression === null || $condition->expression === '') {
            return ValidationResult::error('fact.empty_expression', "Expression condition on edge '{$edge->from}' is empty.", $edge->from);
        }

        try {
            $this->expressionLanguage->lint($condition->expression, $vocabulary->names());
        } catch (SyntaxError $e) {
            return ValidationResult::error(
                'fact.invalid_expression',
                "Expression on edge '{$edge->from}' is invalid: {$e->getMessage()}",
                $edge->from,
            );
        }

        return ValidationResult::valid();
    }

    /** @return array<string, true> */
    private function reachable(GuideDefinition $definition): array
    {
        $reached = [];
        $queue = [$definition->entryNode];

        while ($queue !== []) {
            $key = array_shift($queue);
            if (isset($reached[$key]) || $definition->node($key) === null) {
                continue;
            }

            $reached[$key] = true;

            foreach ($definition->edgesFrom($key) as $edge) {
                $queue[] = $edge->to;
            }
        }

        return $reached;
    }
}
