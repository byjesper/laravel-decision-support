<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Runtime;

/**
 * A request for input that suspends a run. The host UI renders it (a question
 * form, or a provider-driven lookup such as MindKey's "search & pick") and
 * feeds the captured value back through {@see GuideRunner::advance()}.
 */
final readonly class Interaction
{
    /**
     * @param  'question'|'lookup'  $kind
     * @param  'boolean'|'select'|'date'|'text'|'number'  $inputType
     * @param  list<array{value: string, label: string}>  $options
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $nodeKey,
        public string $kind,
        public string $prompt,
        public string $inputType,
        public array $options = [],
        public array $meta = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'nodeKey' => $this->nodeKey,
            'kind' => $this->kind,
            'prompt' => $this->prompt,
            'inputType' => $this->inputType,
            'options' => $this->options,
            'meta' => $this->meta,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var 'question'|'lookup' $kind */
        $kind = is_string($data['kind'] ?? null) ? $data['kind'] : 'question';
        /** @var 'boolean'|'select'|'date'|'text'|'number' $inputType */
        $inputType = is_string($data['inputType'] ?? null) ? $data['inputType'] : 'text';
        /** @var list<array{value: string, label: string}> $options */
        $options = is_array($data['options'] ?? null) ? array_values($data['options']) : [];
        /** @var array<string, mixed> $meta */
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        return new self(
            nodeKey: is_string($data['nodeKey'] ?? null) ? $data['nodeKey'] : '',
            kind: $kind,
            prompt: is_string($data['prompt'] ?? null) ? $data['prompt'] : '',
            inputType: $inputType,
            options: $options,
            meta: $meta,
        );
    }
}
