<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Contracts;

use ByJesper\DecisionSupport\Definition\NodeDefinition;
use ByJesper\DecisionSupport\Runtime\EvaluationContext;
use ByJesper\DecisionSupport\Runtime\NodeResult;
use ByJesper\DecisionSupport\Support\PortSet;
use ByJesper\DecisionSupport\Validation\ValidationResult;

/**
 * The free-form primitive: a kind of node the editor can place and the runtime
 * can evaluate. The four built-ins (question/fact/decision/outcome) cover the
 * issue's phased model; hosts register their own for new behaviours without an
 * engine change.
 */
interface NodeType
{
    /** Stable discriminator stored on each node, e.g. 'question'. */
    public function key(): string;

    /**
     * Describes the node's config fields. Drives the Filament form in the
     * companion package; the engine itself does not interpret it.
     *
     * @return array<string, mixed>
     */
    public function configSchema(): array;

    /** The named outputs this node may emit, used for edge validation. */
    public function ports(NodeDefinition $node): PortSet;

    /** Validate the node's own config in isolation (graph checks live in the publish validator). */
    public function validate(NodeDefinition $node): ValidationResult;

    /** Evaluate one step, returning Advance, Suspend, or Terminate. */
    public function evaluate(NodeDefinition $node, EvaluationContext $context): NodeResult;
}
