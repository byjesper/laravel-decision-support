<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Definition\GuideDefinition;
use ByJesper\DecisionSupport\Enums\FactType;
use ByJesper\DecisionSupport\Runtime\GuideRunner;
use ByJesper\DecisionSupport\Runtime\RunState;
use ByJesper\DecisionSupport\Testing\FakeFactProvider;
use ByJesper\DecisionSupport\Testing\GuideBuilder;
use ByJesper\DecisionSupport\Testing\InteractsWithGuides;

uses(InteractsWithGuides::class);

function i18nGuide(): GuideDefinition
{
    return GuideBuilder::make('g')
        ->question('q', 'Are you employed?', 'employed', 'boolean', [], ['prompt_i18n' => ['da' => 'Er du ansat?']])
        ->outcome('yes', 'Eligible', 'You qualify.', [], [
            'verdict_i18n' => ['da' => 'Berettiget'],
            'text_i18n' => ['da' => 'Du kvalificerer.'],
        ])
        ->outcome('no', 'Not eligible')
        ->edge('q', 'yes', 'true')
        ->edge('q', 'no', 'false')
        ->entry('q')
        ->build();
}

function i18nRunner(object $test): GuideRunner
{
    return $test->decisionRunner('g', FakeFactProvider::make()->declare('employed', FactType::Boolean));
}

it('renders base content when no locale is set', function (): void {
    $guide = i18nGuide();
    $runner = i18nRunner($this);

    $state = $runner->start($guide);
    expect($state->pendingInteraction?->prompt)->toBe('Are you employed?');

    $state = $runner->advance($guide, $state, true);
    expect($state->outcome?->verdict)->toBe('Eligible')
        ->and($state->outcome?->text)->toBe('You qualify.');
});

it('renders the active locale for prompts and outcomes', function (): void {
    $guide = i18nGuide();
    $runner = i18nRunner($this);

    $state = $runner->start($guide, [], 'da');
    expect($state->pendingInteraction?->prompt)->toBe('Er du ansat?');

    $state = $runner->advance($guide, $state, true);
    expect($state->outcome?->verdict)->toBe('Berettiget')
        ->and($state->outcome?->text)->toBe('Du kvalificerer.');
});

it('falls through the fallback locale, then the base string', function (): void {
    $guide = i18nGuide();
    $runner = i18nRunner($this);

    // 'de' has no translation; fallback 'da' does → Danish prompt.
    $state = $runner->start($guide, [], 'de', 'da');
    expect($state->pendingInteraction?->prompt)->toBe('Er du ansat?');

    // The 'no' outcome has no translations at all → base verdict even under a locale.
    $state = $runner->advance($guide, $runner->start($guide, [], 'da'), false);
    expect($state->outcome?->verdict)->toBe('Not eligible');
});

it('localizes select option labels through label_i18n', function (): void {
    $guide = GuideBuilder::make('g')
        ->question('q', 'Pick one', 'choice', 'select', [
            ['value' => 'a', 'label' => 'Apple', 'label_i18n' => ['da' => 'Æble']],
            ['value' => 'b', 'label' => 'Banana'],
        ])
        ->outcome('a', 'A')
        ->outcome('b', 'B')
        ->edge('q', 'a', 'a')
        ->edge('q', 'b', 'b')
        ->entry('q')
        ->build();

    $runner = $this->decisionRunner('g', FakeFactProvider::make()->declare('choice', FactType::Text));

    $state = $runner->start($guide, [], 'da');

    expect($state->pendingInteraction?->options)->toBe([
        ['value' => 'a', 'label' => 'Æble'],
        ['value' => 'b', 'label' => 'Banana'],
    ]);
});

it('preserves the locale chain across serialization', function (): void {
    $guide = i18nGuide();
    $runner = i18nRunner($this);

    $state = $runner->start($guide, [], 'da', 'en');
    $restored = RunState::fromArray($state->toArray());

    expect($restored->context->locale)->toBe('da')
        ->and($restored->context->fallbackLocale)->toBe('en');

    // Advancing the round-tripped state still resolves the Danish outcome.
    $state = $runner->advance($guide, $restored, true);
    expect($state->outcome?->verdict)->toBe('Berettiget');
});
