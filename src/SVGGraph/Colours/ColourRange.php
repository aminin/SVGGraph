<?php

namespace SVGGraph\Colours;

/**
 * Abstract class implements common methods
 */
abstract class ColourRange implements \ArrayAccess
{
    protected $count = 2;

    /**
     * Clamps a value to range $min-$max
     */
    protected static function clamp($val, $min, $max)
    {
        return min($max, max($min, $val));
    }

    /**
     * Sets up the length of the range
     */
    public function setup($count)
    {
        $this->count = $count;
    }

    /**
     * always true, because it wraps around
     */
    public function offsetExists($offset)
    {
        return true;
    }

    public function offsetSet($offset, $value)
    {
        throw new \Exception('Unexpected offsetSet');
    }

    public function offsetUnset($offset)
    {
        throw new \Exception('Unexpected offsetUnset');
    }
}
