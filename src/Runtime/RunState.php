<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Runtime;

use ByJesper\DecisionSupport\Definition\GuideDefinition;
use ByJesper\DecisionSupport\Enums\RunStatus;

/**
 * A serializable snapshot of a guide run. Plain `toArray()`/`fromArray()` (no
 * spatie dependency) so it survives Livewire round-trips and session storage.
 * Identifies its guide by key + version; the runner re-loads the matching
 * {@see GuideDefinition} to advance.
 */
final readonly class RunState
{
    /** @param list<string> $path visited node keys, in order, including the entry node */
    public function __construct(
        public string $guideKey,
        public int $version,
        public ?string $currentNode,
        public RunStatus $status,
        public GuideContext $context,
        public array $path,
        public ?Outcome $outcome = null,
        public ?Interaction $pendingInteraction = null,
        public int $steps = 0,
    ) {}

    public function isRunning(): bool
    {
        return $this->status === RunStatus::Running;
    }

    public function isSuspended(): bool
    {
        return $this->status === RunStatus::Suspended;
    }

    public function isCompleted(): bool
    {
        return $this->status === RunStatus::Completed;
    }

    public function hasVisited(string $nodeKey): bool
    {
        return in_array($nodeKey, $this->path, true);
    }

    public function withContext(GuideContext $context): self
    {
        return $this->copy(context: $context);
    }

    public function withStatus(RunStatus $status): self
    {
        return $this->copy(status: $status);
    }

    /** Advance to a node: record it on the path and bump the step counter. */
    public function moveTo(string $nodeKey): self
    {
        return $this->copy(
            currentNode: $nodeKey,
            status: RunStatus::Running,
            path: [...$this->path, $nodeKey],
            steps: $this->steps + 1,
        );
    }

    public function suspend(Interaction $interaction): self
    {
        return $this->copy(status: RunStatus::Suspended, pendingInteraction: $interaction);
    }

    public function complete(Outcome $outcome): self
    {
        return $this->copy(status: RunStatus::Completed, outcome: $outcome, clearInteraction: true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'guideKey' => $this->guideKey,
            'version' => $this->version,
            'currentNode' => $this->currentNode,
            'status' => $this->status->value,
            'context' => $this->context->toArray(),
            'path' => $this->path,
            'outcome' => $this->outcome?->toArray(),
            'pendingInteraction' => $this->pendingInteraction?->toArray(),
            'steps' => $this->steps,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var list<string> $path */
        $path = is_array($data['path'] ?? null) ? array_values($data['path']) : [];
        /** @var array<string, mixed> $context */
        $context = is_array($data['context'] ?? null) ? $data['context'] : [];

        return new self(
            guideKey: is_string($data['guideKey'] ?? null) ? $data['guideKey'] : '',
            version: is_int($data['version'] ?? null) ? $data['version'] : 1,
            currentNode: is_string($data['currentNode'] ?? null) ? $data['currentNode'] : null,
            status: RunStatus::from(is_string($data['status'] ?? null) ? $data['status'] : 'running'),
            context: GuideContext::fromArray($context),
            path: $path,
            outcome: is_array($data['outcome'] ?? null) ? Outcome::fromArray($data['outcome']) : null,
            pendingInteraction: is_array($data['pendingInteraction'] ?? null)
                ? Interaction::fromArray($data['pendingInteraction'])
                : null,
            steps: is_int($data['steps'] ?? null) ? $data['steps'] : 0,
        );
    }

    /** @param list<string>|null $path */
    private function copy(
        ?string $currentNode = null,
        ?RunStatus $status = null,
        ?GuideContext $context = null,
        ?array $path = null,
        ?Outcome $outcome = null,
        ?Interaction $pendingInteraction = null,
        ?int $steps = null,
        bool $clearInteraction = false,
    ): self {
        return new self(
            guideKey: $this->guideKey,
            version: $this->version,
            currentNode: $currentNode ?? $this->currentNode,
            status: $status ?? $this->status,
            context: $context ?? $this->context,
            path: $path ?? $this->path,
            outcome: $outcome ?? $this->outcome,
            pendingInteraction: $clearInteraction ? null : ($pendingInteraction ?? $this->pendingInteraction),
            steps: $steps ?? $this->steps,
        );
    }
}
