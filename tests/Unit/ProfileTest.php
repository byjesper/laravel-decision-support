<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Profiles\FreeformProfile;
use ByJesper\DecisionSupport\Profiles\PhasedProfile;
use ByJesper\DecisionSupport\Testing\GuideBuilder;

it('accepts a forward-only flow under the phased profile', function (): void {
    $guide = GuideBuilder::make('phased')
        ->question('q1', 'Employed?', 'employed', 'boolean')
        ->fact('f1', 'tenure')
        ->decision('d1')
        ->outcome('yes', 'Yes')
        ->outcome('no', 'No')
        ->edge('q1', 'f1', 'true')
        ->edge('q1', 'no', 'false')
        ->edge('f1', 'd1')
        ->edge('d1', 'yes', 'out')
        ->build();

    expect((new PhasedProfile)->validate($guide)->passes())->toBeTrue();
});

it('rejects an edge that moves backwards across phases', function (): void {
    $guide = GuideBuilder::make('backwards')
        ->fact('f1', 'tenure')
        ->question('q1', 'Employed?', 'employed', 'boolean')
        ->outcome('yes', 'Yes')
        ->outcome('no', 'No')
        ->edge('f1', 'q1')
        ->edge('q1', 'yes', 'true')
        ->edge('q1', 'no', 'false')
        ->build();

    $result = (new PhasedProfile)->validate($guide);

    expect($result->hasCode('profile.phase_order'))->toBeTrue();
});

it('permits any ordering under the freeform profile', function (): void {
    $guide = GuideBuilder::make('freeform')
        ->fact('f1', 'tenure')
        ->question('q1', 'Employed?', 'employed', 'boolean')
        ->outcome('yes', 'Yes')
        ->outcome('no', 'No')
        ->edge('f1', 'q1')
        ->edge('q1', 'yes', 'true')
        ->edge('q1', 'no', 'false')
        ->build();

    expect((new FreeformProfile)->validate($guide)->passes())->toBeTrue();
});
