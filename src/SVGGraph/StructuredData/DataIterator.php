<?php

namespace SVGGraph\StructuredData;

/**
 * For iterating over structured data
 */
class DataIterator implements \Iterator
{
    private $data = [];
    private $dataset = 0;
    private $position = 0;
    private $count = 0;
    private $structure = null;
    private $key_field = 0;
    private $dataset_fields = [];

    public function __construct($data, $dataset, $structure)
    {
        $this->dataset = $dataset;
        $this->data = $data;
        $this->count = count($data);
        $this->structure = $structure;

        $this->key_field = $structure['key'];
        $this->dataset_fields = $structure['value'];
    }

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
    }

    public function rewind()
    {
        $this->position = 0;
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
        if (isset($this->data[$index])) {
            $key = is_null($this->key_field) ? $index : null;
            return new DataItem($this->data[$index], $this->structure, $this->dataset, $key);
        }

        return null;
    }

    /**
     * Returns an item by key
     * @param $key
     * @return null|DataItem
     */
    public function GetItemByKey($key)
    {
        if (is_null($this->key_field)) {
            if (isset($this->data[$key])) {
                return new DataItem($this->data[$key], $this->structure, $this->dataset, $key);
            }
        } else {
            foreach ($this->data as $item) {
                if (isset($item[$this->key_field]) && $item[$this->key_field] == $key) {
                    return new DataItem($item, $this->structure, $this->dataset, $key);
                }
            }
        }
        return null;
    }
}
