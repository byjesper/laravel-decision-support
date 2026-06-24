<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Runtime;

enum NodeResultType
{
    /** Move on through a named port. */
    case Advance;

    /** Pause and request host input. */
    case Suspend;

    /** Finish with a verdict. */
    case Terminate;
}
