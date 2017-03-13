<?php

namespace SVGGraph\Data;

/**
 * Class for single data items
 */
class DataItem
{
    public $key;
    public $value;

    public function __construct($key, $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    /**
     * Returns NULL because standard data doesn't support extra fields
     */
    public function data($field)
    {
        return null;
    }
}
