<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Registry;

use ByJesper\DecisionSupport\Contracts\NodeType;

/**
 * The set of node types the engine knows about. Hosts register custom types in
 * their service provider's boot(); the four built-ins are registered by the
 * package provider.
 */
final class NodeTypeRegistry
{
    /** @var array<string, NodeType> */
    private array $types = [];

    public function register(NodeType $type): void
    {
        $this->types[$type->key()] = $type;
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    public function get(string $key): ?NodeType
    {
        return $this->types[$key] ?? null;
    }

    /** @return array<string, NodeType> */
    public function all(): array
    {
        return $this->types;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->types);
    }
}
