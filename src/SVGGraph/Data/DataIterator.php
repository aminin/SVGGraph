<?php

namespace SVGGraph\Data;

/**
 * Class to iterate over standard data
 */
class DataIterator implements \Iterator
{
    private $data = 0;
    private $dataSetIndex = 0;
    private $position = 0;
    private $count = 0;

    public function __construct($data, $dataSetIndex)
    {
        $this->dataSetIndex = $dataSetIndex;
        $this->data = $data;
        $this->count = count($data[$dataSetIndex]);
    }

    /**
     * Iterator methods
     */
    public function current()
    {
        return $this->getItemByIndex($this->position);
    }

    public function key()
    {
        return $this->position;
    }

    public function next()
    {
        ++$this->position;
        next($this->data[$this->dataSetIndex]);
    }

    public function rewind()
    {
        $this->position = 0;
        reset($this->data[$this->dataSetIndex]);
    }

    public function valid()
    {
        return $this->position < $this->count;
    }

    /**
     * Returns an item by index
     * @param $index
     * @return null|DataItem
     */
    public function getItemByIndex($index)
    {
        $slice = array_slice($this->data[$this->dataSetIndex], $index, 1, true);
        // use foreach to get key and value
        foreach ($slice as $k => $v) {
            return new DataItem($k, $v);
        }
        return null;
    }

    /**
     * Returns an item by its key
     * @param $key
     * @return null|DataItem
     */
    public function getItemByKey($key)
    {
        if (isset($this->data[$this->dataSetIndex][$key])) {
            return new DataItem($key, $this->data[$this->dataSetIndex][$key]);
        }
        return null;
    }
}
