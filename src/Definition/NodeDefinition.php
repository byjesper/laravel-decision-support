<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Definition;

use ByJesper\DecisionSupport\Contracts\NodeType;

/**
 * A single node in a published guide snapshot. `config` is interpreted by the
 * matching {@see NodeType}.
 */
final readonly class NodeDefinition
{
    /** @param array<string, mixed> $config */
    public function __construct(
        public string $key,
        public string $type,
        public array $config = [],
        public ?string $label = null,
        public ?int $position = null,
    ) {}

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'config' => $this->config,
            'label' => $this->label,
            'position' => $this->position,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $config */
        $config = is_array($data['config'] ?? null) ? $data['config'] : [];

        return new self(
            key: is_string($data['key'] ?? null) ? $data['key'] : '',
            type: is_string($data['type'] ?? null) ? $data['type'] : '',
            config: $config,
            label: is_string($data['label'] ?? null) ? $data['label'] : null,
            position: is_int($data['position'] ?? null) ? $data['position'] : null,
        );
    }
}
