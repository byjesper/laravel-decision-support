<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Contracts;

/**
 * Marker interface for {@see GuideProfile}s that permit cycles in the graph.
 *
 * A profile that implements this opts out of the acyclic publish check and,
 * at runtime, out of the revisit guard — instead the run is bounded solely by
 * the configurable step budget (`decision-support.max_steps`). It is a marker
 * (no methods) so adding cycle support to a profile never breaks the
 * host-implemented {@see GuideProfile} contract.
 */
interface SupportsCycles {}
