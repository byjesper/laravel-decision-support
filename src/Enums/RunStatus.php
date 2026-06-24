<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Enums;

enum RunStatus: string
{
    case Running = 'running';
    case Suspended = 'suspended';
    case Completed = 'completed';
}
