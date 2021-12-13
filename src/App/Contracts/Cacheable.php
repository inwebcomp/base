<?php

namespace InWeb\Base\Contracts;

interface Cacheable
{
    public static function clearCache(Cacheable $model = null): void;
    public static function cacheTagAll(): string;
    public function cacheTag(): string;
}
