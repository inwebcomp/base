<?php

namespace InWeb\Base;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Category
 *
 * @package App
 * @property int id
 */
class Entity extends Model
{
    const STATUS_HIDDEN = 0;
    const STATUS_PUBLISHED = 1;

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Determine if entity is translatable
     *
     * @return bool
     */
    public function translatable()
    {
        return isset($this->translatedAttributes);
    }

    /**
     * Determine if entity is sortable
     *
     * @return bool
     */
    public function positionable()
    {
        return isset($this->sortable);
    }
}
