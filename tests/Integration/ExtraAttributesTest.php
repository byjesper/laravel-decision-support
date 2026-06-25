<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\DecisionSupportManager;
use ByJesper\DecisionSupport\Enums\FactType;
use ByJesper\DecisionSupport\Enums\VersionStatus;
use ByJesper\DecisionSupport\Models\Guide;
use ByJesper\DecisionSupport\Models\GuideVersion;
use ByJesper\DecisionSupport\Publishing\GuidePublisher;
use ByJesper\DecisionSupport\Testing\FakeFactProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Seed a minimal publishable boolean guide as draft rows. */
function seedGuideForExtras(): GuideVersion
{
    app(DecisionSupportManager::class)->registerProvider(
        'eligibility',
        FakeFactProvider::make()->declare('employed', FactType::Boolean),
    );

    $guide = Guide::create(['key' => 'eligibility', 'name' => 'Eligibility', 'profile' => 'phased']);
    $version = $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Draft]);

    $q = $version->nodes()->create([
        'type' => 'question',
        'key' => 'q1',
        'config' => ['prompt' => 'Are you employed?', 'fact' => 'employed', 'inputType' => 'boolean'],
    ]);
    $yes = $version->nodes()->create(['type' => 'outcome', 'key' => 'yes', 'config' => ['verdict' => 'Eligible']]);
    $no = $version->nodes()->create(['type' => 'outcome', 'key' => 'no', 'config' => ['verdict' => 'Not eligible']]);

    $version->edges()->create(['from_node_id' => $q->id, 'to_node_id' => $yes->id, 'from_port' => 'true']);
    $version->edges()->create(['from_node_id' => $q->id, 'to_node_id' => $no->id, 'from_port' => 'false']);

    return $version;
}

it('persists and casts extra_attributes to an array on both models', function (): void {
    $guide = Guide::create([
        'key' => 'eligibility',
        'name' => 'Eligibility',
        'profile' => 'phased',
        'extra_attributes' => ['permissions' => ['view-guide']],
    ]);

    $version = $guide->versions()->create([
        'number' => 1,
        'status' => VersionStatus::Draft,
        'extra_attributes' => ['permissions' => ['run-guide']],
    ]);

    expect($guide->fresh()?->extra_attributes)->toBe(['permissions' => ['view-guide']])
        ->and($version->fresh()?->extra_attributes)->toBe(['permissions' => ['run-guide']]);
})->group('integration');

it('seeds the guide extra_attributes from the version when publishing', function (): void {
    $version = seedGuideForExtras();
    $version->update(['extra_attributes' => ['permissions' => ['run-guide']]]);

    $result = app(GuidePublisher::class)->publish($version);

    expect($result->passes())->toBeTrue()
        ->and($version->guide?->fresh()?->extra_attributes)->toBe(['permissions' => ['run-guide']]);
})->group('integration');

it('publishes a version that has no extra_attributes', function (): void {
    $version = seedGuideForExtras();

    $result = app(GuidePublisher::class)->publish($version);

    expect($result->passes())->toBeTrue()
        ->and($version->guide?->fresh()?->extra_attributes)->toBeNull();
})->group('integration');
