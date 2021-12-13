<?php

namespace InWeb\Base\Support;

use Illuminate\Support\Facades\Blade;
use InWeb\Base\Entity;

class App
{
    public static function setLocale($locale)
    {
        \App::setLocale($locale);
        \Carbon\Carbon::setLocale($locale);
        setlocale(LC_ALL, $locale . '_' . strtoupper($locale) . '.UTF-8', $locale);
    }

    public static function registerCacheDirectives()
    {
        Blade::directive('cache', function ($expression) {
            return "<?php
            if (\Cache::has($expression)) {
                echo \Cache::get($expression);
            } else {
            ob_start(); ?>";
        });

        Blade::directive('endcache', function ($expression) {
            return "<?php \$tmpContent = ob_get_clean(); \Cache::set($expression, \$tmpContent, 60); echo \$tmpContent; } ?>";
        });

        Blade::directive('includeCached', function ($expression) {
            $tmp = explode(', [', $expression);
            $file = $tmp[0] ?? null;
            $data = isset($tmp[1]) ? '[' . $tmp[1] : null;
            $tags = isset($tmp[2]) ? '[' . $tmp[2] : null;

            if ($tags)
                $cache = "\Cache::tags($tags)->";
            else
                $cache = "\Cache::";

            $result = "<?php
            if ({$cache}has(\App::getLocale() . ':view:' . $file)) {
                echo {$cache}get(\App::getLocale() . ':view:' . $file);
            } else {
            ob_start();
            echo view($file)->with(" . ($data ?? '') . ")->render();
            ?>";

            $result .= "<?php \$tmpContent = ob_get_clean(); {$cache}set(\App::getLocale() . ':view:' . $file, \$tmpContent, 1400); echo \$tmpContent; } ?>";

            return $result;
        });
    }
}
