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
