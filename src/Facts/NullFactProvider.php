<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Facts;

use ByJesper\DecisionSupport\Contracts\FactProvider;
use ByJesper\DecisionSupport\Runtime\GuideContext;

/**
 * The fallback used when a guide has no registered provider. It exposes no
 * vocabulary and resolves every fact to null, so a guide that references facts
 * without a provider routes through its default/`unknown` branches instead of
 * throwing.
 */
final class NullFactProvider implements FactProvider
{
    #[\Override]
    public function vocabulary(): FactVocabulary
    {
        return new FactVocabulary([]);
    }

    #[\Override]
    public function resolve(string $fact, GuideContext $context): FactValue
    {
        return new FactValue(null);
    }
}
