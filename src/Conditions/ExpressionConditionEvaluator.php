<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Conditions;

use ByJesper\DecisionSupport\Contracts\ConditionEvaluator;
use ByJesper\DecisionSupport\Enums\ConditionType;
use ByJesper\DecisionSupport\Runtime\GuideContext;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * The advanced escape hatch: a symfony/expression-language string evaluated
 * against the fact/answer variables. Evaluation is sandboxed to those variables
 * and never throws at run time — a syntax or missing-variable error yields
 * false so routing falls through to a default branch.
 */
final readonly class ExpressionConditionEvaluator implements ConditionEvaluator
{
    public function __construct(
        private ExpressionLanguage $expressionLanguage = new ExpressionLanguage,
    ) {}

    #[\Override]
    public function supports(Condition $condition): bool
    {
        return $condition->type === ConditionType::Expression;
    }

    #[\Override]
    public function matches(Condition $condition, GuideContext $context): bool
    {
        if ($condition->expression === null) {
            return false;
        }

        try {
            return (bool) $this->expressionLanguage->evaluate($condition->expression, $context->variables());
        } catch (\Throwable) {
            return false;
        }
    }
}
