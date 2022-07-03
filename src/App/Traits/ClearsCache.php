<?php

namespace InWeb\Base\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InWeb\Base\Contracts\Cacheable;
use InWeb\Base\Entity;

trait ClearsCache
{
    protected static function bootClearsCache()
    {
        static::updated(function () {
            static::clearCache();
        });
        static::deleted(function () {
            static::clearCache();
        });
        static::created(function () {
            static::clearCache();
        });
    }

    public static function clearCache(Cacheable $model = null): void
    {
        Cache::tags(static::cacheTagAll())->flush();

        if ($model) {
            Cache::tags($model->cacheTag())->flush();
        }
    }

    public static function cacheTagAll(): string
    {
        return Str::plural(class_basename(static::class));
    }

    public function cacheTag(): string
    {
        return class_basename(static::class) . ':' . $this->getKey();
    }
}
