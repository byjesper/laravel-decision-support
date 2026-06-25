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
        public ?string $locale = null,
        public ?string $fallbackLocale = null,
    ) {}

    public function withAnswer(string $key, mixed $value): self
    {
        return new self([...$this->answers, $key => $value], $this->facts, $this->locale, $this->fallbackLocale);
    }

    public function withFact(string $fact, mixed $value): self
    {
        return new self($this->answers, [...$this->facts, $fact => $value], $this->locale, $this->fallbackLocale);
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

    /** @return array{answers: array<string, mixed>, facts: array<string, mixed>, locale: ?string, fallbackLocale: ?string} */
    public function toArray(): array
    {
        return [
            'answers' => $this->answers,
            'facts' => $this->facts,
            'locale' => $this->locale,
            'fallbackLocale' => $this->fallbackLocale,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $answers */
        $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];
        /** @var array<string, mixed> $facts */
        $facts = is_array($data['facts'] ?? null) ? $data['facts'] : [];
        $locale = is_string($data['locale'] ?? null) ? $data['locale'] : null;
        $fallbackLocale = is_string($data['fallbackLocale'] ?? null) ? $data['fallbackLocale'] : null;

        return new self($answers, $facts, $locale, $fallbackLocale);
    }
}
