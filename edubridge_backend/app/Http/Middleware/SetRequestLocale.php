<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class SetRequestLocale
{
    /**
     * @var list<string>
     */
    private array $supportedLocales = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->getPreferredLanguage($this->supportedLocales) ?? config('app.fallback_locale', 'en');

        if (! in_array($locale, $this->supportedLocales, true)) {
            $locale = 'en';
        }

        App::setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
