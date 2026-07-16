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
            self::Equals => $this->scalarEquals($left, $right),
            self::NotEquals => ! $this->scalarEquals($left, $right),
            self::GreaterThan => $this->ordered($left, $right, static fn (int $c): bool => $c > 0),
            self::GreaterThanOrEqual => $this->ordered($left, $right, static fn (int $c): bool => $c >= 0),
            self::LessThan => $this->ordered($left, $right, static fn (int $c): bool => $c < 0),
            self::LessThanOrEqual => $this->ordered($left, $right, static fn (int $c): bool => $c <= 0),
            self::In => is_array($right) && array_any($right, fn (mixed $r): bool => $this->scalarEquals($left, $r)),
            self::NotIn => is_array($right) && ! array_any($right, fn (mixed $r): bool => $this->scalarEquals($left, $r)),
            self::IsTrue => $left === true,
            self::IsFalse => $left === false,
        };
    }

    /**
     * Shared equality used by `=`, `!=`, `in`, and `not_in` so a fact routes
     * the same way regardless of which membership/equality operator asks.
     * Strict `===` first, then a loose string compare for scalars — with one
     * carve-out: bools never coerce through `(string)` (that made `false = ""`
     * and `true = "1"` both true). A bool only equals another bool (handled by
     * `===`) or its canonical string forms.
     */
    private function scalarEquals(mixed $left, mixed $right): bool
    {
        if ($left === $right) {
            return true;
        }

        if (is_bool($left) || is_bool($right)) {
            return $this->boolEquals($left, $right);
        }

        if (is_scalar($left) && is_scalar($right)) {
            return (string) $left === (string) $right;
        }

        return false;
    }

    /**
     * Loose equality when exactly one side is a bool (matching bool/bool pairs
     * are already resolved by `===`). The bool matches only the canonical
     * string forms of that boolean — `"true"/"1"` for true, `"false"/"0"` for
     * false — so `false = ""` no longer matches.
     */
    private function boolEquals(mixed $left, mixed $right): bool
    {
        [$bool, $other] = is_bool($left) ? [$left, $right] : [$right, $left];

        if (! is_string($other)) {
            return false;
        }

        return in_array($other, $bool ? ['true', '1'] : ['false', '0'], true);
    }

    /**
     * Apply an ordering test (`>`, `>=`, `<`, `<=`) to two operands, coercing
     * each to a comparable float first. If either side is not comparable the
     * result is false — the runtime falls through to a default branch exactly
     * as before.
     *
     * @param  callable(int): bool  $test  receives the `<=>` spaceship result
     */
    private function ordered(mixed $left, mixed $right, callable $test): bool
    {
        $l = $this->comparableValue($left);
        $r = $this->comparableValue($right);

        if ($l === null || $r === null) {
            return false;
        }

        return $test($l <=> $r);
    }

    /**
     * Normalize an operand to a float for ordering. Numerics (including numeric
     * strings) map directly; unambiguous ISO-8601 date/datetime strings map to
     * their Unix timestamp; everything else is null (not comparable). Deliberately
     * narrow — arbitrary strings never gain an ordering.
     */
    private function comparableValue(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            if (is_numeric($value)) {
                return (float) $value;
            }

            return $this->isoTimestamp($value);
        }

        return null;
    }

    private function isoTimestamp(string $value): ?float
    {
        // Accept only unambiguous ISO-8601: a date (YYYY-MM-DD), optionally with
        // a time component (T or space separator, optional seconds/fraction, and
        // an optional Z or ±HH:MM offset). Anything else stays null.
        $pattern = '/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})?)?$/';

        if (preg_match($pattern, $value) !== 1) {
            return null;
        }

        try {
            return (float) new \DateTimeImmutable($value)->getTimestamp();
        } catch (\Exception) {
            return null;
        }
    }
}
