<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport;

use ByJesper\DecisionSupport\Contracts\FactProvider;
use ByJesper\DecisionSupport\Contracts\GuideProfile;
use ByJesper\DecisionSupport\Contracts\NodeType;
use ByJesper\DecisionSupport\Registry\FactProviderRegistry;
use ByJesper\DecisionSupport\Registry\GuideProfileRegistry;
use ByJesper\DecisionSupport\Registry\NodeTypeRegistry;

/**
 * The host-facing entry point. Hosts call these from their service provider's
 * boot() to wire the engine to their domain: register a fact provider per
 * guide, add custom node types, register profiles.
 */
final readonly class DecisionSupportManager
{
    public function __construct(
        private NodeTypeRegistry $nodeTypes,
        private FactProviderRegistry $providers,
        private GuideProfileRegistry $profiles,
    ) {}

    public function registerNodeType(NodeType $type): self
    {
        $this->nodeTypes->register($type);

        return $this;
    }

    /** @param FactProvider|class-string<FactProvider> $provider */
    public function registerProvider(string $guideKey, FactProvider|string $provider): self
    {
        $this->providers->register($guideKey, $provider);

        return $this;
    }

    public function registerProfile(GuideProfile $profile): self
    {
        $this->profiles->register($profile);

        return $this;
    }

    public function nodeTypes(): NodeTypeRegistry
    {
        return $this->nodeTypes;
    }

    public function providers(): FactProviderRegistry
    {
        return $this->providers;
    }

    public function profiles(): GuideProfileRegistry
    {
        return $this->profiles;
    }
}
