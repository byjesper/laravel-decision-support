<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Validation;

final readonly class ValidationError
{
    /**
     * @param  array<string, string|int>  $params  the values interpolated into `message`,
     *                                             exposed so consumers can re-render it in
     *                                             another language by `code` + these params
     */
    public function __construct(
        public string $code,
        public string $message,
        public ?string $nodeKey = null,
        public array $params = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['code' => $this->code, 'message' => $this->message, 'nodeKey' => $this->nodeKey, 'params' => $this->params];
    }
}
