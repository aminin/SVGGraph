<?php

namespace SVGGraph\Axis;

/**
 * Class for axis grid points
 */
class GridPoint
{
    public $position;
    public $text;
    public $value;

    public function __construct($position, $text, $value)
    {
        $this->position = $position;
        $this->text = $text;
        $this->value = $value;
    }

    public static function sort($a, $b)
    {
        return $a->position - $b->position;
    }

    public static function rsort($a, $b)
    {
        return $b->position - $a->position;
    }
}
