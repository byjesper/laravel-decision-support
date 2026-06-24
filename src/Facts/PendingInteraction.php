<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Facts;

use ByJesper\DecisionSupport\Runtime\Interaction;

/**
 * Returned by a provider when a fact cannot be resolved without host input
 * (e.g. a lookup the user must complete). Suspends the run.
 */
final readonly class PendingInteraction
{
    public function __construct(public Interaction $interaction) {}
}
