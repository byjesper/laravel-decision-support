<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Runtime\LocaleResolver;

it('resolves a string through the locale → fallback → base chain', function (): void {
    $map = ['da' => 'Ansat', 'en' => 'Employed'];

    expect((new LocaleResolver('da'))->localizedString($map, 'BASE'))->toBe('Ansat')
        ->and((new LocaleResolver('en'))->localizedString($map, 'BASE'))->toBe('Employed')
        ->and((new LocaleResolver('de', 'da'))->localizedString($map, 'BASE'))->toBe('Ansat')
        ->and((new LocaleResolver('de'))->localizedString($map, 'BASE'))->toBe('BASE')
        ->and((new LocaleResolver)->localizedString($map, 'BASE'))->toBe('BASE');
});

it('resolves nullable strings, keeping null when there is no translation or base', function (): void {
    expect((new LocaleResolver('da'))->localizedNullableString(['da' => 'x'], null))->toBe('x')
        ->and((new LocaleResolver('da'))->localizedNullableString([], null))->toBeNull()
        ->and((new LocaleResolver('da'))->localizedNullableString([], 'base'))->toBe('base');
});

it('resolves and normalises a list', function (): void {
    expect((new LocaleResolver('da'))->localizedList(['da' => ['a', 'b']], ['base']))->toBe(['a', 'b'])
        ->and((new LocaleResolver('da'))->localizedList([], ['base']))->toBe(['base'])
        ->and((new LocaleResolver('de', 'da'))->localizedList(['da' => ['x']], []))->toBe(['x'])
        // non-string members are coerced/filtered to a clean list<string>
        ->and((new LocaleResolver('da'))->localizedList(['da' => ['x', '', 5, null]], []))->toBe(['x', '5']);
});
