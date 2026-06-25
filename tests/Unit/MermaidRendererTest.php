<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Conditions\Condition;
use ByJesper\DecisionSupport\Enums\Operator;
use ByJesper\DecisionSupport\Enums\RunStatus;
use ByJesper\DecisionSupport\Mermaid\MermaidRenderer;
use ByJesper\DecisionSupport\Runtime\GuideContext;
use ByJesper\DecisionSupport\Runtime\RunState;
use ByJesper\DecisionSupport\Testing\GuideBuilder;

it('renders a flowchart with node shapes and edge labels', function (): void {
    $guide = GuideBuilder::make('eligibility')
        ->question('q1', 'Employed?', 'employed', 'boolean')
        ->decision('d1', 'tenure')
        ->outcome('yes', 'Eligible')
        ->outcome('no', 'Not eligible')
        ->edge('q1', 'd1', 'true')
        ->edge('q1', 'no', 'false')
        ->edge('d1', 'yes', 'out', Condition::structured('tenure', Operator::GreaterThanOrEqual, 5))
        ->edge('d1', 'no', 'out', Condition::always())
        ->build();

    $mermaid = (new MermaidRenderer)->render($guide);

    expect($mermaid)
        ->toStartWith('flowchart TD')
        ->toContain('n_q1{"Employed?"}')
        ->toContain('n_yes(["Eligible"])')
        ->toContain('n_q1 -->|"true"| n_d1')
        ->toContain('n_d1 -->|"tenure >= 5"| n_yes')
        ->toContain('n_d1 -->|"else"| n_no');
});

it('renders node text in the requested locale, falling back to base', function (): void {
    $guide = GuideBuilder::make('eligibility')
        ->question('q1', 'Employed?', 'employed', 'boolean', i18n: ['prompt_i18n' => ['da' => 'Ansat?']])
        ->fact('cp', 'overlaps_any_cp', label: 'Overlaps a CP', labelI18n: ['da' => 'Overlapper en BP'])
        ->outcome('yes', 'Eligible', i18n: ['verdict_i18n' => ['da' => 'Berettiget']])
        ->edge('q1', 'cp', 'true')
        ->edge('cp', 'yes', 'out')
        ->build();

    $renderer = new MermaidRenderer;

    // Danish localizes prompt, label and verdict.
    expect($renderer->render($guide, null, 'da'))
        ->toContain('n_q1{"Ansat?"}')
        ->toContain('n_cp[/"Overlapper en BP"/]')
        ->toContain('n_yes(["Berettiget"])');

    // A locale with no translation falls back to the base strings.
    expect($renderer->render($guide, null, 'de'))
        ->toContain('n_q1{"Employed?"}')
        ->toContain('n_cp[/"Overlaps a CP"/]')
        ->toContain('n_yes(["Eligible"])');

    // No locale at all => base behaviour (a bare fact node still shows its label).
    expect($renderer->render($guide))->toContain('n_cp[/"Overlaps a CP"/]');
});

it('renders a custom edge label, localized, overriding the derived condition label', function (): void {
    $guide = GuideBuilder::make('eligibility')
        ->decision('d1', 'tenure')
        ->outcome('yes', 'Eligible')
        ->outcome('no', 'No')
        ->edge('d1', 'yes', 'out', Condition::structured('tenure', Operator::GreaterThanOrEqual, 5), 'Long tenure', ['da' => 'Lang anciennitet'])
        ->edge('d1', 'no', 'out', Condition::always())
        ->build();

    $renderer = new MermaidRenderer;

    // Without the label the derived condition text would be "tenure >= 5".
    expect($renderer->render($guide))->toContain('n_d1 -->|"Long tenure"| n_yes');
    expect($renderer->render($guide, null, 'da'))->toContain('n_d1 -->|"Lang anciennitet"| n_yes');

    // The unlabelled default edge still derives its label.
    expect($renderer->render($guide))->toContain('n_d1 -->|"else"| n_no');
});

it('derives the diagram locale from the highlighted run state', function (): void {
    $guide = GuideBuilder::make('eligibility')
        ->question('q1', 'Employed?', 'employed', 'boolean', i18n: ['prompt_i18n' => ['da' => 'Ansat?']])
        ->outcome('yes', 'Eligible', i18n: ['verdict_i18n' => ['da' => 'Berettiget']])
        ->edge('q1', 'yes', 'true')
        ->build();

    $state = new RunState(
        guideKey: 'eligibility',
        version: 1,
        currentNode: 'yes',
        status: RunStatus::Completed,
        context: new GuideContext(locale: 'da'),
        path: ['q1', 'yes'],
    );

    // No explicit locale passed — the renderer reads it off the run state.
    expect((new MermaidRenderer)->render($guide, $state))
        ->toContain('n_q1{"Ansat?"}')
        ->toContain('n_yes(["Berettiget"])');
});

it('overlays the reached path when given a run state', function (): void {
    $guide = GuideBuilder::make('eligibility')
        ->question('q1', 'Employed?', 'employed', 'boolean')
        ->outcome('yes', 'Eligible')
        ->outcome('no', 'No')
        ->edge('q1', 'yes', 'true')
        ->edge('q1', 'no', 'false')
        ->build();

    $state = new RunState(
        guideKey: 'eligibility',
        version: 1,
        currentNode: 'yes',
        status: RunStatus::Completed,
        context: new GuideContext,
        path: ['q1', 'yes'],
    );

    $mermaid = (new MermaidRenderer)->render($guide, $state);

    expect($mermaid)
        ->toContain('classDef reached')
        ->toContain('class n_q1,n_yes reached;');
});
