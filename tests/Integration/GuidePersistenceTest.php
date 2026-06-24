<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\DecisionSupportManager;
use ByJesper\DecisionSupport\Enums\FactType;
use ByJesper\DecisionSupport\Enums\VersionStatus;
use ByJesper\DecisionSupport\Events\GuidePublished;
use ByJesper\DecisionSupport\Events\GuideRunStarted;
use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupport\Models\GuideVersion;
use ByJesper\DecisionSupport\Publishing\GuidePublisher;
use ByJesper\DecisionSupport\Runtime\GuideRunner;
use ByJesper\DecisionSupport\Testing\FakeFactProvider;
use ByJesper\DecisionSupport\Testing\InteractsWithGuides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class, InteractsWithGuides::class);

function seedEligibilityGuide(): GuideVersion
{
    $guide = Guide::create(['key' => 'eligibility', 'name' => 'Eligibility', 'profile' => 'phased']);
    $version = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);

    $q1 = $version->nodes()->create([
        'type' => 'question',
        'key' => 'q1',
        'config' => ['prompt' => 'Are you employed?', 'fact' => 'employed', 'inputType' => 'boolean'],
    ]);
    $yes = $version->nodes()->create(['type' => 'outcome', 'key' => 'yes', 'config' => ['verdict' => 'Eligible']]);
    $no = $version->nodes()->create(['type' => 'outcome', 'key' => 'no', 'config' => ['verdict' => 'Not eligible']]);

    $version->edges()->create(['from_node_id' => $q1->id, 'to_node_id' => $yes->id, 'from_port' => 'true']);
    $version->edges()->create(['from_node_id' => $q1->id, 'to_node_id' => $no->id, 'from_port' => 'false']);

    return $version;
}

it('publishes a draft into an immutable snapshot and points the guide at it', function (): void {
    Event::fake([GuidePublished::class]);

    app(DecisionSupportManager::class)->registerProvider(
        'eligibility',
        FakeFactProvider::make()->declare('employed', FactType::Boolean),
    );

    $version = seedEligibilityGuide();

    $result = app(GuidePublisher::class)->publish($version);

    expect($result->passes())->toBeTrue();

    $version->refresh();
    expect($version->status)->toBe(VersionStatus::Published)
        ->and($version->definition)->not->toBeNull();

    expect($version->guide?->fresh()?->active_version_id)->toBe($version->id);

    Event::assertDispatched(GuidePublished::class);
})->group('integration');

it('runs a published guide resolved through the container', function (): void {
    Event::fake([GuideRunStarted::class]);

    app(DecisionSupportManager::class)->registerProvider(
        'eligibility',
        FakeFactProvider::make()->declare('employed', FactType::Boolean),
    );

    $version = seedEligibilityGuide();
    app(GuidePublisher::class)->publish($version);

    $definition = $version->fresh()?->toDefinition();
    expect($definition)->not->toBeNull();

    $runner = app(GuideRunner::class);
    $state = $runner->start($definition);

    $this->assertSuspendsForQuestion($state, 'q1');
    $this->assertReachesOutcome($runner->advance($definition, $state, true), 'Eligible');

    Event::assertDispatched(GuideRunStarted::class);
})->group('integration');
