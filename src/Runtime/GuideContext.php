<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Runtime;

/**
 * The accumulated state a guide branches on: user `answers` (captured by
 * question nodes) and resolved `facts` (provided by the host). Immutable —
 * every mutation returns a new instance so {@see RunState} stays a value object.
 */
final readonly class GuideContext
{
    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $facts
     */
    public function __construct(
        public array $answers = [],
        public array $facts = [],
    ) {}

    public function withAnswer(string $key, mixed $value): self
    {
        return new self([...$this->answers, $key => $value], $this->facts);
    }

    public function withFact(string $fact, mixed $value): self
    {
        return new self($this->answers, [...$this->facts, $fact => $value]);
    }

    public function answer(string $key): mixed
    {
        return $this->answers[$key] ?? null;
    }

    public function fact(string $fact): mixed
    {
        return $this->facts[$fact] ?? null;
    }

    public function hasFact(string $fact): bool
    {
        return array_key_exists($fact, $this->facts);
    }

    /**
     * Variables exposed to expression conditions: facts win over answers on a
     * name clash since facts are the authoritative resolved values.
     *
     * @return array<string, mixed>
     */
    public function variables(): array
    {
        return [...$this->answers, ...$this->facts];
    }

    /** @return array{answers: array<string, mixed>, facts: array<string, mixed>} */
    public function toArray(): array
    {
        return ['answers' => $this->answers, 'facts' => $this->facts];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $answers */
        $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];
        /** @var array<string, mixed> $facts */
        $facts = is_array($data['facts'] ?? null) ? $data['facts'] : [];

        return new self($answers, $facts);
    }
}
