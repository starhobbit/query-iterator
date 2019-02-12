<?php

namespace Photonite\QueryIterator\Support;

use Iterator;

class MappedIterator extends HigherOrderIterator
{
    /**
     * MApper function
     *
     * @var callable
     */
    protected $mapper;

    /**
     * Constructs the mapped iterator
     *
     * @param Iterator $iterator
     * @param callable $mapper
     */
    public function __construct(Iterator $internal, callable $mapper)
    {
        parent::__construct($internal);
        $this->mapper = $mapper;
    }

    /**
     * Returns the current element after applying the mapper function
     *
     * @return mixed
     */
    public function current()
    {
        return call_user_func($this->mapper, $this->internal->current());
    }
}
