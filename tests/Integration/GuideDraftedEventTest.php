<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Enums\VersionStatus;
use ByJesper\DecisionSupport\Events\GuideDrafted;
use ByJesper\DecisionSupport\Models\Guide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('dispatches GuideDrafted when a draft version is created', function (): void {
    Event::fake([GuideDrafted::class]);

    $guide = Guide::create(['key' => 'eligibility', 'name' => 'Eligibility', 'profile' => 'phased']);
    $guide->versions()->create(['number' => 3, 'status' => VersionStatus::Draft]);

    Event::assertDispatched(
        GuideDrafted::class,
        static fn (GuideDrafted $event): bool => $event->guideKey === 'eligibility' && $event->version === 3,
    );
})->group('integration');

it('does not dispatch GuideDrafted for a non-draft version', function (): void {
    Event::fake([GuideDrafted::class]);

    $guide = Guide::create(['key' => 'eligibility', 'name' => 'Eligibility', 'profile' => 'phased']);
    $guide->versions()->create(['number' => 1, 'status' => VersionStatus::Published]);

    Event::assertNotDispatched(GuideDrafted::class);
})->group('integration');
