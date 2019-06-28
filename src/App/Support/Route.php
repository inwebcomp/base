<?php

namespace InWeb\Base\Support;

use App;
use InWeb\Base\Entity;

class Route
{
    public static function localized($path, $locale = null)
    {
        $locale = $locale ?? App::getLocale();
        return ($locale == config('app.default_locale') ? '' : '/' . $locale) . '/' . (trim($path ?? '', '/'));
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
}