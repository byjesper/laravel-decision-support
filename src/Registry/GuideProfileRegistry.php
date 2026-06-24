<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Registry;

use ByJesper\DecisionSupport\Contracts\GuideProfile;

/**
 * The publish-time profiles a guide may declare. Ships with `phased` and
 * `freeform`; hosts can register more.
 */
final class GuideProfileRegistry
{
    /** @var array<string, GuideProfile> */
    private array $profiles = [];

    public function register(GuideProfile $profile): void
    {
        $this->profiles[$profile->key()] = $profile;
    }

    public function has(string $key): bool
    {
        return isset($this->profiles[$key]);
    }

    public function get(string $key): ?GuideProfile
    {
        return $this->profiles[$key] ?? null;
    }

    /** @return array<string, GuideProfile> */
    public function all(): array
    {
        return $this->profiles;
    }
}
