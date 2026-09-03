<?php

namespace App\Integrations\Support;

use Illuminate\Support\Facades\File;

class FixturePayloadReader
{
    /**
     * Load and parse a JSON fixture for a given platform.
     *
     * @param string $platformSlug
     * @param string $fixtureFile
     * @return array
     *
     * @throws \RuntimeException
     */
    public function loadFixture(string $platformSlug, string $fixtureFile): array
    {
        $path = $this->fixturePath($platformSlug, $fixtureFile);

        if (!File::exists($path)) {
            throw new \RuntimeException("Fixture file not found at path: {$path}");
        }

        $content = File::get($path);
        
        $payload = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON in fixture {$fixtureFile}: " . json_last_error_msg());
        }

        return $payload;
    }

    /**
     * Get the absolute path to the fixture file.
     *
     * @param string $platformSlug
     * @param string $fixtureFile
     * @return string
     */
    public function fixturePath(string $platformSlug, string $fixtureFile): string
    {
        // For example: base_path('tests/Fixtures/Integrations/Parkos/reservations_success.json')
        $folder = str_replace('-', '', ucwords($platformSlug, '-'));
        return base_path("tests/Fixtures/Integrations/{$folder}/{$fixtureFile}");
    }
}
