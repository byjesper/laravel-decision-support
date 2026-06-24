<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Definition;

/**
 * The immutable, runtime-facing snapshot of a guide version. Built once at
 * publish time and stored as JSON in `guide_versions.definition`; the runner
 * reads only this, never the draft rows.
 */
final readonly class GuideDefinition
{
    /** @var array<string, NodeDefinition> */
    public array $nodes;

    /**
     * @param  list<NodeDefinition>  $nodes
     * @param  list<EdgeDefinition>  $edges
     */
    public function __construct(
        public string $guideKey,
        public int $version,
        public string $profile,
        public string $entryNode,
        array $nodes,
        public array $edges,
    ) {
        $keyed = [];
        foreach ($nodes as $node) {
            $keyed[$node->key] = $node;
        }

        $this->nodes = $keyed;
    }

    public function node(string $key): ?NodeDefinition
    {
        return $this->nodes[$key] ?? null;
    }

    public function entry(): ?NodeDefinition
    {
        return $this->node($this->entryNode);
    }

    /** @return list<EdgeDefinition> */
    public function edgesFrom(string $nodeKey): array
    {
        return array_values(array_filter(
            $this->edges,
            static fn (EdgeDefinition $edge): bool => $edge->from === $nodeKey,
        ));
    }

    /** @return list<EdgeDefinition> */
    public function edgesTo(string $nodeKey): array
    {
        return array_values(array_filter(
            $this->edges,
            static fn (EdgeDefinition $edge): bool => $edge->to === $nodeKey,
        ));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'guideKey' => $this->guideKey,
            'version' => $this->version,
            'profile' => $this->profile,
            'entryNode' => $this->entryNode,
            'nodes' => array_map(static fn (NodeDefinition $n): array => $n->toArray(), array_values($this->nodes)),
            'edges' => array_map(static fn (EdgeDefinition $e): array => $e->toArray(), $this->edges),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var list<array<string, mixed>> $rawNodes */
        $rawNodes = is_array($data['nodes'] ?? null) ? array_values($data['nodes']) : [];
        /** @var list<array<string, mixed>> $rawEdges */
        $rawEdges = is_array($data['edges'] ?? null) ? array_values($data['edges']) : [];

        return new self(
            guideKey: is_string($data['guideKey'] ?? null) ? $data['guideKey'] : '',
            version: is_int($data['version'] ?? null) ? $data['version'] : 1,
            profile: is_string($data['profile'] ?? null) ? $data['profile'] : 'freeform',
            entryNode: is_string($data['entryNode'] ?? null) ? $data['entryNode'] : '',
            nodes: array_map(NodeDefinition::fromArray(...), $rawNodes),
            edges: array_map(EdgeDefinition::fromArray(...), $rawEdges),
        );
    }
}
