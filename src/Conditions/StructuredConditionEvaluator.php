<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Conditions;

use ByJesper\DecisionSupport\Contracts\ConditionEvaluator;
use ByJesper\DecisionSupport\Enums\ConditionType;
use ByJesper\DecisionSupport\Runtime\GuideContext;

/**
 * The default evaluator: fact + operator + value, plus the `always` and
 * `unknown` routing sentinels. A missing fact never throws — a structured
 * comparison against an unresolved fact is simply false, letting routing fall
 * through to a default or `unknown` branch.
 */
final class StructuredConditionEvaluator implements ConditionEvaluator
{
    #[\Override]
    public function supports(Condition $condition): bool
    {
        return in_array(
            $condition->type,
            [ConditionType::Structured, ConditionType::Always, ConditionType::Unknown],
            true,
        );
    }

    #[\Override]
    public function matches(Condition $condition, GuideContext $context): bool
    {
        return match ($condition->type) {
            ConditionType::Always => true,
            ConditionType::Unknown => $condition->fact !== null && ! $context->hasFact($condition->fact),
            ConditionType::Structured => $this->matchesStructured($condition, $context),
            ConditionType::Expression => false,
        };
    }

    private function matchesStructured(Condition $condition, GuideContext $context): bool
    {
        if ($condition->fact === null || $condition->operator === null) {
            return false;
        }

        if (! $context->hasFact($condition->fact)) {
            return false;
        }

        return $condition->operator->evaluate($context->fact($condition->fact), $condition->value);
    }
}
