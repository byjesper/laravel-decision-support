<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Events;

use ByJesper\DecisionSupport\Runtime\RunState;

/**
 * Emitted when a run begins. Hosts wire their own audit log (hrtools6 owen-it,
 * Spring's AuditLogService) — the package ships no audit dependency.
 */
final readonly class GuideRunStarted
{
    public function __construct(public RunState $state) {}
}
