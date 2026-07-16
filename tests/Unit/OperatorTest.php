<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Enums\Operator;

// #17 — unified loose scalar equality across =, !=, in, not_in.

it('matches a string-valued fact against a typed value with = (loose)', function (): void {
    expect(Operator::Equals->evaluate('5', 5))->toBeTrue()
        ->and(Operator::Equals->evaluate(5, '5'))->toBeTrue()
        ->and(Operator::NotEquals->evaluate('5', 5))->toBeFalse();
});

it('matches a string-valued fact against a typed value with in/not_in (same as =)', function (): void {
    expect(Operator::In->evaluate('5', [5]))->toBeTrue()
        ->and(Operator::In->evaluate(5, ['5']))->toBeTrue()
        ->and(Operator::NotIn->evaluate('5', [5]))->toBeFalse()
        ->and(Operator::In->evaluate('5', [1, 2, 3]))->toBeFalse();
});

it('coerces booleans only to their canonical string forms', function (): void {
    expect(Operator::Equals->evaluate(true, '1'))->toBeTrue()
        ->and(Operator::Equals->evaluate(true, 'true'))->toBeTrue()
        ->and(Operator::Equals->evaluate(false, '0'))->toBeTrue()
        ->and(Operator::Equals->evaluate(false, 'false'))->toBeTrue()
        ->and(Operator::Equals->evaluate(true, true))->toBeTrue();
});

it('no longer treats false = "" (or other non-canonical strings) as equal', function (): void {
    expect(Operator::Equals->evaluate(false, ''))->toBeFalse()
        ->and(Operator::Equals->evaluate(true, ''))->toBeFalse()
        ->and(Operator::Equals->evaluate(true, '0'))->toBeFalse()
        ->and(Operator::Equals->evaluate(false, '1'))->toBeFalse()
        ->and(Operator::Equals->evaluate(true, 'yes'))->toBeFalse();
});

it('applies the canonical bool coercion to membership too', function (): void {
    expect(Operator::In->evaluate(true, ['1']))->toBeTrue()
        ->and(Operator::In->evaluate(false, ['']))->toBeFalse();
});

// #18 — ordering operators compare ISO dates and numeric strings.

it('compares ISO dates with ordering operators', function (): void {
    expect(Operator::LessThan->evaluate('2026-01-01', '2026-06-01'))->toBeTrue()
        ->and(Operator::LessThan->evaluate('2026-06-01', '2026-01-01'))->toBeFalse()
        ->and(Operator::LessThanOrEqual->evaluate('2026-01-01', '2026-01-01'))->toBeTrue()
        ->and(Operator::GreaterThan->evaluate('2026-06-01', '2026-01-01'))->toBeTrue();
});

it('compares a datetime against a date', function (): void {
    expect(Operator::GreaterThan->evaluate('2026-01-01T10:00:00', '2026-01-01'))->toBeTrue()
        ->and(Operator::LessThan->evaluate('2026-01-01', '2026-01-01T10:00:00'))->toBeTrue();
});

it('never orders arbitrary (non-ISO) strings', function (): void {
    expect(Operator::LessThan->evaluate('abc', 'abd'))->toBeFalse()
        ->and(Operator::LessThan->evaluate('2026-01-01', 'not-a-date'))->toBeFalse()
        ->and(Operator::GreaterThan->evaluate('not-a-date', '2026-01-01'))->toBeFalse();
});

it('orders numeric operands, including numeric strings', function (): void {
    expect(Operator::LessThan->evaluate(5, 10))->toBeTrue()
        ->and(Operator::LessThan->evaluate('5', '10'))->toBeTrue()
        ->and(Operator::GreaterThanOrEqual->evaluate('5', 5))->toBeTrue()
        ->and(Operator::GreaterThan->evaluate('5', '10'))->toBeFalse();
});
