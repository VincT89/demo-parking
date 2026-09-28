<?php

namespace App\Integrations;

use App\Integrations\Contracts\PlatformAdapterInterface;
use App\Models\Platform;
use Illuminate\Support\Collection;

class AdapterRegistry
{
    /** @var Collection<string, PlatformAdapterInterface> */
    private Collection $adapters;

    public function __construct()
    {
        $this->adapters = new Collection();
    }

    public function register(PlatformAdapterInterface $adapter): void
    {
        $this->adapters->put($adapter->getPlatformSlug(), $adapter);
    }

    /**
     * @throws \Exception Se l'adapter non è registrato per la piattaforma.
     */
    public function forPlatform(Platform $platform): PlatformAdapterInterface
    {
        if (! config('demo.enabled') && ! $platform->uses_legacy_credentials) {
            return app(PlatformConnectionFactory::class)->make($platform);
        }
        $adapter = $this->adapters->get($platform->slug);

        if (!$adapter) {
            throw new \Exception("Nessun adapter registrato per la piattaforma '{$platform->name}' (slug: '{$platform->slug}').");
        }

        return $adapter;
    }
}
