<?php

namespace InWeb\Base\Http\Middleware;

use Closure;

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
        $language = $request->segment(1);

        if ($language == config('inweb.default_language')) {
            return abort(404);
        } else if ($language and in_array($language, config('inweb.languages'))) {
            \App::setLocale($language);
        } else {
            \App::setLocale(config('inweb.default_language'));
        }

        $language = \App::getLocale();
        \Carbon\Carbon::setLocale($language);
        setlocale(LC_ALL, $language . '_' . strtoupper($language) . '.UTF-8', $language);

        return $next($request);
    }
}
