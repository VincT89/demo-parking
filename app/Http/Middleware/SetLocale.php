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
        $locale = (string) $request->session()->get('locale', config('app.locale', 'it'));

        if (! in_array($locale, $supported, true)) {
            $locale = (string) config('app.locale', 'it');
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale === 'en_GB' ? 'en' : $locale);

        $response = $next($request);
        $htmlLocale = config("app.supported_locales.{$locale}.html", str_replace('_', '-', $locale));
        $response->headers->set('Content-Language', $htmlLocale);

        return $response;
    }
}
