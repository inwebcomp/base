<?php

namespace InWeb\Base\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;

/**
 * @property int status
 */
trait WithStatus
{
    public static function statusColumn(Blueprint $table)
    {
        $table->tinyInteger('status')->default(static::defaultStatus());
    }

    public static function defaultStatus()
    {
        return static::STATUS_PUBLISHED;
    }

    /**
     * Отбирает только опубликованные объекты
     * @param Builder $query
     * @return Builder
     */
    public function scopePublished(Builder $query)
    {
        return $query->where('status', '=', static::STATUS_PUBLISHED);
    }

    /**
     * Отбирает только неопубликованные объекты
     * @param Builder $query
     * @return Builder
     */
    public function scopeHidden(Builder $query)
    {
        return $query->where('status', '=', static::STATUS_HIDDEN);
    }

    /**
     * Скрыт ли объект
     */
    public function isHidden()
    {
        return (isset($this->status) && $this->status == static::STATUS_HIDDEN);
    }

    /**
     * Опубликован ли объект
     */
    public function isPublished()
    {
        return (! isset($this->status) || $this->status == static::STATUS_PUBLISHED);
    }

    /**
     *  Публикет объект
     */
    public function publish()
    {
        $this->status = static::STATUS_PUBLISHED;
        return $this->save();
    }

    /**
     *  Публикет объект
     */
    public function hide()
    {
        $this->status = static::STATUS_HIDDEN;
        return $this->save();
    }
}
