<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Profiles;

use ByJesper\DecisionSupport\Contracts\GuideProfile;
use ByJesper\DecisionSupport\Contracts\SupportsCycles;
use ByJesper\DecisionSupport\Definition\GuideDefinition;
use ByJesper\DecisionSupport\Validation\ValidationResult;

/**
 * Imposes no ordering — any node may connect to any other, including cycles.
 * The home for the "fully free-form" guides that can loop back and re-ask a
 * question.
 */
final class FreeformProfile implements GuideProfile, SupportsCycles
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
