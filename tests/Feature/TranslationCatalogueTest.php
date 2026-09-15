<?php

test('translation catalogues are valid and contain no duplicate keys', function () {
    foreach (['it', 'en_GB', 'nl'] as $locale) {
        $contents = file_get_contents(lang_path($locale.'.json'));

        expect(fn () => json_decode($contents, true, 512, JSON_THROW_ON_ERROR))
            ->not->toThrow(JsonException::class);

        preg_match_all('/^\s*"((?:\\\\.|[^"])*)"\s*:/m', $contents, $matches);
        $duplicates = array_filter(
            array_count_values($matches[1]),
            fn (int $occurrences) => $occurrences > 1,
        );

        expect($duplicates)->toBe([], "Duplicate translation keys found in {$locale}.json");
    }
});

test('english and dutch catalogues cover every registered italian key', function () {
    $italianKeys = array_keys(json_decode(
        file_get_contents(lang_path('it.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    ));

    foreach (['en_GB', 'nl'] as $locale) {
        $translatedKeys = array_keys(json_decode(
            file_get_contents(lang_path($locale.'.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        ));

        expect(array_values(array_diff($italianKeys, $translatedKeys)))
            ->toBe([], "Missing translations in {$locale}.json");
    }
});
