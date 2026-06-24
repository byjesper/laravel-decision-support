<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\DecisionSupportManager;
use ByJesper\DecisionSupport\NodeTypes\DecisionNode;
use ByJesper\DecisionSupport\NodeTypes\FactNode;
use ByJesper\DecisionSupport\NodeTypes\OutcomeNode;
use ByJesper\DecisionSupport\NodeTypes\QuestionNode;
use ByJesper\DecisionSupport\Profiles\FreeformProfile;
use ByJesper\DecisionSupport\Profiles\PhasedProfile;
use ByJesper\DecisionSupport\Runtime\GuideRunner;

it('merges the package configuration', function (): void {
    expect(config('decision-support.max_steps'))->toBe(200);
});

it('registers the four built-in node types', function (): void {
    $registry = app(DecisionSupportManager::class)->nodeTypes();

    expect($registry->has(QuestionNode::KEY))->toBeTrue()
        ->and($registry->has(FactNode::KEY))->toBeTrue()
        ->and($registry->has(DecisionNode::KEY))->toBeTrue()
        ->and($registry->has(OutcomeNode::KEY))->toBeTrue();
});

it('registers the built-in profiles', function (): void {
    $registry = app(DecisionSupportManager::class)->profiles();

    expect($registry->has(PhasedProfile::KEY))->toBeTrue()
        ->and($registry->has(FreeformProfile::KEY))->toBeTrue();
});

it('resolves the runner from the container', function (): void {
    expect(app(GuideRunner::class))->toBeInstanceOf(GuideRunner::class);
});
