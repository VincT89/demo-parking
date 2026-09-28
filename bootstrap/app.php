<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'parking.context' => \App\Http\Middleware\ResolveParkingContext::class,
            'booking.context' => \App\Http\Middleware\ResolvePublicBookingContext::class,
            'subscription.context' => \App\Http\Middleware\ResolvePublicSubscriptionContext::class,
        ]);
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\ResolveParkingContext::class
        );
        $middleware->prependToPriorityList(
            \App\Http\Middleware\ResolveParkingContext::class,
            \App\Http\Middleware\SetLocale::class
        );
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/stripe',
            'webhooks/paypal',
            'webhooks/*/stripe',
            'webhooks/*/paypal',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['credentials', 'payment_settings', 'aruba_password', 'aruba_client_secret']);
    })->create();
