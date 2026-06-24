<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Conditions;

use ByJesper\DecisionSupport\Contracts\ConditionEvaluator;
use ByJesper\DecisionSupport\Runtime\GuideContext;

/**
 * Dispatches a condition to the first registered evaluator that supports it.
 * This is what the runner and validator depend on, so structured/expression
 * selection stays an implementation detail.
 */
final readonly class ConditionEvaluatorChain implements ConditionEvaluator
{
    /** @var list<ConditionEvaluator> */
    private array $evaluators;

    public function __construct(ConditionEvaluator ...$evaluators)
    {
        $this->evaluators = array_values($evaluators);
    }

    #[\Override]
    public function supports(Condition $condition): bool
    {
        return array_any($this->evaluators, fn (ConditionEvaluator $evaluator): bool => $evaluator->supports($condition));
    }

    #[\Override]
    public function matches(Condition $condition, GuideContext $context): bool
    {
        foreach ($this->evaluators as $evaluator) {
            if ($evaluator->supports($condition)) {
                return $evaluator->matches($condition, $context);
            }
        }

        return false;
    }
}
