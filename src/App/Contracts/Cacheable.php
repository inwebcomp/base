<?php

namespace InWeb\Base\Contracts;

interface Cacheable
{
    public static function clearCache(Cacheable $model = null);
    public static function cacheTagAll();
    public function cacheTag();
}