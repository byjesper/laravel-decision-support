<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Conditions\Condition;
use ByJesper\DecisionSupport\Contracts\GuideProfile;
use ByJesper\DecisionSupport\Definition\GuideDefinition;
use ByJesper\DecisionSupport\Enums\FactType;
use ByJesper\DecisionSupport\Enums\Operator;
use ByJesper\DecisionSupport\NodeTypes\DecisionNode;
use ByJesper\DecisionSupport\NodeTypes\FactNode;
use ByJesper\DecisionSupport\NodeTypes\OutcomeNode;
use ByJesper\DecisionSupport\NodeTypes\QuestionNode;
use ByJesper\DecisionSupport\Profiles\FreeformProfile;
use ByJesper\DecisionSupport\Registry\NodeTypeRegistry;
use ByJesper\DecisionSupport\Testing\FakeFactProvider;
use ByJesper\DecisionSupport\Testing\GuideBuilder;
use ByJesper\DecisionSupport\Validation\PublishValidator;
use ByJesper\DecisionSupport\Validation\ValidationResult;

function makeValidator(): PublishValidator
{
    $registry = new NodeTypeRegistry;
    $registry->register(new QuestionNode);
    $registry->register(new FactNode);
    $registry->register(new DecisionNode);
    $registry->register(new OutcomeNode);

    return new PublishValidator($registry);
}

it('passes a well-formed guide', function (): void {
    $guide = GuideBuilder::make('eligibility')
        ->question('q1', 'Employed?', 'employed', 'boolean')
        ->outcome('yes', 'Eligible')
        ->outcome('no', 'Not eligible')
        ->edge('q1', 'yes', 'true')
        ->edge('q1', 'no', 'false')
        ->build();

    $result = makeValidator()->validate($guide, FakeFactProvider::make()->declare('employed', FactType::Boolean)->vocabulary());

    expect($result->passes())->toBeTrue();
});

it('rejects a condition that references an unknown fact', function (): void {
    $guide = GuideBuilder::make('tenure')
        ->fact('f1', 'tenure')
        ->decision('d1')
        ->outcome('a', 'A')
        ->outcome('b', 'B')
        ->edge('f1', 'd1')
        ->edge('d1', 'a', 'out', Condition::structured('ghost', Operator::Equals, 1))
        ->edge('d1', 'b', 'out', Condition::always())
        ->build();

    $result = makeValidator()->validate($guide, FakeFactProvider::make()->declare('tenure', FactType::Number)->vocabulary());

    expect($result->hasCode('fact.unknown_fact'))->toBeTrue();
});

it('rejects a dangling edge', function (): void {
    $guide = GuideBuilder::make('broken')
        ->question('q1', 'Employed?', 'employed', 'boolean')
        ->outcome('yes', 'Yes')
        ->edge('q1', 'yes', 'true')
        ->edge('q1', 'ghost', 'false')
        ->build();

    $result = makeValidator()->validate($guide, FakeFactProvider::make()->vocabulary());

    expect($result->hasCode('graph.dangling_edge'))->toBeTrue();
});

it('rejects an uncovered port', function (): void {
    $guide = GuideBuilder::make('partial')
        ->question('q1', 'Employed?', 'employed', 'boolean')
        ->outcome('yes', 'Yes')
        ->edge('q1', 'yes', 'true')
        ->build();

    $result = makeValidator()->validate($guide, FakeFactProvider::make()->vocabulary());

    expect($result->hasCode('graph.uncovered_port'))->toBeTrue();
});

it('exposes the interpolated values as structured params for translation', function (): void {
    $guide = GuideBuilder::make('partial')
        ->question('q1', 'Employed?', 'employed', 'boolean')
        ->outcome('yes', 'Yes')
        ->edge('q1', 'yes', 'true')
        ->build();

    $result = makeValidator()->validate($guide, FakeFactProvider::make()->vocabulary());

    $error = collect($result->errors)->firstWhere('code', 'graph.uncovered_port');

    expect($error)->not->toBeNull()
        ->and($error?->params)->toBe(['key' => 'q1', 'port' => 'false']);
});

it('rejects a cycle', function (): void {
    $guide = GuideBuilder::make('loop')
        ->decision('a')
        ->decision('b')
        ->outcome('end', 'End')
        ->edge('a', 'b')
        ->edge('b', 'a')
        ->edge('a', 'end', 'out', Condition::always())
        ->build();

    $result = makeValidator()->validate($guide, FakeFactProvider::make()->vocabulary());

    expect($result->hasCode('graph.cycle'))->toBeTrue();
});

it('accepts a cycle for a cycle-supporting profile', function (): void {
    // q1 (true) -> q2; q2 (false) -> q1: a legal loop for freeform.
    $guide = GuideBuilder::make('loop')
        ->question('q1', 'Continue?', 'again', 'boolean')
        ->question('q2', 'Done?', 'done', 'boolean')
        ->outcome('ok', 'Ok')
        ->outcome('stop', 'Stop')
        ->edge('q1', 'q2', 'true')
        ->edge('q1', 'stop', 'false')
        ->edge('q2', 'ok', 'true')
        ->edge('q2', 'q1', 'false')
        ->build();

    $vocab = FakeFactProvider::make()
        ->declare('again', FactType::Boolean)
        ->declare('done', FactType::Boolean)
        ->vocabulary();

    $result = makeValidator()->validate($guide, $vocab, new FreeformProfile);

    expect($result->passes())->toBeTrue()
        ->and($result->hasCode('graph.cycle'))->toBeFalse();
});

it('still rejects a cycle for a profile that does not support cycles', function (): void {
    $guide = GuideBuilder::make('loop')
        ->question('q1', 'Continue?', 'again', 'boolean')
        ->question('q2', 'Done?', 'done', 'boolean')
        ->outcome('ok', 'Ok')
        ->outcome('stop', 'Stop')
        ->edge('q1', 'q2', 'true')
        ->edge('q1', 'stop', 'false')
        ->edge('q2', 'ok', 'true')
        ->edge('q2', 'q1', 'false')
        ->build();

    $vocab = FakeFactProvider::make()
        ->declare('again', FactType::Boolean)
        ->declare('done', FactType::Boolean)
        ->vocabulary();

    $acyclicProfile = new class implements GuideProfile
    {
        #[Override]
        public function key(): string
        {
            return 'strict';
        }

        #[Override]
        public function validate(GuideDefinition $definition): ValidationResult
        {
            return ValidationResult::valid();
        }
    };

    $result = makeValidator()->validate($guide, $vocab, $acyclicProfile);

    expect($result->hasCode('graph.cycle'))->toBeTrue();
});

it('rejects a non-outcome dead end', function (): void {
    $guide = GuideBuilder::make('dead')
        ->question('q1', 'Employed?', 'employed', 'boolean')
        ->decision('d1')
        ->outcome('yes', 'Yes')
        ->edge('q1', 'yes', 'true')
        ->edge('q1', 'd1', 'false')
        ->build();

    $result = makeValidator()->validate($guide, FakeFactProvider::make()->vocabulary());

    expect($result->hasCode('graph.non_outcome_leaf'))->toBeTrue();
});

it('rejects an orphan node', function (): void {
    $guide = GuideBuilder::make('orphaned')
        ->question('q1', 'Employed?', 'employed', 'boolean')
        ->outcome('yes', 'Yes')
        ->outcome('no', 'No')
        ->outcome('lonely', 'Lonely')
        ->edge('q1', 'yes', 'true')
        ->edge('q1', 'no', 'false')
        ->build();

    $result = makeValidator()->validate($guide, FakeFactProvider::make()->vocabulary());

    expect($result->hasCode('graph.orphan_node'))->toBeTrue();
});

it('rejects an expression that references a fact outside the vocabulary', function (): void {
    $guide = GuideBuilder::make('expr')
        ->fact('f1', 'tenure')
        ->decision('d1')
        ->outcome('a', 'A')
        ->outcome('b', 'B')
        ->edge('f1', 'd1')
        ->edge('d1', 'a', 'out', Condition::expression('ghost > 1'))
        ->edge('d1', 'b', 'out', Condition::always())
        ->build();

    $result = makeValidator()->validate($guide, FakeFactProvider::make()->declare('tenure', FactType::Number)->vocabulary());

    expect($result->hasCode('fact.invalid_expression'))->toBeTrue();
});
