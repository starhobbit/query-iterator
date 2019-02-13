<?php

namespace Photonite\QueryIterator;

use ArrayAccess;
use Countable;
use Iterator;
use LogicException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Traits\Macroable;
use Photonite\QueryIterator\Support\MappedIterator;

class QueryIterator implements Iterator, Countable, ArrayAccess, Arrayable
{
    use Macroable;

    /**
     * The query builder
     *
     * @var Builder|EloquentBuilder
     */
    protected $query;

    /**
     * Chunk size
     *
     * @var integer
     */
    protected $chunkSize;

    /**
     * Number of current chunk
     *
     * @var integer
     */
    protected $chunkOffset = null;

    /**
     * Index of the current element in the chunk
     *
     * @var integer
     */
    protected $offset = 0;

    /**
     * The current chunk items
     *
     * @var array
     */
    protected $chunk = [];

    /**
     * Construct the iterator
     *
     * @param Builder|EloquentBuilder $query
     * @param integer $chunkSize
     */
    public function __construct($query, int $chunkSize = null)
    {
        if ($query instanceof Builder || $query instanceof EloquentBuilder) {
            $this->query = $query;
            $this->chunkSize = value($chunkSize, config('queryiterator.chunk_size'));
        } else {
            throw new InvalidArgumentException("QueryIterator constructor expects query builder or eloquent builder. '" . get_class($query) . "' received.");
        }
    }

    /**
     * Return the current element
     *
     * @return mixed
     */
    public function current()
    {
        return $this->get($this->offset + $this->chunkOffset * $this->chunkSize);
    }

    /**
     * Return the key of the current element
     *
     * @return integer
     */
    public function key()
    {
        return $this->offset + ($this->chunkOffset * $this->chunkSize);
    }

    /**
     * Move forward to next element
     *
     * @return void
     */
    public function next()
    {
        if ($this->shouldLoadNextChunk()) {
            $this->loadNextChunk();
        } else {
            $this->offset++;
        }
    }

    /**
     * Rewind the Iterator to the first element
     *
     * @return void
     */
    public function rewind()
    {
        $this->chunk = [];

        $this->offset = 0;

        $this->chunkOffset = null;
    }

    /**
     * Checks if current position is valid
     *
     * @return boolean
     */
    public function valid()
    {
        return !$this->reachedEnd();
    }

    /**
     * Checks if the passed offset is in the loaded chunk
     *
     * @param integer $offset
     *
     * @return boolean
     */
    public function isLoaded(int $offset)
    {
        // Check that a chunk is loaded
        if ($this->isNotBooted()) {
            return false;
        }

        // The beginning of the chunk
        $chunkStart = $this->chunkOffset * $this->chunkSize;

        return $offset >= $chunkStart
        && $offset <= $this->offset + $chunkStart;
    }

    /**
     * Gets record at the passed offset starting from the beginning of all chuncks
     *
     * @param int $offset
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(int $offset, $default = null)
    {
        $this->bootIfNotBooted();

        $chunkOffset = intdiv($offset, $this->chunkSize);

        $this->loadChunk($chunkOffset);

        $keys = array_keys($this->chunk);

        $modulo = $offset - $chunkOffset * $this->chunkSize;

        if (isset($keys[$modulo])) {
            return $this->chunk[$keys[$modulo]];
        }

        return $default;
    }

    /**
     * Returns the loaded chunk
     *
     * @return void
     */
    public function getChunk()
    {
        $this->bootIfNotBooted();

        return $this->chunk;
    }

    /**
     * Returns the current chunk's offset
     *
     * @return integer|null
     */
    public function getChunkOffset()
    {
        return $this->chunkOffset;
    }

    /**
     * Returns the chunk size
     *
     * @return integer
     */
    public function getChunkSize()
    {
        return $this->chunkSize;
    }

    /**
     * Returns all records from the query
     *
     * @return array
     */
    public function all()
    {
        return $this->query->get()->all();
    }

    /**
     * Returns all records from the query
     *
     * @return array
     */
    public function toArray()
    {
        return $this->all();
    }

    /**
     * Returns the count of the all the records
     *
     * @return integer
     */
    public function count()
    {
        return $this->query->count();
    }

    /**
     * Checks if the offset exists in the query
     *
     * @param integer $offset
     *
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return $this->isLoaded($offset) || ($offset >= 0 && $offset < $this->count());
    }

    /**
     * Gets record at the passed offset starting from the beginning of all chuncks
     *
     * @param integer $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Implements ArrayAccess::offsetSet but does not allow setting
     *
     * @param mixed $offset
     * @param mixed $value
     *
     * @return void
     *
     * @throws LogicException
     */
    public function offsetSet($offset, $value)
    {
        throw new LogicException("Cannot set offset '{$offset}' on read only " . __CLASS__);
    }

    /**
     * Implements ArrayAccess::offsetUnset but does not allow unsetting
     *
     * @param mixed $offset
     *
     * @return void
     *
     * @throws LogicException
     */
    public function offsetUnset($offset)
    {
        throw new LogicException("Cannot unset offset '{$offset}' on read only " . __CLASS__);
    }

    /**
     * Loads the chunk by offset and moves the iterators pointer to the loaded chunk
     *
     * @param integer $offset
     * @return void
     */
    public function loadChunk(int $offset)
    {
        if ($offset !== $this->chunkOffset) {
            $this->chunkOffset = $offset;

            $results = $this->query->forPage($this->chunkOffset + 1, $this->chunkSize)->get()->all();

            $this->chunk = $results;

            $this->offset = 0;
        }
    }

    /**
     * Returns a new iterator that applies the passed function to every element before returning it
     *
     * @param callable $mapper
     *
     * @return MappedIterator
     */
    public function map(callable $mapper)
    {
        return new MappedIterator($this, $mapper);
    }

    /**
     * Returns the first element
     *
     * @param array $colums
     *
     * @return mixed
     */
    public function first($columns = ['*'])
    {
        return $this->query->first($columns);
    }

    /**
     * Checks if the iterator has reached the end
     *
     * @return boolean
     */
    public function reachedEnd()
    {
        $currentChunkSize = count($this->chunk);

        return ($currentChunkSize < $this->chunkSize)
            && $currentChunkSize - 1 == $this->offset;
    }

    /**
     * Checks whether to load the next chunk or not
     *
     * @return boolean
     */
    protected function shouldLoadNextChunk()
    {
        return $this->isNotBooted()
            || ($this->valid() && $this->offset >= $this->chunkSize);
    }

    /**
     * Loads next chunk
     *
     * @return void
     */
    protected function loadNextChunk()
    {
        $this->loadChunk(($this->chunkOffset ?? -1) + 1);
    }

    /**
     * Checks if the iterator has loaded the first chunk or not
     *
     * @return boolean
     */
    protected function isNotBooted()
    {
        return is_null($this->chunkOffset);
    }

    /**
     * Load the first chunk if not loaded
     *
     * @return void
     */
    protected function bootIfNotBooted()
    {
        if ($this->isNotBooted()) {
            $this->loadNextChunk();
        }
    }
}
