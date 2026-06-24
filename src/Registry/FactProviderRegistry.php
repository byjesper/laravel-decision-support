<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Registry;

use ByJesper\DecisionSupport\Contracts\FactProvider;
use ByJesper\DecisionSupport\Facts\NullFactProvider;
use Illuminate\Contracts\Container\Container;

/**
 * Binds one {@see FactProvider} per guide key. Providers may be registered as
 * an instance or a class-string resolved through the container, so hosts can
 * lean on constructor injection.
 */
final class FactProviderRegistry
{
    /** @var array<string, FactProvider|class-string<FactProvider>> */
    private array $providers = [];

    public function __construct(private readonly ?Container $container = null) {}

    /** @param FactProvider|class-string<FactProvider> $provider */
    public function register(string $guideKey, FactProvider|string $provider): void
    {
        $this->providers[$guideKey] = $provider;
    }

    public function has(string $guideKey): bool
    {
        return isset($this->providers[$guideKey]);
    }

    public function for(string $guideKey): FactProvider
    {
        $provider = $this->providers[$guideKey] ?? null;

        if ($provider instanceof FactProvider) {
            return $provider;
        }

        if (is_string($provider)) {
            assert($this->container !== null, 'A container is required to resolve a class-string fact provider.');
            $resolved = $this->container->make($provider);
            assert($resolved instanceof FactProvider);

            return $resolved;
        }

        return new NullFactProvider;
    }
}
