<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Testing\GuideBuilder;

it('indexes edges by origin, preserving insertion order', function (): void {
    // selectTarget() is first-matching-condition-wins, so per-origin order must
    // survive the index built in the constructor.
    $guide = GuideBuilder::make('g')
        ->decision('a')
        ->outcome('x', 'X')
        ->outcome('y', 'Y')
        ->edge('a', 'x', 'p1')
        ->edge('a', 'y', 'p2')
        ->build();

    $from = $guide->edgesFrom('a');

    expect($from)->toHaveCount(2)
        ->and($from[0]->to)->toBe('x')
        ->and($from[1]->to)->toBe('y')
        ->and($from[0]->fromPort)->toBe('p1')
        ->and($from[1]->fromPort)->toBe('p2');
});

it('returns an empty list for a node with no outgoing edges', function (): void {
    $guide = GuideBuilder::make('g')
        ->decision('a')
        ->outcome('x', 'X')
        ->edge('a', 'x')
        ->build();

    expect($guide->edgesFrom('x'))->toBe([])
        ->and($guide->edgesFrom('missing'))->toBe([]);
});

it('indexes edges by destination', function (): void {
    $guide = GuideBuilder::make('g')
        ->decision('a')
        ->decision('b')
        ->outcome('x', 'X')
        ->edge('a', 'x')
        ->edge('b', 'x')
        ->build();

    $to = $guide->edgesTo('x');

    expect($to)->toHaveCount(2)
        ->and($to[0]->from)->toBe('a')
        ->and($to[1]->from)->toBe('b')
        ->and($guide->edgesTo('missing'))->toBe([]);
});
