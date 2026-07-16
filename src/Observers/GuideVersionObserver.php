<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Observers;

use ByJesper\DecisionSupport\Enums\VersionStatus;
use ByJesper\DecisionSupport\Events\GuideDrafted;
use ByJesper\DecisionSupport\Models\GuideVersion;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Dispatches {@see GuideDrafted} whenever a draft version is created — for any
 * writer (the Filament editor, a host's custom editor, seeders, artisan
 * commands), with no editor dependency. Non-draft creations (e.g. a version
 * seeded directly as published) do not fire it.
 */
final readonly class GuideVersionObserver
{
    public function __construct(private Dispatcher $events) {}

    public function created(GuideVersion $version): void
    {
        if ($version->status !== VersionStatus::Draft) {
            return;
        }

        $version->loadMissing('guide');

        $this->events->dispatch(new GuideDrafted($version->guide->key, $version->number));
    }
}
