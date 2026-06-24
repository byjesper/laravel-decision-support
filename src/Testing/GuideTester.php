<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Testing;

/**
 * A standalone object exposing the {@see InteractsWithGuides} helpers outside a
 * PHPUnit TestCase — useful for exercising a guide from a console command,
 * Tinker, or a Spring-style feature harness. Assertions still throw PHPUnit
 * assertion failures, so it pairs naturally with a test runner.
 */
final class GuideTester
{
    use InteractsWithGuides;
}
