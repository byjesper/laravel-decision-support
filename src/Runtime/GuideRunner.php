<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Runtime;

use ByJesper\DecisionSupport\Contracts\ConditionEvaluator;
use ByJesper\DecisionSupport\Definition\EdgeDefinition;
use ByJesper\DecisionSupport\Definition\GuideDefinition;
use ByJesper\DecisionSupport\Enums\RunStatus;
use ByJesper\DecisionSupport\Events\GuideRunStarted;
use ByJesper\DecisionSupport\Registry\FactProviderRegistry;
use ByJesper\DecisionSupport\Registry\NodeTypeRegistry;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * The resumable interpreter. `start()` and `advance()` drive a run forward
 * through automatic nodes (fact/decision/outcome) until it needs host input
 * (Suspend) or finishes (Terminate). Both take an explicit
 * {@see GuideDefinition} so a run can be evaluated headless, without the DB.
 *
 * Safety rails — the runtime never throws on bad guide data:
 *  - a step budget caps total transitions,
 *  - a visited-set guards against cycles,
 *  - an unroutable transition (missing edge, unresolved fact with no default)
 *    terminates with an `unknown` outcome rather than an exception.
 */
final readonly class GuideRunner
{
    public function __construct(
        private NodeTypeRegistry $nodeTypes,
        private FactProviderRegistry $providers,
        private ConditionEvaluator $conditions,
        private ?Dispatcher $events = null,
        private int $maxSteps = 200,
    ) {}

    /** @param array<string, mixed> $answers */
    public function start(GuideDefinition $definition, array $answers = []): RunState
    {
        $context = new GuideContext(answers: $answers, facts: $answers);

        $state = new RunState(
            guideKey: $definition->guideKey,
            version: $definition->version,
            currentNode: $definition->entryNode,
            status: RunStatus::Running,
            context: $context,
            path: [$definition->entryNode],
        );

        $this->events?->dispatch(new GuideRunStarted($state));

        return $this->run($definition, $state, null);
    }

    public function advance(GuideDefinition $definition, RunState $state, mixed $input = null): RunState
    {
        if ($state->isCompleted() || $state->currentNode === null) {
            return $state;
        }

        return $this->run($definition, $state->withStatus(RunStatus::Running), $input);
    }

    private function run(GuideDefinition $definition, RunState $state, mixed $input): RunState
    {
        while (true) {
            if ($state->steps > $this->maxSteps) {
                return $this->terminateUnknown($state, 'Step budget exceeded.');
            }

            $nodeKey = $state->currentNode;
            if ($nodeKey === null) {
                return $this->terminateUnknown($state, 'Run reached a null node.');
            }

            $node = $definition->node($nodeKey);
            if ($node === null) {
                return $this->terminateUnknown($state, "Unknown node '{$nodeKey}'.");
            }

            $nodeType = $this->nodeTypes->get($node->type);
            if ($nodeType === null) {
                return $this->terminateUnknown($state, "Unknown node type '{$node->type}'.");
            }

            $evaluation = new EvaluationContext(
                definition: $definition,
                state: $state,
                facts: $this->providers->for($definition->guideKey),
                conditions: $this->conditions,
                input: $input,
            );

            $result = $nodeType->evaluate($node, $evaluation);
            $input = null;

            switch ($result->type) {
                case NodeResultType::Suspend:
                    return $state->suspend($result->interaction ?? $this->fallbackInteraction($nodeKey));

                case NodeResultType::Terminate:
                    return $state->complete($result->outcome ?? Outcome::unknown($nodeKey, 'Node terminated without an outcome.'));

                case NodeResultType::Advance:
                    $state = $state->withContext($this->applyPatch($state->context, $result->contextPatch));
                    $target = $this->selectTarget($definition, $state, $nodeKey, $result->port ?? 'out');

                    if ($target === null) {
                        return $this->terminateUnknown($state, "No outgoing edge from '{$nodeKey}' via port '{$result->port}'.");
                    }

                    if ($state->hasVisited($target)) {
                        return $this->terminateUnknown($state, "Cycle detected re-entering '{$target}'.");
                    }

                    $state = $state->moveTo($target);

                    continue 2;
            }
        }
    }

    private function selectTarget(GuideDefinition $definition, RunState $state, string $nodeKey, string $port): ?string
    {
        $edges = array_values(array_filter(
            $definition->edgesFrom($nodeKey),
            static fn (EdgeDefinition $edge): bool => $edge->fromPort === $port,
        ));

        $default = null;

        foreach ($edges as $edge) {
            if ($edge->isDefault()) {
                $default ??= $edge;

                continue;
            }

            if ($edge->condition !== null && $this->conditions->matches($edge->condition, $state->context)) {
                return $edge->to;
            }
        }

        return $default?->to;
    }

    /**
     * @param  array{answers?: array<string, mixed>, facts?: array<string, mixed>}  $patch
     */
    private function applyPatch(GuideContext $context, array $patch): GuideContext
    {
        foreach ($patch['answers'] ?? [] as $key => $value) {
            $context = $context->withAnswer($key, $value);
        }

        foreach ($patch['facts'] ?? [] as $key => $value) {
            $context = $context->withFact($key, $value);
        }

        return $context;
    }

    private function terminateUnknown(RunState $state, string $reason): RunState
    {
        return $state->complete(Outcome::unknown($state->currentNode ?? 'unknown', $reason));
    }

    private function fallbackInteraction(string $nodeKey): Interaction
    {
        return new Interaction(nodeKey: $nodeKey, kind: 'question', prompt: '', inputType: 'text');
    }
}
