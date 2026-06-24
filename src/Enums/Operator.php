<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Enums;

enum Operator: string
{
    case Equals = '=';
    case NotEquals = '!=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
    case In = 'in';
    case NotIn = 'not_in';
    case IsTrue = 'is_true';
    case IsFalse = 'is_false';

    /**
     * Evaluate this operator against a resolved left-hand fact value and the
     * condition's configured right-hand value. Comparisons never throw — an
     * incompatible pair simply yields false so the runtime can fall through to
     * a default branch.
     */
    public function evaluate(mixed $left, mixed $right): bool
    {
        return match ($this) {
            self::Equals => $left === $right || $this->looseEquals($left, $right),
            self::NotEquals => ! ($left === $right || $this->looseEquals($left, $right)),
            self::GreaterThan => $this->isComparable($left, $right) && $left > $right,
            self::GreaterThanOrEqual => $this->isComparable($left, $right) && $left >= $right,
            self::LessThan => $this->isComparable($left, $right) && $left < $right,
            self::LessThanOrEqual => $this->isComparable($left, $right) && $left <= $right,
            self::In => is_array($right) && in_array($left, $right, true),
            self::NotIn => is_array($right) && ! in_array($left, $right, true),
            self::IsTrue => $left === true,
            self::IsFalse => $left === false,
        };
    }

    private function looseEquals(mixed $left, mixed $right): bool
    {
        if (is_scalar($left) && is_scalar($right)) {
            return (string) $left === (string) $right;
        }

        return false;
    }

    private function isComparable(mixed $left, mixed $right): bool
    {
        return (is_int($left) || is_float($left)) && (is_int($right) || is_float($right));
    }
}
