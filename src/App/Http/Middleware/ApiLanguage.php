<?php

namespace InWeb\Base\Http\Middleware;

use Closure;
use InWeb\Base\Support\App;

class ApiLanguage
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param Closure                   $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $useLocale = config('inweb.default_language');

        $locale = $request->get('language');

        if ($locale and in_array($locale, config('inweb.languages'))) {
            $useLocale = $locale;
        }

        App::setLocale($useLocale);

        return $next($request);
    }
}
