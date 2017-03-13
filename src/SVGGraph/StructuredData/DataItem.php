<?php

namespace SVGGraph\StructuredData;

/**
 * Class for structured data items
 */
class DataItem
{
    public $key = 0;
    public $value = null;

    private $item;
    private $dataSetIndex = 0;
    private $keyField = 0;
    private $datasetFields = [];
    private $structure;

    /**
     * @param      $item
     * @param      $structure
     * @param      $dataSetIndex
     * @param null $key
     */
    public function __construct($item, &$structure, $dataSetIndex, $key = null)
    {
        $this->item = $item;
        $this->keyField = $structure['key'];
        $this->datasetFields = $structure['value'];
        $this->key = is_null($this->keyField) ? $key : $item[$this->keyField];
        if (isset($this->datasetFields[$dataSetIndex]) && isset($item[$this->datasetFields[$dataSetIndex]])) {
            $this->value = $item[$this->datasetFields[$dataSetIndex]];
        }

        $this->dataSetIndex = $dataSetIndex;
        $this->structure = &$structure;
    }

    /**
     * Constructs a new data item with a different dataset
     */
    public function newFrom($dataSetIndex)
    {
        return new static($this->item, $this->structure, $dataSetIndex, $this->key);
    }

    /**
     * Returns some extra data from item
     */
    public function data($field)
    {
        if (!isset($this->structure[$field])) {
            return null;
        }
        $item_field = $this->structure[$field];
        if (is_array($item_field)) {
            if(!isset($item_field[$this->dataSetIndex]))
              return null;
            $item_field = $item_field[$this->dataSetIndex];
        }

        return isset($this->item[$item_field]) ? $this->item[$item_field] : null;
    }

    /**
     * Check if extra data field exists
     */
    public function rawDataExists($field)
    {
        return isset($this->item[$field]);
    }

    /**
     * Returns a value from the item without translating structure
     */
    public function rawData($field)
    {
        return isset($this->item[$field]) ? $this->item[$field] : null;
    }
}

