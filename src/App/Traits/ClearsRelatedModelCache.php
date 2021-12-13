<?php

namespace InWeb\Base\Traits;

use InWeb\Base\Contracts\Cacheable;
use InWeb\Base\Entity;

trait ClearsRelatedModelCache
{
    protected static function boot()
    {
        parent::boot();

        static::updated(function ($model) {
            if ($model instanceof Cacheable)
                static::clearCache($model);
            else
                static::clearCache();
        });
        static::deleted(function ($model) {
            if ($model instanceof Cacheable)
                static::clearCache($model);
            else
                static::clearCache();
        });
        static::created(function () {
            static::clearCache();
        });
    }

    public static function clearCache(Cacheable $model = null)
    {
        if ($model)
            static::getMainClass()::clearCache($model);
        else
            static::getMainClass()::clearCache();
    }

    /**
     * @return string|Entity|ClearsCache
     */
    public static function getMainClass()
    {
        return str_replace(['Translations', 'Translation'], ['Models', ''], static::class);
    }
}
