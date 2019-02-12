<?php

namespace Photonite\QueryIterator\Support;

use ArrayAccess;
use Countable;
use Iterator;
use Illuminate\Contracts\Support\Arrayable;

class HigherOrderIterator implements Iterator
{
    public $internal;

    public function __construct(Iterator $internal)
    {
        $this->internal = $internal;
    }

    public function current()
    {
        return $this->internal->current();
    }

    public function key()
    {
        return $this->internal->key();
    }

    public function next()
    {
        return $this->internal->next();
    }

    public function rewind()
    {
        return $this->internal->rewind();
    }

    public function valid()
    {
        return $this->internal->valid();
    }
}
