<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('app.supported_locales', []));
        $defaultLocale = (string) config('app.locale', 'it');

        if (! in_array($defaultLocale, $supported, true)) {
            $defaultLocale = $supported[0] ?? 'it';
        }

        $locale = (string) $request->session()->get('locale', $defaultLocale);

        if (! in_array($locale, $supported, true)) {
            $locale = $defaultLocale;
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale === 'en_GB' ? 'en' : $locale);

        $response = $next($request);
        $htmlLocale = config("app.supported_locales.{$locale}.html", str_replace('_', '-', $locale));
        $response->headers->set('Content-Language', $htmlLocale);

        return $response;
    }
}
