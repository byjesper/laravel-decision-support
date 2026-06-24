<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Enums\RunStatus;
use ByJesper\DecisionSupport\Runtime\GuideContext;
use ByJesper\DecisionSupport\Runtime\Interaction;
use ByJesper\DecisionSupport\Runtime\Outcome;
use ByJesper\DecisionSupport\Runtime\RunState;

it('round-trips a suspended state through array form', function (): void {
    $state = new RunState(
        guideKey: 'eligibility',
        version: 2,
        currentNode: 'q2',
        status: RunStatus::Suspended,
        context: new GuideContext(answers: ['a' => true], facts: ['tenure' => 6]),
        path: ['q1', 'q2'],
        pendingInteraction: new Interaction('q2', 'question', 'Second?', 'boolean'),
        steps: 1,
    );

    $restored = RunState::fromArray($state->toArray());

    expect($restored->guideKey)->toBe('eligibility')
        ->and($restored->version)->toBe(2)
        ->and($restored->currentNode)->toBe('q2')
        ->and($restored->status)->toBe(RunStatus::Suspended)
        ->and($restored->context->answer('a'))->toBeTrue()
        ->and($restored->context->fact('tenure'))->toBe(6)
        ->and($restored->path)->toBe(['q1', 'q2'])
        ->and($restored->pendingInteraction?->prompt)->toBe('Second?')
        ->and($restored->steps)->toBe(1);
});

it('round-trips a completed state with an outcome', function (): void {
    $state = new RunState(
        guideKey: 'eligibility',
        version: 1,
        currentNode: 'done',
        status: RunStatus::Completed,
        context: new GuideContext,
        path: ['q1', 'done'],
        outcome: new Outcome('done', 'Eligible', 'You qualify', ['Check paperwork']),
    );

    $restored = RunState::fromArray($state->toArray());

    expect($restored->isCompleted())->toBeTrue()
        ->and($restored->outcome?->verdict)->toBe('Eligible')
        ->and($restored->outcome?->warnings)->toBe(['Check paperwork']);
});
