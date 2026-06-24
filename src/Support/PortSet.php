<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Support;

/**
 * The named outputs a node may emit. Edges leave a node through one of these
 * ports; the publish validator checks every declared port has at least one
 * outgoing edge.
 */
final readonly class PortSet
{
    /** @param list<string> $ports */
    public function __construct(public array $ports) {}

    public static function of(string ...$ports): self
    {
        return new self(array_values($ports));
    }

    public static function none(): self
    {
        return new self([]);
    }

    public function has(string $port): bool
    {
        return in_array($port, $this->ports, true);
    }

    public function isEmpty(): bool
    {
        return $this->ports === [];
    }
}
