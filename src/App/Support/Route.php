<?php

namespace InWeb\Base\Support;

use App;
use InWeb\Base\Entity;

class Route
{
    public static function localized($path, $locale = null)
    {
        $locale = $locale ?? App::getLocale();
        return ($locale == config('inweb.default_language') ? '' : '/' . $locale) . '/' . (trim($path ?? '', '/'));
    }

    public static function otherLocale($locale = null)
    {
        $locale = $locale ?? App::getLocale();

        $locales = config('translations-parser.locales');

        return $locales[$locale == $locales[0] ? 1 : 0];
    }

    public static function alternativeSlug(Entity $entity, $locale = null)
    {
        return optional($entity->getTranslation(self::otherLocale($locale)))->slug;
    }

    public static function pathLocale()
    {
        return App::getLocale() == 'ru' ? null : App::getLocale();
    }

    public static function route(...$args)
    {
        if (count($args))
            array_splice($args, 1, 0, static::pathLocale());

        return route(...$args);
    }
}