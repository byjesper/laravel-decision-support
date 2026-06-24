<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Facts;

/**
 * A resolved fact value returned synchronously by a provider.
 */
final readonly class FactValue
{
    public function __construct(public mixed $value) {}
}
