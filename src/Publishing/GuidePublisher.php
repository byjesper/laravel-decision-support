<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Publishing;

use ByJesper\DecisionSupport\Enums\VersionStatus;
use ByJesper\DecisionSupport\Events\GuidePublished;
use ByJesper\DecisionSupport\Models\GuideVersion;
use ByJesper\DecisionSupport\Registry\FactProviderRegistry;
use ByJesper\DecisionSupport\Registry\GuideProfileRegistry;
use ByJesper\DecisionSupport\Validation\PublishValidator;
use ByJesper\DecisionSupport\Validation\ValidationResult;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;

/**
 * Runs the publish pipeline for a draft version. On success it freezes the draft
 * rows into the immutable `definition` snapshot the runtime reads, marks the
 * version published, and points the guide at it. Validation failures are
 * returned, never thrown, so the editor can surface them inline.
 */
final readonly class GuidePublisher
{
    public function __construct(
        private PublishValidator $validator,
        private GuideProfileRegistry $profiles,
        private FactProviderRegistry $providers,
        private ?Dispatcher $events = null,
    ) {}

    public function validate(GuideVersion $version): ValidationResult
    {
        $version->loadMissing('guide');

        $definition = $version->toDefinition();
        $vocabulary = $this->providers->for($version->guide->key)->vocabulary();
        $profile = $this->profiles->get($version->guide->profile);

        return $this->validator->validate($definition, $vocabulary, $profile);
    }

    public function publish(GuideVersion $version): ValidationResult
    {
        $result = $this->validate($version);

        if ($result->fails()) {
            return $result;
        }

        $definition = $version->toDefinition();
        $version->definition = $definition->toArray();
        $version->status = VersionStatus::Published;
        $version->published_at = Carbon::now();
        $version->save();

        $guide = $version->guide;
        $guide->active_version_id = $version->id;
        $guide->save();

        $this->events?->dispatch(new GuidePublished($definition->guideKey, $definition->version));

        return $result;
    }
}
