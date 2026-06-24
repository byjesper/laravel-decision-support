<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Conditions\Condition;
use ByJesper\DecisionSupport\Conditions\ConditionEvaluatorChain;
use ByJesper\DecisionSupport\Conditions\ExpressionConditionEvaluator;
use ByJesper\DecisionSupport\Conditions\StructuredConditionEvaluator;
use ByJesper\DecisionSupport\Enums\Operator;
use ByJesper\DecisionSupport\Runtime\GuideContext;

dataset('operators', [
    'equals true' => [Operator::Equals, 'red', 'red', true],
    'equals false' => [Operator::Equals, 'red', 'blue', false],
    'not equals' => [Operator::NotEquals, 'red', 'blue', true],
    'greater than' => [Operator::GreaterThan, 6, 5, true],
    'greater or equal' => [Operator::GreaterThanOrEqual, 5, 5, true],
    'less than' => [Operator::LessThan, 3, 5, true],
    'in' => [Operator::In, 'b', ['a', 'b'], true],
    'not in' => [Operator::NotIn, 'c', ['a', 'b'], true],
    'is true' => [Operator::IsTrue, true, null, true],
    'is false' => [Operator::IsFalse, false, null, true],
]);

it('evaluates structured operators', function (Operator $operator, mixed $left, mixed $right, bool $expected): void {
    $evaluator = new StructuredConditionEvaluator;
    $context = new GuideContext(facts: ['x' => $left]);

    expect($evaluator->matches(Condition::structured('x', $operator, $right), $context))->toBe($expected);
})->with('operators');

it('treats a missing fact as a non-match without throwing', function (): void {
    $evaluator = new StructuredConditionEvaluator;

    expect($evaluator->matches(Condition::structured('missing', Operator::Equals, 'x'), new GuideContext))->toBeFalse();
});

it('matches the unknown sentinel only when the fact is unresolved', function (): void {
    $evaluator = new StructuredConditionEvaluator;

    expect($evaluator->matches(Condition::unknown('color'), new GuideContext))->toBeTrue()
        ->and($evaluator->matches(Condition::unknown('color'), new GuideContext(facts: ['color' => 'red'])))->toBeFalse();
});

it('always matches the always sentinel', function (): void {
    expect((new StructuredConditionEvaluator)->matches(Condition::always(), new GuideContext))->toBeTrue();
});

it('evaluates expression conditions against context variables', function (): void {
    $evaluator = new ExpressionConditionEvaluator;
    $context = new GuideContext(answers: ['age' => 40], facts: ['tenure' => 6]);

    expect($evaluator->matches(Condition::expression('tenure >= 5 and age > 18'), $context))->toBeTrue()
        ->and($evaluator->matches(Condition::expression('tenure < 5'), $context))->toBeFalse();
});

it('returns false for an invalid expression instead of throwing', function (): void {
    $evaluator = new ExpressionConditionEvaluator;

    expect($evaluator->matches(Condition::expression('this is !! not valid'), new GuideContext))->toBeFalse();
});

it('dispatches to the supporting evaluator through the chain', function (): void {
    $chain = new ConditionEvaluatorChain(
        new StructuredConditionEvaluator,
        new ExpressionConditionEvaluator,
    );
    $context = new GuideContext(facts: ['tenure' => 6]);

    expect($chain->matches(Condition::structured('tenure', Operator::GreaterThan, 5), $context))->toBeTrue()
        ->and($chain->matches(Condition::expression('tenure == 6'), $context))->toBeTrue();
});
