<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Contracts;

use ByJesper\DecisionSupport\Facts\FactValue;
use ByJesper\DecisionSupport\Facts\FactVocabulary;
use ByJesper\DecisionSupport\Facts\PendingInteraction;
use ByJesper\DecisionSupport\Registry\FactProviderRegistry;
use ByJesper\DecisionSupport\Runtime\GuideContext;

/**
 * The developer-owned half of the dev↔non-dev boundary: it declares the named
 * facts a guide may branch on and resolves them at run time. Hosts bind one per
 * guide via the {@see FactProviderRegistry}.
 */
interface FactProvider
{
    public function vocabulary(): FactVocabulary;

    /**
     * Resolve a named fact. Return a {@see FactValue} when known synchronously,
     * or a {@see PendingInteraction} to suspend the run for host input.
     */
    public function resolve(string $fact, GuideContext $context): FactValue|PendingInteraction;
}
