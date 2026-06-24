<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Validation;

final readonly class ValidationError
{
    public function __construct(
        public string $code,
        public string $message,
        public ?string $nodeKey = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['code' => $this->code, 'message' => $this->message, 'nodeKey' => $this->nodeKey];
    }
}
