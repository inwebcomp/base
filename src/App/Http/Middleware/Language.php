<?php

namespace InWeb\Base\Http\Middleware;

use Closure;
use InWeb\Base\Support\App;

class Language
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
        $locale = $request->segment(1);

        $useLocale = config('inweb.default_language');

        if ($locale == config('inweb.default_language')) {
            return abort(404);
        } else if ($locale and in_array($locale, config('inweb.languages'))) {
            $useLocale = $locale;
        }

        App::setLocale($useLocale);

        return $next($request);
    }
}
