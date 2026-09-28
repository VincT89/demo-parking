<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(\App\Tenancy\ParkingContext::class, fn () => new \App\Tenancy\ParkingContext());
        $this->app->singleton(\App\Integrations\AdapterRegistry::class, function ($app) {
            $registry = new \App\Integrations\AdapterRegistry();

            if (config('demo.enabled')) {
                foreach (['parkos', 'vologio', 'parking-my-car'] as $platformSlug) {
                    $registry->register(new \App\Integrations\Adapters\DemoPlatformAdapter($platformSlug));
                }

                return $registry;
            }
            
            if (config('services.parkos.enabled')) {
                $registry->register(
                    new \App\Integrations\Adapters\ParkosAdapter(
                        $app->make(\App\Integrations\Support\ParkosClient::class),
                        $app->make(\App\Integrations\Support\FixturePayloadReader::class)
                    )
                );
            }
            if (config('services.vologio.enabled')) {
                $registry->register(
                    new \App\Integrations\Adapters\VologioAdapter(
                        $app->make(\App\Integrations\Support\RooshProviderClient::class)
                    )
                );
            }
            if (config('services.parking_my_car.enabled')) {
                $registry->register(
                    new \App\Integrations\Adapters\ParkingMyCarAdapter(
                        $app->make(\App\Integrations\Support\ParkingMyCarClient::class)
                    )
                );
            }
            
            return $registry;
        });
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Blade::directive('parkingContext', function () {
            return '<input type="hidden" name="_parking_context" value="<?php echo (int) (app(\\App\\Tenancy\\ParkingContext::class)->parking?->id ?? 0); ?>">';
        });
        Gate::define('manage-shared-connections', function (User $user) {
            return $user->isAdmin() && $user->managements()->where('is_initial', true)->wherePivot('role', 'admin')->exists();
        });

        \Carbon\Carbon::setLocale(config('app.locale', 'it'));

        if (config('demo.enabled')) {
            \Illuminate\Support\Facades\Http::preventStrayRequests();
            \Illuminate\Support\Facades\Http::allowStrayRequests([
                'https://demoauth.fatturazioneelettronica.aruba.it/*',
                'https://demows.fatturazioneelettronica.aruba.it/*',
                'https://auth.fatturazioneelettronica.aruba.it/*',
                'https://ws.fatturazioneelettronica.aruba.it/*',
            ]);
        }

        \Illuminate\Support\Facades\Gate::define('manage-platforms', function (\App\Models\User $user) {
            return $user->isAdmin();
        });

        \Illuminate\Support\Facades\Gate::define('manage-parkings', function (\App\Models\User $user) {
            return $user->isAdmin();
        });

        \Illuminate\Support\Facades\View::composer(['booking.*', 'layouts.public'], function ($view) {
            $context = app(\App\Tenancy\ParkingContext::class);
            $view->with('bookingParking', $context->parking)->with('bookingManagement', $context->management);
        });

        \Illuminate\Support\Facades\View::composer('*', function ($view) {
            $context = app(\App\Tenancy\ParkingContext::class);
            if (auth()->check() && $context->restricted && $context->management) {
                $alertCount = cache()->remember('alert_count_' . auth()->id() . '_parking_' . ($context->parking?->id ?? 0), 5, function () {
                    $parkings = \App\Models\Parking::active()->get();
                    if ($parkings->isEmpty())
                        return 0;
                    return count((new \App\Services\AlertService())->getAlertsForParkings($parkings));
                });
                $view->with('alertCount', $alertCount);
                $view->with('activeParking', $context->parking);
                $view->with('activeManagement', $context->management);
                $view->with('accessibleParkings', request()->attributes->get('accessible_parkings', collect()));
            }
        });
    }
}
