<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Conditions\Condition;
use ByJesper\DecisionSupport\Enums\Operator;
use ByJesper\DecisionSupport\Runtime\Interaction;
use ByJesper\DecisionSupport\Testing\FakeFactProvider;
use ByJesper\DecisionSupport\Testing\GuideBuilder;
use ByJesper\DecisionSupport\Testing\InteractsWithGuides;

uses(InteractsWithGuides::class);

it('suspends for a question and terminates on the matching boolean port', function (): void {
    $guide = GuideBuilder::make('eligibility')
        ->question('q1', 'Are you employed?', 'employed', 'boolean')
        ->outcome('yes', 'Eligible')
        ->outcome('no', 'Not eligible')
        ->edge('q1', 'yes', 'true')
        ->edge('q1', 'no', 'false')
        ->build();

    $runner = $this->decisionRunner('eligibility', FakeFactProvider::make());

    $state = $runner->start($guide);
    $this->assertSuspendsForQuestion($state, 'q1');

    $this->assertReachesOutcome($runner->advance($guide, $state, true), 'Eligible');
    $this->assertReachesOutcome($runner->advance($guide, $state, false), 'Not eligible');
});

it('resolves a fact and routes a decision without suspending', function (): void {
    $guide = GuideBuilder::make('tenure')
        ->fact('f1', 'tenure_years')
        ->decision('d1', 'tenure_years')
        ->outcome('senior', 'Senior')
        ->outcome('junior', 'Junior')
        ->edge('f1', 'd1')
        ->edge('d1', 'senior', 'out', Condition::structured('tenure_years', Operator::GreaterThanOrEqual, 5))
        ->edge('d1', 'junior', 'out', Condition::always())
        ->build();

    $runner = $this->decisionRunner('tenure', FakeFactProvider::make()->with('tenure_years', 6));

    $this->assertReachesOutcome($runner->start($guide), 'Senior');
});

it('evaluates an expression condition', function (): void {
    $guide = GuideBuilder::make('tenure')
        ->fact('f1', 'tenure_years')
        ->decision('d1')
        ->outcome('senior', 'Senior')
        ->outcome('junior', 'Junior')
        ->edge('f1', 'd1')
        ->edge('d1', 'senior', 'out', Condition::expression('tenure_years >= 5'))
        ->edge('d1', 'junior', 'out', Condition::always())
        ->build();

    $runner = $this->decisionRunner('tenure', FakeFactProvider::make()->with('tenure_years', 3));

    $this->assertReachesOutcome($runner->start($guide), 'Junior');
});

it('routes an unresolved fact to the unknown branch without throwing', function (): void {
    $guide = GuideBuilder::make('colors')
        ->decision('d1')
        ->outcome('missing', 'Missing')
        ->outcome('known', 'Known')
        ->edge('d1', 'missing', 'out', Condition::unknown('color'))
        ->edge('d1', 'known', 'out', Condition::always())
        ->build();

    $runner = $this->decisionRunner('colors', FakeFactProvider::make());

    $this->assertReachesOutcome($runner->start($guide), 'Missing');
});

it('safely terminates with an unknown outcome when no edge matches', function (): void {
    $guide = GuideBuilder::make('dead-end')
        ->decision('d1')
        ->outcome('never', 'Never')
        ->edge('d1', 'never', 'out', Condition::structured('foo', Operator::Equals, 'bar'))
        ->build();

    $runner = $this->decisionRunner('dead-end', FakeFactProvider::make());

    $this->assertReachesUnknown($runner->start($guide));
});

it('guards against cycles', function (): void {
    $guide = GuideBuilder::make('loop')
        ->decision('a')
        ->decision('b')
        ->outcome('end', 'End')
        ->edge('a', 'b')
        ->edge('b', 'a')
        ->build();

    $runner = $this->decisionRunner('loop', FakeFactProvider::make());

    $this->assertReachesUnknown($runner->start($guide));
});

it('enforces the step budget', function (): void {
    $guide = GuideBuilder::make('chain')
        ->decision('a')
        ->decision('b')
        ->decision('c')
        ->decision('d')
        ->outcome('end', 'End')
        ->edge('a', 'b')
        ->edge('b', 'c')
        ->edge('c', 'd')
        ->edge('d', 'end')
        ->build();

    $runner = $this->decisionRunner('chain', FakeFactProvider::make(), maxSteps: 2);

    $this->assertReachesUnknown($runner->start($guide));
});

it('resumes across a question in the middle of the tree', function (): void {
    $guide = GuideBuilder::make('two-questions')
        ->question('q1', 'First?', 'a', 'boolean')
        ->question('q2', 'Second?', 'b', 'boolean')
        ->outcome('ok', 'Ok')
        ->outcome('stop', 'Stop')
        ->edge('q1', 'q2', 'true')
        ->edge('q1', 'stop', 'false')
        ->edge('q2', 'ok', 'true')
        ->edge('q2', 'stop', 'false')
        ->build();

    $runner = $this->decisionRunner('two-questions', FakeFactProvider::make());

    $first = $runner->start($guide);
    $this->assertSuspendsForQuestion($first, 'q1');

    $second = $runner->advance($guide, $first, true);
    $this->assertSuspendsForQuestion($second, 'q2');

    $this->assertReachesOutcome($runner->advance($guide, $second, true), 'Ok');
    expect($runner->advance($guide, $second, true)->path)->toContain('q1', 'q2', 'ok');
});

it('suspends when a fact provider needs host input, then consumes the resumed value', function (): void {
    $guide = GuideBuilder::make('lookup')
        ->fact('f1', 'employee')
        ->decision('d1')
        ->outcome('found', 'Found')
        ->outcome('none', 'None')
        ->edge('f1', 'd1')
        ->edge('d1', 'found', 'out', Condition::structured('employee', Operator::Equals, 'picked'))
        ->edge('d1', 'none', 'out', Condition::always())
        ->build();

    $interaction = new Interaction('f1', 'lookup', 'Pick an employee', 'select');
    $runner = $this->decisionRunner('lookup', FakeFactProvider::make()->pending('employee', $interaction));

    $state = $runner->start($guide);
    expect($state->isSuspended())->toBeTrue()
        ->and($state->pendingInteraction?->kind)->toBe('lookup');

    $this->assertReachesOutcome($runner->advance($guide, $state, 'picked'), 'Found');
});

it('routes a select question through the chosen option port', function (): void {
    $guide = GuideBuilder::make('color')
        ->question('q1', 'Pick a color', 'color', 'select', [
            ['value' => 'red', 'label' => 'Red'],
            ['value' => 'blue', 'label' => 'Blue'],
        ])
        ->outcome('warm', 'Warm')
        ->outcome('cool', 'Cool')
        ->edge('q1', 'warm', 'red')
        ->edge('q1', 'cool', 'blue')
        ->build();

    $runner = $this->decisionRunner('color', FakeFactProvider::make());
    $state = $runner->start($guide);

    $this->assertReachesOutcome($runner->advance($guide, $state, 'blue'), 'Cool');
});

it('keeps a required free question suspended until a non-blank answer arrives', function (): void {
    $guide = GuideBuilder::make('notes')
        ->question('q1', 'Any notes?', 'notes', 'text', [], [], required: true)
        ->outcome('done', 'Done')
        ->edge('q1', 'done', 'out')
        ->build();

    $runner = $this->decisionRunner('notes', FakeFactProvider::make());

    $state = $runner->start($guide);
    $this->assertSuspendsForQuestion($state, 'q1');
    expect($state->pendingInteraction?->required)->toBeTrue();

    // Blank answers (empty or whitespace-only) cannot advance a mandatory question.
    $this->assertSuspendsForQuestion($runner->advance($guide, $state, ''), 'q1');
    $this->assertSuspendsForQuestion($runner->advance($guide, $state, '   '), 'q1');

    // A real answer routes through to the outcome.
    $this->assertReachesOutcome($runner->advance($guide, $state, 'looks good'), 'Done');
});

it('advances an optional free question on a blank answer (the default)', function (): void {
    $guide = GuideBuilder::make('notes')
        ->question('q1', 'Any notes?', 'notes', 'text')
        ->outcome('done', 'Done')
        ->edge('q1', 'done', 'out')
        ->build();

    $runner = $this->decisionRunner('notes', FakeFactProvider::make());
    $state = $runner->start($guide);

    expect($state->pendingInteraction?->required)->toBeFalse();
    $this->assertReachesOutcome($runner->advance($guide, $state, ''), 'Done');
});

it('ignores the required flag for a boolean question', function (): void {
    $guide = GuideBuilder::make('q')
        ->question('q1', 'Employed?', 'employed', 'boolean', [], [], required: true)
        ->outcome('yes', 'Yes')
        ->outcome('no', 'No')
        ->edge('q1', 'yes', 'true')
        ->edge('q1', 'no', 'false')
        ->build();

    $runner = $this->decisionRunner('q', FakeFactProvider::make());
    $state = $runner->start($guide);

    // required is meaningless for boolean — the interaction reports false and a value still routes.
    expect($state->pendingInteraction?->required)->toBeFalse();
    $this->assertReachesOutcome($runner->advance($guide, $state, false), 'No');
});

it('round-trips the required flag through interaction serialization', function (): void {
    $interaction = new Interaction('q1', 'question', 'Any notes?', 'text', required: true);

    expect(Interaction::fromArray($interaction->toArray())->required)->toBeTrue()
        ->and(Interaction::fromArray(['nodeKey' => 'q1'])->required)->toBeFalse();
});
