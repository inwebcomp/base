<?php

namespace InWeb\Base\Traits;

use Illuminate\Database\Schema\Blueprint;

/**
 * @property int status
 */
trait WithCustomStatus
{
    public static function statusColumn(Blueprint $table)
    {
        $table->tinyInteger('status')->default(static::defaultStatus());
    }

    abstract public static function defaultStatus();
}
