<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Runtime;

/**
 * The verdict a run terminates on. `unknown` is reserved for the runtime's
 * safety rails (cycle guard, step budget, unresolved routing) — a guide reaches
 * it only when something prevented a normal outcome, never by throwing.
 */
final readonly class Outcome
{
    /** @param list<string> $warnings */
    public function __construct(
        public string $nodeKey,
        public string $verdict,
        public ?string $text = null,
        public array $warnings = [],
        public bool $unknown = false,
    ) {}

    public static function unknown(string $nodeKey, string $reason): self
    {
        return new self($nodeKey, 'unknown', $reason, [$reason], true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'nodeKey' => $this->nodeKey,
            'verdict' => $this->verdict,
            'text' => $this->text,
            'warnings' => $this->warnings,
            'unknown' => $this->unknown,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var list<string> $warnings */
        $warnings = is_array($data['warnings'] ?? null) ? array_values($data['warnings']) : [];

        return new self(
            nodeKey: is_string($data['nodeKey'] ?? null) ? $data['nodeKey'] : '',
            verdict: is_string($data['verdict'] ?? null) ? $data['verdict'] : 'unknown',
            text: is_string($data['text'] ?? null) ? $data['text'] : null,
            warnings: $warnings,
            unknown: (bool) ($data['unknown'] ?? false),
        );
    }
}
