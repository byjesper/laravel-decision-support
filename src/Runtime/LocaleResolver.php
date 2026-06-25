<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Runtime;

/**
 * Resolves a localized value from an `*_i18n` map using a locale chain:
 * active locale → fallback locale → the supplied base value. The engine is
 * framework-agnostic, so the locales are threaded in via {@see GuideContext}
 * (set by the runner) rather than read from the framework. With both locales
 * null this always returns the base value — the pre-i18n behaviour.
 */
final readonly class LocaleResolver
{
    public function __construct(
        private ?string $locale = null,
        private ?string $fallbackLocale = null,
    ) {}

    /**
     * @param  array<string, mixed>  $i18n
     */
    public function localizedString(array $i18n, string $base): string
    {
        $value = $this->pick($i18n);

        return is_string($value) ? $value : $base;
    }

    /**
     * @param  array<string, mixed>  $i18n
     */
    public function localizedNullableString(array $i18n, ?string $base): ?string
    {
        $value = $this->pick($i18n);

        return is_string($value) ? $value : $base;
    }

    /**
     * @param  array<string, mixed>  $i18n
     * @param  list<string>  $base
     * @return list<string>
     */
    public function localizedList(array $i18n, array $base): array
    {
        $value = $this->pick($i18n);

        if (! is_array($value)) {
            return $base;
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => is_scalar($item) ? (string) $item : '', $value),
            static fn (string $item): bool => $item !== '',
        ));
    }

    /**
     * @param  array<string, mixed>  $i18n
     */
    private function pick(array $i18n): mixed
    {
        if ($this->locale !== null && array_key_exists($this->locale, $i18n)) {
            return $i18n[$this->locale];
        }

        if ($this->fallbackLocale !== null && array_key_exists($this->fallbackLocale, $i18n)) {
            return $i18n[$this->fallbackLocale];
        }

        return null;
    }
}
