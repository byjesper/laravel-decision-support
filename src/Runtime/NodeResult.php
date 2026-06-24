<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Runtime;

/**
 * The outcome of evaluating a single node. Exactly one of the three shapes:
 * Advance (with a port and an optional context patch), Suspend (with the
 * interaction to render), or Terminate (with the final outcome).
 */
final readonly class NodeResult
{
    /**
     * @param  array{answers?: array<string, mixed>, facts?: array<string, mixed>}  $contextPatch
     */
    private function __construct(
        public NodeResultType $type,
        public ?string $port = null,
        public array $contextPatch = [],
        public ?Interaction $interaction = null,
        public ?Outcome $outcome = null,
    ) {}

    /**
     * @param  array{answers?: array<string, mixed>, facts?: array<string, mixed>}  $contextPatch
     */
    public static function advance(string $port, array $contextPatch = []): self
    {
        return new self(NodeResultType::Advance, port: $port, contextPatch: $contextPatch);
    }

    public static function suspend(Interaction $interaction): self
    {
        return new self(NodeResultType::Suspend, interaction: $interaction);
    }

    public static function terminate(Outcome $outcome): self
    {
        return new self(NodeResultType::Terminate, outcome: $outcome);
    }
}
