<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Contracts;

use ByJesper\DecisionSupport\Conditions\Condition;
use ByJesper\DecisionSupport\Runtime\GuideContext;

interface ConditionEvaluator
{
    public function supports(Condition $condition): bool;

    public function matches(Condition $condition, GuideContext $context): bool;
}
