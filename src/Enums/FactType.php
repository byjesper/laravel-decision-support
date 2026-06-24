<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Enums;

enum FactType: string
{
    case Boolean = 'boolean';
    case Enum = 'enum';
    case Number = 'number';
    case Date = 'date';
    case Text = 'text';
}
