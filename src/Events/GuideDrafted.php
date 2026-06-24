<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Events;

final readonly class GuideDrafted
{
    public function __construct(
        public string $guideKey,
        public int $version,
    ) {}
}
