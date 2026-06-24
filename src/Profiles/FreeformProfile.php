<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Profiles;

use ByJesper\DecisionSupport\Contracts\GuideProfile;
use ByJesper\DecisionSupport\Definition\GuideDefinition;
use ByJesper\DecisionSupport\Validation\ValidationResult;

/**
 * Imposes no ordering — any node may connect to any other. The home for the
 * deferred "fully free-form" guides.
 */
final class FreeformProfile implements GuideProfile
{
    public const string KEY = 'freeform';

    #[\Override]
    public function key(): string
    {
        return self::KEY;
    }

    #[\Override]
    public function validate(GuideDefinition $definition): ValidationResult
    {
        return ValidationResult::valid();
    }
}
