<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Contracts;

use ByJesper\DecisionSupport\Definition\GuideDefinition;
use ByJesper\DecisionSupport\Validation\ValidationResult;

/**
 * A publish-time shape constraint. The phased model the issue describes is just
 * a profile; "fully free-form" is another. A guide declares which profile it
 * follows and the publish validator enforces it.
 */
interface GuideProfile
{
    public function key(): string;

    public function validate(GuideDefinition $definition): ValidationResult;
}
