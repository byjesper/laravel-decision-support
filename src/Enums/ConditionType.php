<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Enums;

enum ConditionType: string
{
    /** Fact + operator + value, authored through the structured builder. */
    case Structured = 'structured';

    /** A symfony/expression-language string (advanced escape hatch). */
    case Expression = 'expression';

    /** Default / else branch — always matches. */
    case Always = 'always';

    /** Matches only when the referenced fact could not be resolved. */
    case Unknown = 'unknown';
}
