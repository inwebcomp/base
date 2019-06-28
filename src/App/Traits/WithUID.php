<?php

namespace InWeb\Base\Traits;

/**
 * Trait WithUID
 * @package App\Traits
 * @property string uid
 */
trait WithUID
{
    /**
     * @param string $uid
     * @return static|null
     */
    public static function findByUID($uid)
    {
        return static::where('uid', $uid)->first();
    }
}
