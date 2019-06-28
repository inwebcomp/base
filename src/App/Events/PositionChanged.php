<?php

namespace InWeb\Base\Events;

use Illuminate\Foundation\Events\Dispatchable;

class PositionChanged
{
    use Dispatchable;
    /**
     * @var string
     */
    public $tags;

    /**
     * @var array|null
     */
    public $ids;

    /**
     * @param $tags
     * @param $ids
     */
    public function __construct($tags, $ids = null)
    {
        $this->tags = $tags;
        $this->ids = $ids;
    }
}
