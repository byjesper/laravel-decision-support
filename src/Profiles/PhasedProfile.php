<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Profiles;

use ByJesper\DecisionSupport\Contracts\GuideProfile;
use ByJesper\DecisionSupport\Definition\EdgeDefinition;
use ByJesper\DecisionSupport\Definition\GuideDefinition;
use ByJesper\DecisionSupport\NodeTypes\DecisionNode;
use ByJesper\DecisionSupport\NodeTypes\FactNode;
use ByJesper\DecisionSupport\NodeTypes\OutcomeNode;
use ByJesper\DecisionSupport\NodeTypes\QuestionNode;
use ByJesper\DecisionSupport\Validation\ValidationResult;

/**
 * The issue's Phase-1 shape: the flow moves forward through
 * questions → facts → decisions → outcomes and never loops back to an earlier
 * phase. Custom node types are unranked and impose no constraint.
 */
final class PhasedProfile implements GuideProfile
{
    public const string KEY = 'phased';

    /** @var array<string, int> */
    private const array PHASES = [
        QuestionNode::KEY => 0,
        FactNode::KEY => 1,
        DecisionNode::KEY => 2,
        OutcomeNode::KEY => 3,
    ];

    #[\Override]
    public function key(): string
    {
        return self::KEY;
    }

    #[\Override]
    public function validate(GuideDefinition $definition): ValidationResult
    {
        $result = ValidationResult::valid();

        foreach ($definition->edges as $edge) {
            $from = $this->phase($definition, $edge);
            $to = $this->phaseOf($definition, $edge->to);

            if ($from === null || $to === null) {
                continue;
            }

            if ($to < $from) {
                $result = $result->merge(ValidationResult::error(
                    'profile.phase_order',
                    "Edge from '{$edge->from}' to '{$edge->to}' moves backwards across phases.",
                    $edge->from,
                    ['from' => $edge->from, 'to' => $edge->to],
                ));
            }
        }

        return $result;
    }

    private function phase(GuideDefinition $definition, EdgeDefinition $edge): ?int
    {
        return $this->phaseOf($definition, $edge->from);
    }

    private function phaseOf(GuideDefinition $definition, string $nodeKey): ?int
    {
        $node = $definition->node($nodeKey);

        return $node === null ? null : (self::PHASES[$node->type] ?? null);
    }
}
