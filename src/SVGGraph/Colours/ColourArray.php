<?php

namespace SVGGraph\Colours;

class ColourArray implements \ArrayAccess
{
    private $colours;
    private $count;

    public function __construct($colours)
    {
        $this->colours = $colours;
        $this->count = count($colours);
    }

    /**
     * Not used by this class
     */
    public function setup($count)
    {
        // count comes from array, not number of bars etc.
    }

    /**
     * always true, because it wraps around
     */
    public function offsetExists($offset)
    {
        return true;
    }

    /**
     * return the colour
     */
    public function offsetGet($offset)
    {
        return $this->colours[$offset % $this->count];
    }

    public function offsetSet($offset, $value)
    {
        $this->colours[$offset % $this->count] = $value;
    }

    public function offsetUnset($offset)
    {
        throw new \Exception('Unexpected offsetUnset');
    }
}
