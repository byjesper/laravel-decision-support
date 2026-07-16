<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Testing;

use ByJesper\DecisionSupport\Conditions\ConditionEvaluatorChain;
use ByJesper\DecisionSupport\Conditions\ExpressionConditionEvaluator;
use ByJesper\DecisionSupport\Conditions\StructuredConditionEvaluator;
use ByJesper\DecisionSupport\Contracts\FactProvider;
use ByJesper\DecisionSupport\NodeTypes\DecisionNode;
use ByJesper\DecisionSupport\NodeTypes\FactNode;
use ByJesper\DecisionSupport\NodeTypes\OutcomeNode;
use ByJesper\DecisionSupport\NodeTypes\QuestionNode;
use ByJesper\DecisionSupport\Registry\FactProviderRegistry;
use ByJesper\DecisionSupport\Registry\GuideProfileRegistry;
use ByJesper\DecisionSupport\Registry\NodeTypeRegistry;
use ByJesper\DecisionSupport\Runtime\GuideRunner;
use ByJesper\DecisionSupport\Runtime\RunState;
use PHPUnit\Framework\Assert;

/**
 * Pest/PHPUnit helpers for exercising guides headless — no DB, no editor. Build
 * a runner pre-wired with the four built-in node types and a provider, then
 * assert on the resulting {@see RunState}.
 */
trait InteractsWithGuides
{
    /**
     * Build a runner pre-wired with the four built-in node types and a provider.
     * Pass a {@see GuideProfileRegistry} to make the runner profile-aware — a
     * definition whose profile supports cycles then runs under the step budget
     * instead of the revisit guard. Omit it (the default) for today's acyclic
     * semantics.
     */
    public function decisionRunner(string $guideKey, FactProvider $provider, int $maxSteps = 200, ?GuideProfileRegistry $profiles = null): GuideRunner
    {
        $nodeTypes = new NodeTypeRegistry;
        $nodeTypes->register(new QuestionNode);
        $nodeTypes->register(new FactNode);
        $nodeTypes->register(new DecisionNode);
        $nodeTypes->register(new OutcomeNode);

        $providers = new FactProviderRegistry;
        $providers->register($guideKey, $provider);

        $conditions = new ConditionEvaluatorChain(
            new StructuredConditionEvaluator,
            new ExpressionConditionEvaluator,
        );

        return new GuideRunner($nodeTypes, $providers, $conditions, null, $maxSteps, $profiles);
    }

    public function assertReachesOutcome(RunState $state, string $verdict): RunState
    {
        Assert::assertTrue($state->isCompleted(), 'Expected the run to be completed.');
        Assert::assertNotNull($state->outcome, 'Expected the run to have an outcome.');
        Assert::assertSame($verdict, $state->outcome->verdict, "Expected outcome verdict '{$verdict}'.");

        return $state;
    }

    public function assertReachesUnknown(RunState $state): RunState
    {
        Assert::assertTrue($state->isCompleted(), 'Expected the run to be completed.');
        Assert::assertNotNull($state->outcome, 'Expected the run to have an outcome.');
        Assert::assertTrue($state->outcome->unknown, 'Expected the run to reach an unknown outcome.');

        return $state;
    }

    public function assertSuspendsForQuestion(RunState $state, ?string $nodeKey = null): RunState
    {
        Assert::assertTrue($state->isSuspended(), 'Expected the run to be suspended for input.');
        Assert::assertNotNull($state->pendingInteraction, 'Expected a pending interaction.');

        if ($nodeKey !== null) {
            Assert::assertSame($nodeKey, $state->pendingInteraction->nodeKey, "Expected to suspend at node '{$nodeKey}'.");
        }

        return $state;
    }
}
